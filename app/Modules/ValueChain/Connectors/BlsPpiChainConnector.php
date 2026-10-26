<?php

namespace App\Modules\ValueChain\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Support\ApiKeys;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * مؤشرات أسعار المنتجين الأمريكية (BLS PPI) لمراحل سلاسل القيمة: خام الحديد والخردة والبليت والقضبان المدرفلة، والإسمنت والركام والخرسانة الجاهزة،
 * والنحاس وأسلاك وكابلات النحاس، والراتنجات وأنابيب البلاستيك، والطاقة الصناعية. شهرية منذ 2007، واجهة v1 العامة بلا مفتاح (استعلامان يوميًا).
 * هي «مؤشرات» (نقطة، أساس متغير) لا أسعار بالريال؛ قيمتها في رصد الاتجاه وفترات التأخر بين المراحل، وكلها من سوق واحد فتتسق للانحدار.
 */
final class BlsPpiChainConnector extends ApiConnector
{
    private const URL = 'https://api.bls.gov/publicAPI/v1/timeseries/data/';

    private const FIRST_YEAR = 2007;

    /** @var array<string, array{0: string, 1: string}> معرّف BLS => [عربي، إنجليزي] (أسماء BLS الرسمية من wp.item) */
    public const SERIES = [
        'WPU1011' => ['خامات الحديد', 'Iron ores'],
        'WPU1012' => ['خردة الحديد والصلب', 'Iron and steel scrap'],
        'WPU101211' => ['خردة الصلب الكربوني', 'Carbon steel scrap'],
        'WPU101702' => ['منتجات صلب نصف مصنعة (بليت وكتل)', 'Semifinished steel mill products'],
        'WPU101704' => ['قضبان وألواح ومقاطع صلب مدرفلة على الساخن', 'Hot rolled steel bars, plates, and structural shapes'],
        'WPU1074051' => ['حديد التسليح والجسور المصنعة', 'Fabricated structural metal bar joists and concrete reinforcing bars'],
        'WPU1017' => ['منتجات مصانع الصلب', 'Steel mill products'],
        'WPU101712' => ['السبائك الحديدية', 'Electrometallurgical ferroalloy products'],
        'WPU1321' => ['رمل وحصى وحجر مكسر للبناء', 'Construction sand, gravel and crushed stone'],
        'WPU13210122' => ['حجر جيري مكسر', 'Crushed and broken limestone'],
        'WPU1322' => ['الإسمنت الهيدروليكي', 'Cement, hydraulic'],
        'WPU1333' => ['الخرسانة الجاهزة', 'Ready-mix concrete'],
        'WPU102301' => ['خردة النحاس', 'Copper base scrap'],
        'WPU102502' => ['مشغولات النحاس والنحاس الأصفر', 'Copper and brass mill shapes'],
        'WPU10260314' => ['أسلاك وكابلات النحاس', 'Copper wire and cable'],
        'WPU10260332' => ['أسلاك وكابلات القدرة', 'Power wire and cable'],
        'WPU102501' => ['مشغولات الألمنيوم', 'Aluminum mill shapes'],
        'WPU066212' => ['الراتنجات البلاستيكية الحرارية', 'Thermoplastic resins and plastics materials'],
        'WPU07210603' => ['أنابيب البلاستيك', 'Plastics pipe'],
        'WPU0543' => ['الكهرباء الصناعية', 'Industrial electric power'],
        'WPU0553' => ['الغاز الطبيعي الصناعي', 'Industrial natural gas'],
        'WPU057303' => ['الديزل رقم 2', 'No. 2 diesel fuel'],
        'WPU051' => ['الفحم', 'Coal'],
    ];

    public static function key(string $id): string
    {
        return 'bls.ppi.'.strtolower($id);
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as $id => [$ar, $en]) {
            $indicators[] = new IndicatorDefinition(self::key($id), "مؤشر أسعار المنتجين الأمريكي: {$ar}", "US PPI: {$en}", 'مؤشر', 'M', [new Range(1, 5000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'bls.ppi_chains',
            source: new SourceDefinition(key: 'bls', nameAr: 'مكتب إحصاءات العمل الأمريكي', nameEn: 'US Bureau of Labor Statistics', publisher: 'BLS', url: 'https://www.bls.gov/ppi/', license: 'ملكية عامة (حكومة أمريكية)'),
            nameAr: 'مؤشرات أسعار المنتجين لمراحل سلاسل القيمة (BLS)',
            nameEn: 'US PPI for value-chain stages (BLS)',
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
            $res = $this->http()->timeout(60)->retry(3, 5000)->asJson()->post(ApiKeys::blsUrl(), ApiKeys::blsBody([
                'seriesid' => array_keys(self::SERIES), 'startyear' => (string) $start, 'endyear' => (string) min($start + 9, $thisYear),
            ]))->throw()->json();
            if (($res['status'] ?? '') !== 'REQUEST_SUCCEEDED') {
                throw new RuntimeException('BLS: '.json_encode($res['message'] ?? $res, JSON_UNESCAPED_UNICODE));
            }
            foreach ($res['Results']['series'] ?? [] as $s) {
                foreach ($s['data'] ?? [] as $d) {
                    if (preg_match('/^M(0[1-9]|1[0-2])$/', (string) ($d['period'] ?? '')) && is_numeric($d['value'] ?? null)) {
                        $out[] = [$s['seriesID'], $d['year'].'-'.substr($d['period'], 1), (float) $d['value']];
                    }
                }
            }
        }
        if ($out === []) {
            throw new RuntimeException('BLS PPI: لا بيانات.');
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$id, $period, $value]) {
            if (isset(self::SERIES[$id])) {
                yield new ParsedRecord(self::key($id), 'USA', $period, $value);
            }
        }
    }
}
