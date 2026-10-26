<?php

namespace App\Modules\ValueChain;

use Illuminate\Support\Facades\DB;

/**
 * تحليلات مستكشف السلسلة: لكل عقدة تقلبها السعري وتركّز الدول المصدّرة ومؤشر مخاطر مركّب شفاف المكونات،
 * وتحليل التأخر الزمني بين كل مدخل والمنتج النهائي بالارتباط المتأخر (Cross-correlation) للتغيرات الشهرية (0–12 شهرًا).
 */
final class ChainAnalytics
{
    private const MAX_LAG = 12;

    public function __construct(private SeriesReader $reader) {}

    /** @return array<string, mixed> */
    public function analyse(int $chainId): array
    {
        $nodes = DB::table('intel.chain_nodes')->where('chain_id', $chainId)->get()->keyBy('id');
        $trade = DB::table('intel.node_trade')->whereIn('node_id', $nodes->keys())->get()->keyBy('node_id');
        $final = $nodes->first(fn ($n) => $n->kind === 'final');
        $monthly = [];
        $out = [];
        foreach ($nodes as $id => $n) {
            $series = $this->primaryMonthly((int) $id);
            $monthly[$id] = $series;
            $vol = $series ? $this->volatility($series['points']) : null;
            $t = $trade[$id] ?? null;
            $components = [];
            if ($t) {
                $components['concentration'] = ['label' => 'تركّز الدول المصدّرة (HHI)', 'value' => (float) $t->hhi, 'score' => round(min(100, (float) $t->hhi / 100), 1)];
            }
            if ($vol !== null) {
                $components['volatility'] = ['label' => 'التقلب السعري السنوي', 'value' => round($vol, 4), 'score' => round(min(100, $vol / 0.6 * 100), 1)];
            }
            $out[$id] = [
                'node_id' => $id,
                'series' => $series ? ['key' => $series['key'], 'label_ar' => $series['label_ar'], 'months' => count($series['points'])] : null,
                'volatility' => $vol,
                'trade' => $t ? ['hs4' => $t->hs4, 'year' => (int) $t->year, 'total_usd' => (float) $t->total_usd, 'hhi' => (float) $t->hhi, 'exporters' => json_decode($t->exporters, true), 'computed_at' => $t->computed_at] : null,
                'risk' => $components === [] ? null : ['score' => round(array_sum(array_column($components, 'score')) / count($components), 1), 'components' => $components,
                    'note' => 'متوسط المكونات المتاحة؛ العقوبات والاضطرابات تُضاف في مرحلة ذكاء الموردين.'],
                'lag' => null,
            ];
        }
        if ($final && ($monthly[$final->id] ?? null)) {
            $fy = $this->dlog($monthly[$final->id]['points']);
            foreach ($nodes as $id => $n) {
                if ($id === $final->id || ! $monthly[$id] || $n->kind === 'conversion') {
                    continue;
                }
                $out[$id]['lag'] = $this->crossCorrelation($fy, $this->dlog($monthly[$id]['points']));
            }
        }

        return ['final_node_id' => $final?->id, 'final_series' => $final && $monthly[$final->id] ? $monthly[$final->id]['key'] : null, 'nodes' => array_values($out)];
    }

    /** @return array{key: string, label_ar: string, points: array<string, float>}|null أول سلسلة للعقدة بها 24 شهرًا فأكثر (اليومية تُختزل لآخر قيمة في الشهر) */
    public function primaryMonthly(int $nodeId): ?array
    {
        $links = DB::table('intel.node_series as ns')->join('intel.price_series as s', 's.id', '=', 'ns.series_id')
            ->where('ns.node_id', $nodeId)->where('s.enabled', true)->whereIn('s.frequency', ['M', 'D', 'W'])
            ->orderByRaw("CASE ns.role WHEN 'primary' THEN 0 WHEN 'alternative' THEN 1 ELSE 2 END")->orderBy('ns.priority')
            ->get(['s.indicator_key', 's.entity_code', 's.label_ar', 's.frequency']);
        foreach ($links as $l) {
            $pts = $this->reader->points($l->indicator_key, $l->entity_code, (string) (date('Y') - 10));
            if ($l->frequency !== 'M') {
                $m = [];
                foreach ($pts as $p => $v) {
                    $m[substr($p, 0, 7)] = $v;
                }
                $pts = $m;
            }
            if (count($pts) >= 24) {
                return ['key' => "{$l->indicator_key}|{$l->entity_code}", 'label_ar' => $l->label_ar, 'points' => $pts];
            }
        }

        return null;
    }

    /** @param  array<string, float>  $points الانحراف المعياري السنوي للتغير الشهري اللوغاريتمي (آخر 36 شهرًا) */
    public function volatility(array $points): ?float
    {
        $d = array_slice(array_values($this->dlog($points)), -36);
        if (count($d) < 12) {
            return null;
        }
        $m = array_sum($d) / count($d);
        $var = array_sum(array_map(fn ($x) => ($x - $m) ** 2, $d)) / (count($d) - 1);

        return sqrt($var * 12);
    }

    /**
     * @param  array<string, float>  $dy  تغيرات المنتج النهائي
     * @param  array<string, float>  $dx  تغيرات المدخل
     * @return array{best_lag: int, best_corr: float, n: int, by_lag: list<array{lag: int, corr: float, n: int}>}|null
     */
    public function crossCorrelation(array $dy, array $dx): ?array
    {
        $by = [];
        for ($lag = 0; $lag <= self::MAX_LAG; $lag++) {
            $a = $b = [];
            foreach ($dy as $m => $v) {
                $src = (new \DateTimeImmutable($m.'-01'))->modify("-{$lag} month")->format('Y-m');
                if (isset($dx[$src])) {
                    $a[] = $v;
                    $b[] = $dx[$src];
                }
            }
            if (count($a) >= 24) {
                $by[] = ['lag' => $lag, 'corr' => round($this->corr($a, $b), 3), 'n' => count($a)];
            }
        }
        if ($by === []) {
            return null;
        }
        $best = array_reduce($by, fn ($c, $x) => $c === null || $x['corr'] > $c['corr'] ? $x : $c);

        return ['best_lag' => $best['lag'], 'best_corr' => $best['corr'], 'n' => $best['n'], 'by_lag' => $by];
    }

    /** @param  array<string, float>  $p @return array<string, float> */
    private function dlog(array $p): array
    {
        ksort($p);
        $out = [];
        $prev = null;
        foreach ($p as $period => $v) {
            if ($prev !== null && $v > 0 && $prev[1] > 0 && (new \DateTimeImmutable($period.'-01'))->modify('-1 month')->format('Y-m') === $prev[0]) {
                $out[$period] = log($v) - log($prev[1]);
            }
            $prev = [$period, $v];
        }

        return $out;
    }

    /** @param  list<float>  $a @param  list<float>  $b */
    private function corr(array $a, array $b): float
    {
        $n = count($a);
        $ma = array_sum($a) / $n;
        $mb = array_sum($b) / $n;
        $c = $va = $vb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $c += ($a[$i] - $ma) * ($b[$i] - $mb);
            $va += ($a[$i] - $ma) ** 2;
            $vb += ($b[$i] - $mb) ** 2;
        }

        return $va > 0 && $vb > 0 ? $c / sqrt($va * $vb) : 0.0;
    }
}
