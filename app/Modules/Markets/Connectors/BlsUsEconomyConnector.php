<?php

namespace App\Modules\Markets\Connectors;

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
 * أهم مؤشرات الاقتصاد الأمريكي من مكتب إحصاءات العمل (BLS): أسعار المستهلك (عام وأساسي)، والبطالة والمشاركة والوظائف غير الزراعية
 * ومتوسط الأجر بالساعة وأسعار المنتجين. واجهة v1 العامة بلا مفتاح (25 استعلامًا يوميًا)، فنجلب التاريخ منذ 1970 بنوافذ من 10 سنوات (6 استعلامات) مرة كل 24 ساعة.
 * التضخم السنوي مشتق: تغيّر الرقم القياسي عن الشهر نفسه قبل سنة.
 */
final class BlsUsEconomyConnector extends ApiConnector
{
    private const URL = 'https://api.bls.gov/publicAPI/v1/timeseries/data/';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> معرّف السلسلة => [مفتاح، عربي، إنجليزي، وحدة] */
    private const SERIES = [
        'CUUR0000SA0' => ['us.cpi_index', 'الرقم القياسي لأسعار المستهلك الأمريكي (CPI-U)', 'US CPI-U (not seasonally adjusted)', 'مؤشر 1982-84=100'],
        'CUSR0000SA0L1E' => ['us.core_cpi_index', 'الرقم القياسي الأساسي (دون الغذاء والطاقة)', 'US core CPI (seasonally adjusted)', 'مؤشر 1982-84=100'],
        'LNS14000000' => ['us.unemployment_rate', 'معدل البطالة الأمريكي', 'US unemployment rate', '%'],
        'LNS11300000' => ['us.participation_rate', 'معدل المشاركة في القوى العاملة الأمريكية', 'US labour force participation rate', '%'],
        'CES0000000001' => ['us.nonfarm_payrolls', 'الوظائف غير الزراعية', 'US nonfarm payrolls', 'ألف وظيفة'],
        'CES0500000003' => ['us.avg_hourly_earnings', 'متوسط الأجر بالساعة (القطاع الخاص)', 'US average hourly earnings, private', 'دولار'],
        'WPSFD4' => ['us.ppi_final_demand', 'مؤشر أسعار المنتجين (الطلب النهائي)', 'US PPI, final demand', 'مؤشر'],
        'CUUR0000SAF1' => ['us.cpi_food', 'أسعار الغذاء (CPI)', 'US CPI: food', 'مؤشر 1982-84=100'],
        'CUUR0000SA0E' => ['us.cpi_energy', 'أسعار الطاقة (CPI)', 'US CPI: energy', 'مؤشر 1982-84=100'],
        'CUUR0000SAH1' => ['us.cpi_shelter', 'أسعار السكن (CPI)', 'US CPI: shelter', 'مؤشر 1982-84=100'],
        'CUUR0000SEHA' => ['us.cpi_rent', 'الإيجارات (CPI)', 'US CPI: rent of primary residence', 'مؤشر 1982-84=100'],
        'CUUR0000SETB01' => ['us.cpi_gasoline', 'أسعار البنزين (CPI)', 'US CPI: gasoline', 'مؤشر 1982-84=100'],
        'CUUR0000SEHF01' => ['us.cpi_electricity', 'أسعار الكهرباء (CPI)', 'US CPI: electricity', 'مؤشر 1982-84=100'],
        'CUUR0000SAM' => ['us.cpi_medical', 'الرعاية الطبية (CPI)', 'US CPI: medical care', 'مؤشر 1982-84=100'],
        'CUUR0000SAT' => ['us.cpi_transport', 'النقل (CPI)', 'US CPI: transportation', 'مؤشر 1982-84=100'],
        'CUUR0000SETA01' => ['us.cpi_new_vehicles', 'السيارات الجديدة (CPI)', 'US CPI: new vehicles', 'مؤشر 1982-84=100'],
        'CUUR0000SETA02' => ['us.cpi_used_vehicles', 'السيارات المستعملة (CPI)', 'US CPI: used vehicles', 'مؤشر 1982-84=100'],
        'LNS12300000' => ['us.employment_population', 'نسبة التوظيف إلى السكان', 'US employment-population ratio', '%'],
        'LNS13327709' => ['us.u6_rate', 'البطالة الموسّعة U-6', 'US U-6 underutilisation rate', '%'],
        'CES0500000002' => ['us.avg_weekly_hours', 'متوسط ساعات العمل الأسبوعية (القطاع الخاص)', 'US average weekly hours, private', 'ساعة'],
        'CES3000000001' => ['us.manufacturing_jobs', 'وظائف التصنيع', 'US manufacturing employment', 'ألف وظيفة'],
        'CES9000000001' => ['us.government_jobs', 'وظائف القطاع الحكومي', 'US government employment', 'ألف وظيفة'],
        'JTS000000000000000JOL' => ['us.job_openings', 'الوظائف الشاغرة (JOLTS)', 'US job openings (JOLTS)', 'ألف وظيفة'],
        'EIUIR' => ['us.import_prices', 'مؤشر أسعار الواردات', 'US import price index', 'مؤشر'],
        'WPU00000000' => ['us.ppi_all_commodities', 'مؤشر أسعار المنتجين (كل السلع)', 'US PPI, all commodities', 'مؤشر'],
    ];

    /** @var array<string, string> سلاسل CPI الفرعية التي يُشتق لها تضخم سنوي */
    private const YOY_EXTRA = [
        'CUUR0000SAF1' => 'us.cpi_food',
        'CUUR0000SA0E' => 'us.cpi_energy',
        'CUUR0000SAH1' => 'us.cpi_shelter',
        'CUUR0000SEHA' => 'us.cpi_rent',
        'CUUR0000SETB01' => 'us.cpi_gasoline',
        'CUUR0000SEHF01' => 'us.cpi_electricity',
        'CUUR0000SAM' => 'us.cpi_medical',
        'CUUR0000SAT' => 'us.cpi_transport',
        'CUUR0000SETA01' => 'us.cpi_new_vehicles',
        'CUUR0000SETA02' => 'us.cpi_used_vehicles',
    ];

    /** أول سنة تُجلب (v1 تعطي 10 سنوات لكل استعلام فتُقسَّم النوافذ). */
    private const FIRST_YEAR = 1970;

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as [$key, $ar, $en, $unit]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'M', [new Range(0, 1e6, 'reject')]);
        }
        $indicators[] = new IndicatorDefinition('us.cpi_yoy', 'التضخم السنوي الأمريكي (CPI)', 'US CPI inflation, y/y', '%', 'M', [new Range(-20, 50, 'reject')]);
        foreach (self::YOY_EXTRA as $id => $key) {
            [, $ar, $en] = self::SERIES[$id];
            $indicators[] = new IndicatorDefinition("{$key}_yoy", "التضخم السنوي: {$ar}", "Inflation y/y: {$en}", '%', 'M', [new Range(-80, 300, 'reject')]);
        }
        $indicators[] = new IndicatorDefinition('us.core_cpi_yoy', 'التضخم الأساسي السنوي الأمريكي', 'US core CPI inflation, y/y', '%', 'M', [new Range(-20, 50, 'reject')]);

        return new DatasetDefinition(
            key: 'us.bls_economy',
            source: new SourceDefinition(key: 'bls', nameAr: 'مكتب إحصاءات العمل الأمريكي (BLS)', nameEn: 'U.S. Bureau of Labor Statistics', publisher: 'BLS', url: 'https://www.bls.gov/data/', license: 'عام (Public domain)'),
            nameAr: 'الاقتصاد الأمريكي: الأسعار والتوظيف والأجور',
            nameEn: 'US economy: prices, jobs, wages',
            section: 'markets',
            intervalMinutes: 24 * 60,
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
        $thisYear = (int) date('Y');
        for ($start = self::FIRST_YEAR; $start <= $thisYear; $start += 10) {
            if ($start > self::FIRST_YEAR) {
                sleep(1);
            }
            $res = $this->http()->timeout(60)->retry(3, 5000)->asJson()->post(self::URL, [
                'seriesid' => array_keys(self::SERIES), 'startyear' => (string) $start, 'endyear' => (string) min($start + 9, $thisYear),
            ])->throw()->json();
            if (($res['status'] ?? '') !== 'REQUEST_SUCCEEDED') {
                throw new RuntimeException('BLS: '.json_encode($res['message'] ?? $res, JSON_UNESCAPED_UNICODE));
            }
            foreach ($res['Results']['series'] ?? [] as $s) {
                foreach ($s['data'] as $d) {
                    if (preg_match('/^M(0[1-9]|1[0-2])$/', (string) ($d['period'] ?? '')) && is_numeric($d['value'] ?? null)) {
                        $out[] = [$s['seriesID'], $d['year'].'-'.substr($d['period'], 1), (float) $d['value']];
                    }
                }
            }
        }
        if ($out === []) {
            throw new RuntimeException('BLS: لا بيانات.');
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');
        $index = [];
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$id, $period, $value]) {
            if (isset(self::SERIES[$id])) {
                yield new ParsedRecord(self::SERIES[$id][0], 'USA', $period, $value);
                $index[$id][$period] = $value;
            }
        }
        foreach (['CUUR0000SA0' => 'us.cpi_yoy', 'CUSR0000SA0L1E' => 'us.core_cpi_yoy'] + array_map(fn ($k) => $k.'_yoy', self::YOY_EXTRA) as $id => $key) {
            foreach ($index[$id] ?? [] as $period => $value) {
                $prev = $index[$id][(((int) substr($period, 0, 4)) - 1).substr($period, 4)] ?? null;
                if ($prev) {
                    yield new ParsedRecord($key, 'USA', $period, round(($value / $prev - 1) * 100, 2));
                }
            }
        }
    }
}
