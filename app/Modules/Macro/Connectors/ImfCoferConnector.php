<?php

namespace App\Modules\Macro\Connectors;

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
 * تركيبة العملات في احتياطيات النقد الأجنبي الرسمية عالميًا (IMF COFER، ربع سنوي): الحصة (%) والقيمة (مليار دولار) لكل من الدولار واليورو واليوان والين
 * والإسترليني والدولار الأسترالي والكندي والفرنك السويسري وعملات أخرى. المؤشر الأوضح لمدى هيمنة الدولار، وعُمان (ريالها مربوط بالدولار) من المتأثرين بها.
 * الكيان «العالم» (WLD) لأن المصدر لا يكشف احتياطيات كل دولة على حدة.
 */
final class ImfCoferConnector extends ApiConnector
{
    private const URL = 'https://api.imf.org/external/sdmx/2.1/data/COFER/G001.AFXRA..?startPeriod=1999';

    /** @var array<string, array{0: string, 1: string, 2: string}> رمز العملة => [لاحقة المفتاح، عربي، إنجليزي] */
    private const CURRENCIES = [
        'CI_USD' => ['usd', 'الدولار الأمريكي', 'US dollar'],
        'CI_EUR' => ['eur', 'اليورو', 'Euro'],
        'CI_CNY' => ['cny', 'اليوان الصيني', 'Chinese renminbi'],
        'CI_JPY' => ['jpy', 'الين الياباني', 'Japanese yen'],
        'CI_GBP' => ['gbp', 'الجنيه الإسترليني', 'Pound sterling'],
        'CI_AUD' => ['aud', 'الدولار الأسترالي', 'Australian dollar'],
        'CI_CAD' => ['cad', 'الدولار الكندي', 'Canadian dollar'],
        'CI_CHF' => ['chf', 'الفرنك السويسري', 'Swiss franc'],
        'CI_OTHC' => ['other', 'عملات أخرى', 'Other currencies'],
        'CI_T' => ['total', 'الإجمالي', 'Total'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::CURRENCIES as [$k, $ar, $en]) {
            if ($k !== 'total') {
                $indicators[] = new IndicatorDefinition("imf.cofer_share_{$k}", "حصة {$ar} من الاحتياطيات العالمية", "Share of {$en} in global FX reserves", '%', 'Q', [new Range(0, 100, 'reject')]);
            }
            $indicators[] = new IndicatorDefinition("imf.cofer_value_{$k}", "احتياطيات العالم بـ{$ar}", "Global FX reserves held in {$en}", 'مليار دولار', 'Q', [new Range(0, 20000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'imf.cofer',
            source: new SourceDefinition(key: 'imf', nameAr: 'صندوق النقد الدولي', nameEn: 'International Monetary Fund', publisher: 'IMF', url: 'https://data.imf.org', license: 'IMF Data Terms'),
            nameAr: 'تركيبة العملات في احتياطيات العالم (COFER)',
            nameEn: 'Currency composition of official foreign exchange reserves (COFER)',
            section: 'finance',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $csv = $this->httpAccepting('application/vnd.sdmx.data+csv')->timeout(180)->retry(3, 3000)->get(self::URL)->throw()->body();
        if (! str_starts_with($csv, 'DATAFLOW,')) {
            throw new RuntimeException('IMF COFER: تغيّر شكل الاستجابة.');
        }
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $idx = array_flip($header);
        foreach (['FXR_CURRENCY', 'TYPE_OF_TRANSFORMATION', 'TIME_PERIOD', 'OBS_VALUE'] as $col) {
            if (! isset($idx[$col])) {
                throw new RuntimeException("IMF COFER: العمود {$col} غير موجود.");
            }
        }
        $out = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) === count($header) && isset(self::CURRENCIES[$row[$idx['FXR_CURRENCY']]]) && preg_match('/^\d{4}-Q[1-4]$/', $row[$idx['TIME_PERIOD']]) && is_numeric($row[$idx['OBS_VALUE']])) {
                $out[] = [$row[$idx['FXR_CURRENCY']], $row[$idx['TYPE_OF_TRANSFORMATION']], $row[$idx['TIME_PERIOD']], (float) $row[$idx['OBS_VALUE']]];
            }
        }
        fclose($handle);
        if ($out === []) {
            throw new RuntimeException('IMF COFER: لا نقاط.');
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('WLD', 'World', 'group', 'العالم');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$currency, $transform, $period, $value]) {
            $suffix = self::CURRENCIES[$currency][0];
            if ($transform === 'SHRO_PT' && $suffix !== 'total') {
                yield new ParsedRecord("imf.cofer_share_{$suffix}", 'WLD', $period, $value);
            } elseif ($transform === 'NV_USD') {
                yield new ParsedRecord("imf.cofer_value_{$suffix}", 'WLD', $period, $value / 1e9);
            }
        }
    }
}
