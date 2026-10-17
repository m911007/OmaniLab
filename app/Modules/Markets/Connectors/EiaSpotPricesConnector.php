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
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * الأسعار الفورية اليومية لإدارة معلومات الطاقة الأمريكية (EIA): برنت وغرب تكساس وهنري هب، المصدر الأصلي الذي تنقل عنه خدمات أخرى.
 * الصفحة العامة تعرض الأسعار أسبوعًا لكل صف (خمسة أيام عمل)، فتُحلَّل وتُنسب كل قيمة إلى يومها. اليومي من بداية السنة الماضية،
 * والمتوسطات الشهرية (للأشهر الكاملة) لكامل التاريخ لتُطابَق مع صندوق النقد والبنك الدولي.
 */
final class EiaSpotPricesConnector extends ApiConnector
{
    private const URL = 'https://www.eia.gov/dnav/pet/hist/LeafHandler.ashx';

    private const GAS_PAGE = 'https://www.eia.gov/dnav/ng/hist/rngwhhdd.htm';

    private const MONTHS = ['Jan' => 1, 'Feb' => 2, 'Mar' => 3, 'Apr' => 4, 'May' => 5, 'Jun' => 6, 'Jul' => 7, 'Aug' => 8, 'Sep' => 9, 'Oct' => 10, 'Nov' => 11, 'Dec' => 12];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: int|float}> رمز EIA => [مفتاح الأساس، عربي، إنجليزي، وحدة، مدى، حد أعلى] */
    private const SERIES = [
        'RBRTE' => ['eia.brent', 'خام برنت', 'Brent crude', 'دولار/برميل', 'oil', 500],
        'RWTC' => ['eia.wti', 'خام غرب تكساس', 'WTI crude', 'دولار/برميل', 'oil', 500],
        'RNGWHHD' => ['eia.henryhub', 'غاز هنري هب', 'Henry Hub natural gas', 'دولار/مليون وحدة حرارية', 'gas', 100],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as [$base, $ar, $en, $unit, , $max]) {
            $indicators[] = new IndicatorDefinition($base.'_d', $ar.' (يومي)', $en.' (daily)', $unit, 'D', [new Range(-50, $max, 'reject')]);
            $indicators[] = new IndicatorDefinition($base.'_m', $ar.' (متوسط شهري)', $en.' (monthly average)', $unit, 'M', [new Range(-50, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'eia.spot_prices',
            source: new SourceDefinition(
                key: 'eia',
                nameAr: 'إدارة معلومات الطاقة الأمريكية (EIA)',
                nameEn: 'U.S. Energy Information Administration',
                publisher: 'U.S. EIA',
                url: 'https://www.eia.gov/dnav/pet/pet_pri_spt_s1_d.htm',
                license: 'عام (Public domain)',
            ),
            nameAr: 'الأسعار الفورية اليومية للنفط والغاز (EIA)',
            nameEn: 'Daily spot prices for oil and gas (EIA)',
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
        foreach (self::SERIES as $code => [$base]) {
            // هنري هب في قسم الغاز بصفحة ثابتة، أما النفط ففي LeafHandler.
            $request = $this->httpAccepting('text/html')->timeout(90);
            $html = ($code === 'RNGWHHD' ? $request->get(self::GAS_PAGE) : $request->get(self::URL, ['n' => 'PET', 's' => $code, 'f' => 'D']))->throw()->body();
            $rows = $this->parseWeeks($html);
            if (count($rows) < 1000) {
                throw new RuntimeException("EIA {$code}: تغيّر شكل الصفحة أو نقصت البيانات (".count($rows).' يومًا).');
            }
            $body[$base] = $rows;
            usleep(500_000);
        }

        return new RawPayload(json_encode($body, JSON_THROW_ON_ERROR));
    }

    /**
     * صفوف الأسابيع «2026 Oct- 5 to Oct- 9» بخمس قيم (الاثنين..الجمعة) → [[تاريخ، قيمة]].
     *
     * @return list<array{0: string, 1: float}>
     */
    private function parseWeeks(string $html): array
    {
        $out = [];
        preg_match_all('/<tr>(.*?)<\/tr>/s', $html, $rows);
        foreach ($rows[1] as $row) {
            $text = preg_replace('/\s+/', ' ', str_replace('&nbsp;', ' ', strip_tags(str_replace('</td>', '|', $row))));
            if (! preg_match('/(\d{4}) ([A-Z][a-z]{2})- ?(\d{1,2}) to /', (string) $text, $m) || ! isset(self::MONTHS[$m[2]])) {
                continue;
            }
            $start = CarbonImmutable::create((int) $m[1], self::MONTHS[$m[2]], (int) $m[3]);
            $monday = $start->subDays($start->dayOfWeekIso - 1);
            $cells = array_map('trim', explode('|', (string) preg_replace('/^.*? to [A-Z][a-z]{2}- ?\d{1,2}\s*\|/', '', (string) $text)));
            foreach (array_slice($cells, 0, 5) as $i => $cell) {
                if (is_numeric($cell)) {
                    $out[] = [$monday->addDays($i)->toDateString(), (float) $cell];
                }
            }
        }

        return $out;
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('WLD', 'World', 'group', 'العالم');

        $windowStart = ((int) date('Y') - 1).'-01-01';
        $thisMonth = date('Y-m');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $base => $rows) {
            $months = [];
            foreach ($rows as [$date, $value]) {
                if ($date >= $windowStart) {
                    yield new ParsedRecord($base.'_d', 'WLD', $date, $value);
                }
                $months[substr($date, 0, 7)][] = $value;
            }
            foreach ($months as $month => $values) {
                if ($month < $thisMonth) {
                    yield new ParsedRecord($base.'_m', 'WLD', $month, array_sum($values) / count($values));
                }
            }
        }
    }
}
