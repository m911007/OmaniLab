<?php

namespace App\Modules\ValueChain;

use App\Modules\Prices\DbCurrencyConverter;
use Illuminate\Support\Facades\DB;

/**
 * المقارنة المعيارية لسعر مورد تدفعه الجهة:
 *   1) نطاق السوق: الأسعار المرجعية المتاحة للعقدة (قيم جمركية واردة لعُمان والخليج، أسعار تصدير الصين والهند وتركيا، عقود آجلة، أسعار مرجعية)
 *      محوّلة إلى ر.ع لكل وحدة العقدة ومرفوعة إلى «سعر واصل» تقديري (شحن ورسوم: افتراضات قابلة للتعديل وموسومة).
 *   2) التكلفة المتوقعة (Should-cost) بمعادلة تعديل الأسعار: سعر العقد × [Σ وزن المدخل × (سعره الآن ÷ سعره عند التعاقد) + الحصة الثابتة].
 *   3) سلوك المورد: هل ينقل ارتفاع المدخلات أكثر من انخفاضها (انتقال غير متماثل).
 * كل نتيجة تحمل مدخلاتها (القيم والفترات والمصادر) كي تُعاد وتُدقَّق.
 */
final class Benchmark
{
    public function __construct(private SeriesReader $reader, private DbCurrencyConverter $fx) {}

    /**
     * @param  object  $price  صف من app.supplier_prices
     * @param  array{freight_pct: float, duty_pct: float}  $assume
     * @return array<string, mixed>
     */
    public function evaluate(object $price, array $assume = ['freight_pct' => 10.0, 'duty_pct' => 5.0]): array
    {
        $node = $price->node_id ? DB::table('intel.chain_nodes')->where('id', $price->node_id)->first() : null;
        $paid = $price->price_omr_per_unit !== null ? (float) $price->price_omr_per_unit : null;
        $out = ['price_id' => $price->id, 'paid_omr_per_unit' => $paid, 'unit' => $price->unit, 'node' => $node ? ['id' => $node->id, 'name_ar' => $node->name_ar, 'unit' => $node->unit] : null, 'assumptions' => $assume];
        if (! $node || $paid === null) {
            return $out + ['status' => 'unlinked', 'message' => $node ? 'تعذّر تحويل العملة.' : 'اربط السعر بعقدة في سلسلة قيمة لتجري المقارنة.'];
        }
        $toNodeUnit = Units::factor($node->unit, $price->unit); // كم وحدة سعر في وحدة العقدة
        $paidPerNodeUnit = $toNodeUnit ? $paid * $toNodeUnit : null;

        $refs = $this->references((int) $node->id, (string) $node->unit, $assume);
        $vals = array_column($refs, 'landed_omr');
        sort($vals);
        $range = $vals === [] ? null : ['min' => $vals[0], 'median' => $vals[intdiv(count($vals), 2)], 'max' => end($vals), 'n' => count($vals)];
        $position = null;
        if ($range && $paidPerNodeUnit !== null) {
            $gap = ($paidPerNodeUnit - $range['median']) / $range['median'];
            $position = ['gap_vs_median' => round($gap, 4), 'label' => $paidPerNodeUnit < $range['min'] ? 'أدنى من نطاق السوق' : ($paidPerNodeUnit > $range['max'] ? 'أعلى من نطاق السوق' : ($gap > 0.05 ? 'أعلى من الوسيط' : ($gap < -0.05 ? 'أدنى من الوسيط' : 'قريب من الوسيط')))];
        }
        $should = $this->shouldCost((int) $node->id, (string) $price->price_date, $paid);
        $qtyNode = ($price->quantity !== null && $toNodeUnit) ? (float) $price->quantity / $toNodeUnit : null;
        $savings = null;
        if ($range && $paidPerNodeUnit !== null && $qtyNode && $paidPerNodeUnit > $range['median']) {
            $savings = round(($paidPerNodeUnit - $range['median']) * $qtyNode, 3);
        }

        return $out + ['status' => 'ok', 'paid_omr_per_node_unit' => $paidPerNodeUnit, 'unit_compatible' => $toNodeUnit !== null, 'references' => $refs, 'range' => $range, 'position' => $position,
            'should_cost' => $should, 'savings_vs_median_omr' => $savings, 'computed_at' => now()->toIso8601String()];
    }

    /** @return list<array<string, mixed>> */
    public function references(int $nodeId, string $nodeUnit, array $assume): array
    {
        $links = DB::table('intel.node_series as ns')->join('intel.price_series as s', 's.id', '=', 'ns.series_id')->where('ns.node_id', $nodeId)
            ->whereIn('s.price_type', ['reference', 'customs_unit_value', 'futures', 'supplier', 'asking'])->get(['s.*']);
        $out = [];
        foreach ($links as $s) {
            $pu = Units::parsePriceUnit($s->unit);
            $latest = $this->reader->latest($s->indicator_key, $s->entity_code);
            if (! $pu || ! $latest) {
                continue;
            }
            $perUnit = Units::factor($nodeUnit, $pu[1]);
            $omr = $this->fx->toOmr($latest['value'], $pu[0]);
            if ($perUnit === null || $omr === null) {
                continue;
            }
            $base = $omr * $perUnit;
            $isOmanCif = $s->incoterm === 'CIF' && $s->entity_code === 'OMN';
            $freight = in_array($s->incoterm, ['FOB', 'EXW', 'FCA'], true) || $s->price_type === 'futures' || $s->price_type === 'reference' ? $assume['freight_pct'] : 0.0;
            $landed = $base * (1 + $freight / 100) * (1 + $assume['duty_pct'] / 100);
            $out[] = ['series' => "{$s->indicator_key}|{$s->entity_code}", 'label_ar' => $s->label_ar, 'price_type' => $s->price_type, 'confidence' => $s->confidence, 'market' => $s->market,
                'incoterm' => $s->incoterm, 'period' => $latest['period'], 'source' => $latest['dataset'], 'value' => $latest['value'], 'unit' => $s->unit, 'base_omr' => round($base, 3),
                'freight_assumed_pct' => $freight, 'landed_omr' => round($landed, 3), 'basis' => $isOmanCif ? 'قيمة جمركية CIF لعُمان + الرسم الجمركي' : ($freight > 0 ? 'سعر منشأ + شحن تقديري + رسم جمركي (افتراض)' : 'قيمة جمركية CIF + رسم جمركي')];
        }

        return $out;
    }

    /**
     * معادلة تعديل السعر (Escalation): سعر التعاقد مضروبًا في تغيّر تكلفة المدخلات منذ تاريخ التعاقد بحسب أوزانها.
     *
     * @return array<string, mixed>|null
     */
    public function shouldCost(int $nodeId, string $date, float $paid): ?array
    {
        $edges = DB::table('intel.chain_edges as e')->join('intel.chain_nodes as n', 'n.id', '=', 'e.from_node_id')->where('e.to_node_id', $nodeId)->whereNotNull('e.weight')->get(['e.weight', 'n.id', 'n.name_ar', 'n.kind']);
        if ($edges->isEmpty()) {
            return null;
        }
        $base = substr($date, 0, 7);
        $parts = [];
        $covered = 0.0;
        $factor = 0.0;
        foreach ($edges as $e) {
            $series = $e->kind === 'conversion' ? null : (new ChainAnalytics($this->reader))->primaryMonthly((int) $e->id);
            if (! $series) {
                continue;
            }
            $before = array_filter($series['points'], fn ($p) => $p <= $base, ARRAY_FILTER_USE_KEY);
            if ($before === []) {
                continue;
            }
            $p0 = end($before);
            $p0Period = array_key_last($before);
            $p1 = end($series['points']);
            $p1Period = array_key_last($series['points']);
            $ratio = $p0 > 0 ? $p1 / $p0 : 1.0;
            $w = (float) $e->weight;
            $covered += $w;
            $factor += $w * $ratio;
            $parts[] = ['input' => $e->name_ar, 'weight' => $w, 'series' => $series['key'], 'from' => $p0Period, 'to' => $p1Period, 'change' => round($ratio - 1, 4)];
        }
        if ($parts === []) {
            return null;
        }
        $factor += 1 - $covered; // الحصة غير المغطاة بسلسلة سعرية تُعامل ثابتة

        return ['method' => 'escalation', 'base_period' => $base, 'factor' => round($factor, 4), 'should_cost_omr' => round($paid * $factor, 3), 'covered_weight' => round($covered, 3), 'inputs' => $parts,
            'note' => 'سعر التعاقد × تغيّر تكلفة المدخلات منذ التعاقد بحسب أوزانها؛ الحصة غير المغطاة ثابتة. يفترض انتقالًا كاملًا للتكلفة.'];
    }

    /**
     * انتقال غير متماثل: لكل زوج أسعار متتالٍ لنفس المورد والمنتج، نقارن تغيّر سعر المورد بالتغير المتوقع من المدخلات.
     *
     * @param  list<object>  $prices  أسعار مرتبة بالتاريخ لنفس (المورد، المنتج، العقدة)
     * @return array<string, mixed>|null
     */
    public function passThrough(array $prices): ?array
    {
        if (count($prices) < 3 || ! $prices[0]->node_id) {
            return null;
        }
        $up = $down = [];
        for ($i = 1; $i < count($prices); $i++) {
            [$a, $b] = [$prices[$i - 1], $prices[$i]];
            if (! $a->price_omr_per_unit || ! $b->price_omr_per_unit) {
                continue;
            }
            $sc = $this->shouldCost((int) $a->node_id, (string) $a->price_date, (float) $a->price_omr_per_unit);
            $bc = $this->shouldCost((int) $a->node_id, (string) $b->price_date, 1.0);
            if (! $sc || ! $bc) {
                continue;
            }
            // التغير المتوقع بين التاريخين = (معامل حتى الآن من a) ÷ (معامل حتى الآن من b)
            $expected = $sc['factor'] / max(1e-9, $bc['factor']) - 1;
            $actual = (float) $b->price_omr_per_unit / (float) $a->price_omr_per_unit - 1;
            if (abs($expected) < 0.01) {
                continue;
            }
            $ratio = $actual / $expected;
            if ($expected > 0) {
                $up[] = $ratio;
            } else {
                $down[] = $ratio;
            }
        }
        if ($up === [] || $down === []) {
            return ['verdict' => 'insufficient', 'label' => 'لا تكفي الأسعار لقياس سلوك المورد (يلزم ارتفاع وانخفاض في المدخلات خلال فترة التعامل).', 'up' => $up, 'down' => $down];
        }
        $mu = array_sum($up) / count($up);
        $md = array_sum($down) / count($down);
        $asym = $mu - $md > 0.3;

        return ['verdict' => $asym ? 'asymmetric' : 'symmetric', 'label' => $asym ? 'المورد ينقل ارتفاع المدخلات إلى السعر أكثر مما ينقل انخفاضها' : 'نقل المورد للارتفاع والانخفاض متقارب',
            'pass_through_up' => round($mu, 2), 'pass_through_down' => round($md, 2), 'episodes_up' => count($up), 'episodes_down' => count($down)];
    }
}
