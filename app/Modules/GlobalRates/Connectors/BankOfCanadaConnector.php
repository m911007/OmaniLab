<?php

namespace App\Modules\GlobalRates\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * كندا من بنك كندا (Valet API العامة): سعر الفائدة المستهدف لليلة واحدة، وعوائد السندات الحكومية (2 و5 و10 سنوات)، وسعر الدولار الكندي
 * مقابل الأمريكي، والتضخم الكلي والأساسي (CPI-trim). كندا مصدّر رئيسي للنفط والغاز فتتأثر بالأسعار كعُمان وتُقارن بها.
 */
final class BankOfCanadaConnector extends ApiConnector
{
    private const URL = 'https://www.bankofcanada.ca/valet/observations/';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: float, 6: float}> رمز Valet => [مفتاح، تردد، وحدة، عربي، إنجليزي، أدنى، أعلى] */
    private const SERIES = [
        'V39079' => ['ca.policy_rate', 'D', '%', 'سعر الفائدة المستهدف لليلة (بنك كندا)', 'Bank of Canada overnight target rate', -1, 30],
        'FXUSDCAD' => ['ca.usdcad', 'D', 'دولار كندي', 'الدولار الكندي لكل دولار أمريكي', 'USD/CAD', 0.5, 3],
        'BD.CDN.2YR.DQ.YLD' => ['ca.yield_2y', 'D', '%', 'عائد السندات الكندية سنتان', 'Canada 2-year yield', -1, 30],
        'BD.CDN.5YR.DQ.YLD' => ['ca.yield_5y', 'D', '%', 'عائد السندات الكندية 5 سنوات', 'Canada 5-year yield', -1, 30],
        'BD.CDN.10YR.DQ.YLD' => ['ca.yield_10y', 'D', '%', 'عائد السندات الكندية 10 سنوات', 'Canada 10-year yield', -1, 30],
        'STATIC_ATABLE_V41690973' => ['ca.cpi_yoy', 'M', '%', 'التضخم السنوي الكلي في كندا', 'Canada total CPI inflation, y/y', -5, 30],
        'CPI_TRIM' => ['ca.cpi_trim', 'M', '%', 'التضخم الأساسي في كندا (CPI-trim)', 'Canada CPI-trim', -5, 30],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as [$key, $freq, $unit, $ar, $en, $lo, $hi]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, $freq, [new Range($lo, $hi, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'ca.bank_of_canada',
            source: new SourceDefinition(key: 'boc', nameAr: 'بنك كندا', nameEn: 'Bank of Canada (Valet API)', publisher: 'Bank of Canada', url: 'https://www.bankofcanada.ca/valet/docs', license: 'Bank of Canada Terms — مع الإشارة إلى المصدر'),
            nameAr: 'كندا: الفائدة والسندات والصرف والتضخم',
            nameEn: 'Canada: policy rate, bonds, FX, inflation',
            section: 'markets',
            intervalMinutes: 12 * 60,
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
        foreach (self::SERIES as $code => [$key, $freq]) {
            if ($i++ > 0) {
                usleep(400_000);
            }
            $res = $this->http()->timeout(120)->retry(3, 3000)->get(self::URL.$code.'/json', ['start_date' => '1990-01-01'])->throw()->json();
            if (! isset($res['observations']) || ! is_array($res['observations'])) {
                throw new RuntimeException("Bank of Canada {$code}: تغيّر شكل الاستجابة.");
            }
            $n = 0;
            foreach ($res['observations'] as $o) {
                $v = $o[$code]['v'] ?? null;
                if (is_numeric($v) && preg_match('/^(\d{4}-\d{2})(-\d{2})?$/', (string) ($o['d'] ?? ''), $m)) {
                    $out[] = [$key, $freq === 'M' ? $m[1] : $o['d'], (float) $v];
                    $n++;
                }
            }
            if ($n === 0) {
                throw new RuntimeException("Bank of Canada {$code}: لا نقاط.");
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('CAN', 'Canada', 'country', 'كندا');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $period, $value]) {
            yield new ParsedRecord($key, 'CAN', $period, $value);
        }
    }
}
