<?php

namespace App\Modules\Bis\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * بنك التسويات الدولية (BIS): أسعار الفائدة الرسمية للبنوك المركزية (49 اقتصادًا منها السعودية والكويت)، وأسعار الصرف الفعلية الاسمية والحقيقية
 * (64 اقتصادًا منها السعودية والإمارات)، وأسعار العقارات السكنية (61 اقتصادًا؛ لا تشمل عُمان ولا الخليج)، والرقم القياسي الطويل لأسعار المستهلك
 * (63 اقتصادًا منها الإمارات والسعودية والكويت منذ الخمسينيات). عُمان غير مغطاة في أغلبها فتُستعمل مرجعًا للمقارنة مع الجيران والعالم.
 * رموز الدول (ISO2) تُحوَّل إلى ISO3 بقائمة البنك الدولي.
 */
final class BisStatisticsConnector extends ApiConnector
{
    private const BASE = 'https://stats.bis.org/api/v2/data/dataflow/BIS/';

    private const WB_COUNTRIES = 'https://api.worldbank.org/v2/country?format=json&per_page=400';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: float, 7: float}> مفتاح => [مجموعة BIS، مفتاح الأبعاد، عربي، إنجليزي، وحدة، تردد، حد أدنى، حد أعلى] */
    private const SERIES = [
        'bis.policy_rate' => ['WS_CBPOL', 'M.', 'سعر الفائدة الرسمي للبنك المركزي', 'Central bank policy rate', '%', 'M', -5, 200],
        'bis.neer_broad' => ['WS_EER', 'M.N.B.', 'سعر الصرف الفعلي الاسمي (سلة واسعة)', 'Nominal effective exchange rate (broad)', 'مؤشر 2020=100', 'M', 0, 5000],
        'bis.reer_broad' => ['WS_EER', 'M.R.B.', 'سعر الصرف الفعلي الحقيقي (سلة واسعة)', 'Real effective exchange rate (broad)', 'مؤشر 2020=100', 'M', 0, 5000],
        'bis.house_price_real' => ['WS_SPP', 'Q..R.628', 'أسعار العقارات السكنية الحقيقية', 'Real residential property prices', 'مؤشر 2010=100', 'Q', 0, 5000],
        'bis.house_price_nominal' => ['WS_SPP', 'Q..N.628', 'أسعار العقارات السكنية الاسمية', 'Nominal residential property prices', 'مؤشر 2010=100', 'Q', 0, 100000],
        'bis.house_price_real_yoy' => ['WS_SPP', 'Q..R.771', 'تغيّر أسعار العقارات الحقيقي (سنويًا)', 'Real property price change, y/y', '%', 'Q', -100, 1000],
        'bis.cpi_long_index' => ['WS_LONG_CPI', 'A..628', 'الرقم القياسي لأسعار المستهلك (سلسلة طويلة)', 'Consumer prices, long series', 'مؤشر 2010=100', 'A', 0, 1e9],
        'bis.inflation_long' => ['WS_LONG_CPI', 'A..771', 'التضخم السنوي (سلسلة طويلة)', 'Inflation, long series', '%', 'A', -100, 1e6],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as $key => [, , $ar, $en, $unit, $freq, $min, $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, $freq, [new Range($min, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'bis.statistics',
            source: new SourceDefinition(key: 'bis', nameAr: 'بنك التسويات الدولية (BIS)', nameEn: 'Bank for International Settlements', publisher: 'BIS', url: 'https://data.bis.org', license: 'BIS Terms of use'),
            nameAr: 'بنك التسويات: الفائدة وأسعار الصرف الفعلية والعقارات',
            nameEn: 'BIS: policy rates, effective exchange rates, property prices',
            section: 'finance',
            intervalMinutes: 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    /** @return array<string, string> ISO2 => ISO3 */
    private function iso3ByIso2(): array
    {
        $res = $this->http()->timeout(60)->get(self::WB_COUNTRIES)->throw()->json();
        $map = [];
        foreach ($res[1] ?? [] as $c) {
            if (($c['region']['value'] ?? '') !== 'Aggregates' && ! empty($c['iso2Code']) && ! empty($c['id'])) {
                $map[(string) $c['iso2Code']] = (string) $c['id'];
            }
        }
        if (count($map) < 150) {
            throw new RuntimeException('BIS: قائمة الدول غير مكتملة.');
        }

        return $map;
    }

    public function fetch(): RawPayload
    {
        $iso = $this->iso3ByIso2();
        $rows = [];
        foreach (self::SERIES as $key => [$flow, $dims]) {
            $csv = $this->httpAccepting('application/vnd.sdmx.data+csv')->timeout(180)->retry(3, 5000)->get(self::BASE."{$flow}/1.0/{$dims}", ['detail' => 'dataonly'])->throw()->body();
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $csv);
            rewind($handle);
            $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
            $area = array_search('REF_AREA', $header, true);
            $period = array_search('TIME_PERIOD', $header, true);
            $value = array_search('OBS_VALUE', $header, true);
            if ($area === false || $period === false || $value === false) {
                throw new RuntimeException("BIS {$flow}: تغيّر شكل الملف.");
            }
            while (($r = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $code = $iso[$r[$area] ?? ''] ?? null;
                if ($code !== null && is_numeric($r[$value] ?? null) && preg_match('/^\d{4}(-\d{2}|-Q[1-4])?$/', (string) ($r[$period] ?? ''))) {
                    $rows[] = [$key, $code, $r[$period], (float) $r[$value]];
                }
            }
            fclose($handle);
            usleep(500_000);
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $iso, $period, $value]) {
            yield new ParsedRecord($key, $iso, $period, $value);
        }
    }
}
