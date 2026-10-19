<?php

namespace App\Modules\Boe\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * المملكة المتحدة من بنك إنجلترا (قاعدة البيانات الإحصائية IADB العامة): سعر الفائدة الرسمي (Bank Rate) وSONIA، وعوائد السندات الحكومية
 * (جيلتس) لـ5 و10 و20 سنة، وسعر الجنيه الإسترليني مقابل الدولار واليورو والين. يوميًا منذ 1975 أو أقرب تاريخ متاح.
 */
final class BoeUkConnector extends ApiConnector
{
    private const URL = 'https://www.bankofengland.co.uk/boeapps/database/_iadb-fromshowcolumns.asp';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float, 5: float}> رمز BoE => [مفتاح، عربي، إنجليزي، وحدة، حد أدنى، حد أعلى] */
    private const SERIES = [
        'IUDBEDR' => ['boe.bank_rate', 'سعر الفائدة الرسمي لبنك إنجلترا (Bank Rate)', 'Bank of England Bank Rate', '%', -1, 30],
        'IUDSOIA' => ['boe.sonia', 'معدل SONIA (الفائدة بين عشية وضحاها بالجنيه)', 'SONIA overnight rate', '%', -1, 30],
        'IUDSNPY' => ['boe.gilt_5y', 'عائد السندات الحكومية البريطانية 5 سنوات', 'UK gilt nominal par yield 5y', '%', -2, 30],
        'IUDMNPY' => ['boe.gilt_10y', 'عائد السندات الحكومية البريطانية 10 سنوات', 'UK gilt nominal par yield 10y', '%', -2, 30],
        'IUDLNPY' => ['boe.gilt_20y', 'عائد السندات الحكومية البريطانية 20 سنة', 'UK gilt nominal par yield 20y', '%', -2, 30],
        'XUDLUSS' => ['boe.gbpusd', 'الجنيه الإسترليني مقابل الدولار', 'GBP/USD', 'دولار', 0.5, 5],
        'XUDLERS' => ['boe.gbpeur', 'الجنيه الإسترليني مقابل اليورو', 'GBP/EUR', 'يورو', 0.5, 5],
        'XUDLJYS' => ['boe.gbpjpy', 'الجنيه الإسترليني مقابل الين', 'GBP/JPY', 'ين', 100, 900],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as [$key, $ar, $en, $unit, $lo, $hi]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'D', [new Range($lo, $hi, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'boe.uk_markets',
            source: new SourceDefinition(key: 'boe', nameAr: 'بنك إنجلترا', nameEn: 'Bank of England (Statistical Interactive Database)', publisher: 'Bank of England', url: 'https://www.bankofengland.co.uk/boeapps/database', license: 'BoE — مع الإشارة إلى المصدر'),
            nameAr: 'المملكة المتحدة: الفائدة والجيلتس والجنيه',
            nameEn: 'UK: Bank Rate, gilts, sterling',
            section: 'markets',
            intervalMinutes: 6 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        $i = 0;
        foreach (self::SERIES as $code => [$key]) {
            if ($i++ > 0) {
                usleep(500_000);
            }
            $csv = $this->httpAccepting('text/csv')->timeout(120)->retry(3, 4000)->get(self::URL, [
                'csv.x' => 'yes', 'Datefrom' => '01/Jan/1970', 'Dateto' => 'now', 'SeriesCodes' => $code, 'CSVF' => 'TN', 'UsingCodes' => 'Y',
            ])->throw()->body();
            $lines = preg_split('/\r?\n/', trim($csv)) ?: [];
            if (! isset($lines[0]) || ! str_starts_with($lines[0], 'DATE')) {
                throw new RuntimeException("BoE {$code}: تغيّر شكل الاستجابة.");
            }
            $n = 0;
            foreach (array_slice($lines, 1) as $line) {
                [$date, $value] = array_pad(str_getcsv($line, ',', '"', ''), 2, null);
                $day = $date ? CarbonImmutable::createFromFormat('d M Y', $date) : false;
                if ($day && is_numeric($value)) {
                    $out[] = [$key, $day->format('Y-m-d'), (float) $value];
                    $n++;
                }
            }
            if ($n === 0) {
                throw new RuntimeException("BoE {$code}: لا نقاط.");
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('GBR', 'United Kingdom', 'country', 'المملكة المتحدة');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $day, $value]) {
            yield new ParsedRecord($key, 'GBR', $day, $value);
        }
    }
}
