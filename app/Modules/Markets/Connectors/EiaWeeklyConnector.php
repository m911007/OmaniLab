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
 * التقرير الأسبوعي لإدارة معلومات الطاقة الأمريكية (EIA): مخزون النفط الخام والبنزين والمقطّرات والاحتياطي الاستراتيجي، وإنتاج النفط الأمريكي
 * ومعدل تشغيل المصافي والواردات والصادرات والطلب، ومخزون الغاز الطبيعي. من أهم مؤشرات اتجاه سوق النفط على المدى القصير. أسبوعي منذ 1982 (الغاز منذ 2010).
 * الصفحات العامة تعرض صفوف «YYYY-Mon» بأزواج (أسبوع، قيمة)، فيُحلَّل كل زوج إلى تاريخ الأسبوع.
 */
final class EiaWeeklyConnector extends ApiConnector
{
    private const URL = 'https://www.eia.gov/dnav/pet/hist/LeafHandler.ashx';

    private const GAS_URL = 'https://www.eia.gov/dnav/ng/hist/nw2_epg0_swo_r48_bcfw.htm';

    private const MONTHS = ['Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float}> رمز EIA => [مفتاح، عربي، إنجليزي، وحدة، حد أعلى] */
    private const SERIES = [
        'WCESTUS1' => ['eia.us_crude_stocks', 'مخزون النفط الخام التجاري الأمريكي (أسبوعي)', 'US commercial crude oil stocks', 'ألف برميل', 1e6],
        'WCSSTUS1' => ['eia.us_spr_stocks', 'مخزون الاحتياطي الاستراتيجي الأمريكي من النفط (أسبوعي)', 'US Strategic Petroleum Reserve stocks', 'ألف برميل', 1e6],
        'WGTSTUS1' => ['eia.us_gasoline_stocks', 'مخزون البنزين الأمريكي (أسبوعي)', 'US motor gasoline stocks', 'ألف برميل', 1e6],
        'WDISTUS1' => ['eia.us_distillate_stocks', 'مخزون المقطّرات (الديزل) الأمريكي (أسبوعي)', 'US distillate fuel oil stocks', 'ألف برميل', 1e6],
        'WCRFPUS2' => ['eia.us_crude_production', 'إنتاج النفط الخام الأمريكي (أسبوعي)', 'US crude oil production', 'ألف برميل/يوم', 1e5],
        'WPULEUS3' => ['eia.us_refinery_utilization', 'معدل تشغيل المصافي الأمريكية (أسبوعي)', 'US refinery utilisation', '%', 120],
        'WCEIMUS2' => ['eia.us_crude_imports', 'واردات النفط الخام الأمريكية (أسبوعي)', 'US crude oil imports', 'ألف برميل/يوم', 1e5],
        'WCREXUS2' => ['eia.us_crude_exports', 'صادرات النفط الخام الأمريكية (أسبوعي)', 'US crude oil exports', 'ألف برميل/يوم', 1e5],
        'WRPUPUS2' => ['eia.us_product_supplied', 'الطلب الأمريكي على المنتجات النفطية (المتاح للاستهلاك) (أسبوعي)', 'US total petroleum product supplied', 'ألف برميل/يوم', 1e5],
        'WGFUPUS2' => ['eia.us_gasoline_demand', 'الطلب الأمريكي على البنزين (أسبوعي)', 'US finished motor gasoline product supplied', 'ألف برميل/يوم', 1e5],
        'NG_STORAGE' => ['eia.us_gas_storage', 'مخزون الغاز الطبيعي الأمريكي (48 ولاية) (أسبوعي)', 'US natural gas working storage (Lower 48)', 'مليار قدم مكعب', 1e5],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as [$key, $ar, $en, $unit, $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'D', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'eia.weekly',
            source: new SourceDefinition(key: 'eia', nameAr: 'إدارة معلومات الطاقة الأمريكية (EIA)', nameEn: 'U.S. Energy Information Administration', publisher: 'U.S. EIA', url: 'https://www.eia.gov/petroleum/supply/weekly/', license: 'عام (Public domain)'),
            nameAr: 'سوق النفط الأمريكي الأسبوعي: المخزون والإنتاج والطلب',
            nameEn: 'US weekly petroleum status (EIA)',
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
        $body = [];
        $i = 0;
        foreach (self::SERIES as $code => [$key]) {
            if ($i++ > 0) {
                usleep(500_000);
            }
            $request = $this->httpAccepting('text/html')->timeout(90)->retry(3, 3000);
            $html = ($code === 'NG_STORAGE' ? $request->get(self::GAS_URL) : $request->get(self::URL, ['n' => 'PET', 's' => $code, 'f' => 'W']))->throw()->body();
            $rows = $this->parseRows($html);
            if (count($rows) < 200) {
                throw new RuntimeException("EIA أسبوعي {$code}: تغيّر شكل الصفحة أو نقصت البيانات (".count($rows).' أسبوعًا).');
            }
            $body[$key] = $rows;
        }

        return new RawPayload(json_encode($body, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{0: string, 1: float}> */
    private function parseRows(string $html): array
    {
        $out = [];
        preg_match_all('/<tr>(.*?)<\/tr>/s', $html, $rows);
        foreach ($rows[1] as $row) {
            $text = preg_replace('/\s+/', ' ', str_replace('&nbsp;', ' ', strip_tags(str_replace('</td>', '|', $row))));
            if (! preg_match('/^\s*(\d{4})-([A-Z][a-z]{2})\s*\|/', (string) $text, $m) || ! isset(self::MONTHS[$m[2]])) {
                continue;
            }
            preg_match_all('/(\d{2})\/(\d{2})\s*\|(?:\s*\|)?\s*([\d,]+(?:\.\d+)?)\s*\|/', (string) $text, $pairs, PREG_SET_ORDER);
            foreach ($pairs as $p) {
                $out[] = [sprintf('%s-%s-%s', $m[1], $p[1], $p[2]), (float) str_replace(',', '', $p[3])];
            }
        }

        return $out;
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $key => $rows) {
            foreach ($rows as [$date, $value]) {
                yield new ParsedRecord($key, 'USA', $date, $value);
            }
        }
    }
}
