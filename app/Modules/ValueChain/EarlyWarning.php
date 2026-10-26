<?php

namespace App\Modules\ValueChain;

use Illuminate\Support\Facades\DB;

/**
 * الإنذار المبكر القابل للتفسير: انحدار التغير الشهري لسعر العقدة على تغيرات مدخلاتها بتأخرات 0–6 أشهر (Distributed lags).
 * لأن المدخلات تتحرك قبل المنتج، فجزء من الحركة القادمة «محسوم» بما حدث فعلًا في المدخلات: التوقع لأفق h شهرًا = مجموع
 * معاملات التأخرات مضروبة في تغيرات المدخلات التي وقعت ولم تنتقل بعد، بافتراض ثبات المدخلات من الآن. كل توقع يحمل
 * مساهمة كل مدخل ونطاق ثقة 90% (من بواقي النموذج)، ويُقيَّم لاحقًا بالقيمة الفعلية.
 */
final class EarlyWarning
{
    public const LAGS = 6;

    public const HORIZONS = [1, 3, 6];

    public function __construct(private ChainAnalytics $analytics, private WeightValidator $ols) {}

    /** @return list<array<string, mixed>> توقعات كل عقدة لها مدخلات بسلاسل كافية */
    public function chain(int $chainId): array
    {
        $nodes = DB::table('intel.chain_nodes')->where('chain_id', $chainId)->get()->keyBy('id');
        $edges = DB::table('intel.chain_edges')->where('chain_id', $chainId)->whereNotNull('weight')->get()->groupBy('to_node_id');
        $out = [];
        foreach ($edges as $targetId => $incoming) {
            $y = $this->target((int) $targetId);
            if (! $y) {
                continue;
            }
            $inputs = [];
            foreach ($incoming as $e) {
                $n = $nodes[$e->from_node_id];
                if ($n->kind === 'conversion') {
                    continue;
                }
                $x = $this->longest((int) $n->id);
                if ($x) {
                    $inputs[] = ['node' => $n, 'weight' => (float) $e->weight, 'series' => $x];
                }
            }
            if ($inputs === []) {
                continue;
            }
            $f = $this->forecast($y, $inputs);
            if ($f) {
                $out[] = ['node_id' => (int) $targetId, 'node' => $nodes[$targetId]->name_ar] + $f;
            }
        }

        return $out;
    }

    /** سلسلة المدخل للتنبؤ: أطول سلسلة شهرية (48 شهرًا فأكثر) مع تفضيل المؤشرات والأسعار المرجعية؛ المدخل بلا سلسلة كافية يُستبعد وحده. */
    private function longest(int $nodeId): ?array
    {
        return $this->target($nodeId);
    }

    /** سلسلة الهدف: المؤشرات أولًا (أنعم وأطول)، ثم المرجعية، ثم الجمركية. */
    private function target(int $nodeId): ?array
    {
        $links = DB::table('intel.node_series as ns')->join('intel.price_series as s', 's.id', '=', 'ns.series_id')->where('ns.node_id', $nodeId)->where('s.enabled', true)
            ->whereIn('s.frequency', ['M', 'D'])->orderByRaw("CASE s.price_type WHEN 'index' THEN 0 WHEN 'reference' THEN 1 WHEN 'futures' THEN 2 ELSE 3 END")->orderBy('ns.priority')->get(['s.indicator_key', 's.entity_code', 's.label_ar', 's.frequency']);
        $reader = new SeriesReader;
        foreach ($links as $l) {
            $pts = $reader->points($l->indicator_key, $l->entity_code, (string) (date('Y') - 10));
            if ($l->frequency === 'D') {
                $m = [];
                foreach ($pts as $p => $v) {
                    $m[substr($p, 0, 7)] = $v;
                }
                $pts = $m;
            }
            if (count($pts) >= 48) {
                return ['key' => "{$l->indicator_key}|{$l->entity_code}", 'label_ar' => $l->label_ar, 'points' => $pts];
            }
        }

        return null;
    }

    /**
     * @param  array{key: string, label_ar: string, points: array<string, float>}  $y
     * @param  list<array{node: object, weight: float, series: array{key: string, label_ar: string, points: array<string, float>}}>  $inputs
     * @return array<string, mixed>|null
     */
    public function forecast(array $y, array $inputs): ?array
    {
        $dy = self::dlog($y['points']);
        $dx = array_map(fn ($in) => self::dlog($in['series']['points']), $inputs);
        $months = array_keys($dy);
        $rows = [];
        foreach ($months as $m) {
            $row = [1.0];
            foreach ($dx as $d) {
                for ($k = 0; $k <= self::LAGS; $k++) {
                    $src = self::shift($m, $k);
                    if (! isset($d[$src])) {
                        continue 3;
                    }
                    $row[] = $d[$src];
                }
            }
            $rows[$m] = [$dy[$m], $row];
        }
        $n = count($rows);
        $k = count($inputs) * (self::LAGS + 1) + 1;
        if ($n < max(36, $k + 12)) {
            return null;
        }
        $ols = $this->ols->ols(array_column(array_values($rows), 1), array_column(array_values($rows), 0));
        if ($ols === null) {
            return null;
        }
        $residSd = sqrt(max(1e-12, (1 - $ols['r2']) * self::variance(array_column(array_values($rows), 0))));
        // آخر شهر تتوفر فيه كل المدخلات = نقطة الانطلاق
        $last = min(array_map(fn ($d) => array_key_last($d), $dx));
        $horizons = [];
        foreach (self::HORIZONS as $h) {
            $total = $ols['beta'][0] * $h; // الاتجاه الشهري المعتاد (الثابت) × الأفق
            $contrib = [];
            foreach ($inputs as $i => $in) {
                $c = 0.0;
                for ($j = 1; $j <= $h; $j++) {
                    for ($lag = $j; $lag <= self::LAGS; $lag++) {
                        $src = self::shift(self::shift($last, -$j), $lag);
                        $c += $ols['beta'][1 + $i * (self::LAGS + 1) + $lag] * ($dx[$i][$src] ?? 0.0);
                    }
                }
                $total += $c;
                $contrib[] = ['input' => $in['node']->name_ar, 'series' => $in['series']['key'], 'contribution_pct' => round((exp($c) - 1) * 100, 2),
                    'recent_change_pct' => round((exp(array_sum(array_slice(array_values($dx[$i]), -3))) - 1) * 100, 1)];
            }
            // النطاق يشمل البواقي وحركات المدخلات المستقبلية غير المعروفة: نستخدم التباين غير المشروط لتغير السعر الشهري (أوسع وأصدق تغطية).
            $band = 1.645 * sqrt(self::variance(array_column(array_values($rows), 0)) * $h);
            usort($contrib, fn ($a, $b) => abs($b['contribution_pct']) <=> abs($a['contribution_pct']));
            $contrib[] = ['input' => 'الاتجاه المعتاد للسعر', 'series' => $y['key'], 'contribution_pct' => round((exp($ols['beta'][0] * $h) - 1) * 100, 2), 'recent_change_pct' => null];
            $horizons[] = ['horizon_months' => $h, 'expected_pct' => round((exp($total) - 1) * 100, 2), 'low_pct' => round((exp($total - $band) - 1) * 100, 2), 'high_pct' => round((exp($total + $band) - 1) * 100, 2), 'drivers' => $contrib];
        }

        return ['target_series' => $y['key'], 'target_label' => $y['label_ar'], 'base_period' => $last, 'method' => 'distributed_lag_ols_0_6_drift',
            'fit' => ['n' => $n, 'r2' => round($ols['r2'], 3), 'resid_sd' => round($residSd, 4)], 'horizons' => $horizons];
    }

    /**
     * اختبار رجعي متدحرج: لكل شهر انطلاق في آخر N شهرًا يُقدَّر النموذج ببيانات ذلك الوقت فقط ثم يُقارن التوقع بما حدث فعلًا.
     * يعطي دقة شفافة من اليوم الأول بدل انتظار حلول التوقعات الحية.
     */
    public function backtest(int $months = 24): int
    {
        $stored = 0;
        foreach (DB::table('intel.value_chains')->pluck('id') as $chainId) {
            $nodes = DB::table('intel.chain_nodes')->where('chain_id', $chainId)->get()->keyBy('id');
            foreach (DB::table('intel.chain_edges')->where('chain_id', $chainId)->whereNotNull('weight')->get()->groupBy('to_node_id') as $targetId => $incoming) {
                $y = $this->target((int) $targetId);
                if (! $y) {
                    continue;
                }
                $inputs = [];
                foreach ($incoming as $e) {
                    if ($nodes[$e->from_node_id]->kind !== 'conversion' && ($x = $this->longest((int) $e->from_node_id))) {
                        $inputs[] = ['node' => $nodes[$e->from_node_id], 'weight' => (float) $e->weight, 'series' => $x];
                    }
                }
                if ($inputs === []) {
                    continue;
                }
                $lastY = array_key_last($y['points']);
                for ($back = 6; $back < $months + 6; $back++) {
                    $origin = self::shift($lastY, $back);
                    $cut = fn (array $pts) => array_filter($pts, fn ($p) => $p <= $origin, ARRAY_FILTER_USE_KEY);
                    $f = $this->forecast(['key' => $y['key'], 'label_ar' => $y['label_ar'], 'points' => $cut($y['points'])], array_map(fn ($in) => ['series' => ['key' => $in['series']['key'], 'label_ar' => $in['series']['label_ar'], 'points' => $cut($in['series']['points'])]] + $in, $inputs));
                    if (! $f || $f['base_period'] !== $origin) {
                        continue;
                    }
                    foreach ($f['horizons'] as $h) {
                        $due = self::shift($origin, -$h['horizon_months']);
                        if (! isset($y['points'][$due], $y['points'][$origin]) || $y['points'][$origin] <= 0) {
                            continue;
                        }
                        $stored += DB::table('intel.forecasts')->insertOrIgnore([
                            'chain_id' => $chainId, 'node_id' => $targetId, 'target_series' => $f['target_series'], 'issued_at' => now(), 'base_period' => $origin, 'horizon_months' => $h['horizon_months'],
                            'expected_pct' => $h['expected_pct'], 'low_pct' => $h['low_pct'], 'high_pct' => $h['high_pct'], 'method' => $f['method'].'_backtest',
                            'drivers' => json_encode($h['drivers'], JSON_UNESCAPED_UNICODE), 'fit' => json_encode($f['fit']),
                            'actual_pct' => round(($y['points'][$due] / $y['points'][$origin] - 1) * 100, 2), 'evaluated_at' => now(),
                        ]);
                    }
                }
            }
        }

        return $stored;
    }

    /** يحفظ التوقعات ويقيّم ما حان موعده بالقيم الفعلية. */
    public function issueAndEvaluate(): array
    {
        $issued = 0;
        foreach (DB::table('intel.value_chains')->pluck('id') as $chainId) {
            foreach ($this->chain((int) $chainId) as $f) {
                foreach ($f['horizons'] as $h) {
                    $issued += DB::table('intel.forecasts')->insertOrIgnore([
                        'chain_id' => $chainId, 'node_id' => $f['node_id'], 'target_series' => $f['target_series'], 'issued_at' => now(), 'base_period' => $f['base_period'],
                        'horizon_months' => $h['horizon_months'], 'expected_pct' => $h['expected_pct'], 'low_pct' => $h['low_pct'], 'high_pct' => $h['high_pct'],
                        'method' => $f['method'], 'drivers' => json_encode($h['drivers'], JSON_UNESCAPED_UNICODE), 'fit' => json_encode($f['fit']),
                    ]);
                }
            }
        }
        $evaluated = 0;
        $reader = new SeriesReader;
        foreach (DB::table('intel.forecasts')->whereNull('actual_pct')->get() as $f) {
            $due = self::shift($f->base_period, -$f->horizon_months);
            [$key, $entity] = explode('|', $f->target_series);
            $pts = $reader->points($key, $entity);
            $m = [];
            foreach ($pts as $p => $v) {
                $m[substr($p, 0, 7)] = $v;
            }
            if (isset($m[$f->base_period], $m[$due]) && $m[$f->base_period] > 0) {
                DB::table('intel.forecasts')->where('id', $f->id)->update(['actual_pct' => round(($m[$due] / $m[$f->base_period] - 1) * 100, 2), 'evaluated_at' => now()]);
                $evaluated++;
            }
        }

        return ['issued' => $issued, 'evaluated' => $evaluated];
    }

    /** @param  array<string, float>  $p @return array<string, float> */
    public static function dlog(array $p): array
    {
        ksort($p);
        $out = [];
        $prev = null;
        foreach ($p as $period => $v) {
            if ($prev !== null && $v > 0 && $prev[1] > 0 && self::shift($period, 1) === $prev[0]) {
                $out[$period] = log($v) - log($prev[1]);
            }
            $prev = [$period, $v];
        }

        return $out;
    }

    /** إزاحة شهرية: موجبة للماضي وسالبة للمستقبل. */
    public static function shift(string $period, int $months): string
    {
        return (new \DateTimeImmutable($period.'-01'))->modify(($months >= 0 ? '-' : '+').abs($months).' month')->format('Y-m');
    }

    /** @param  list<float>  $v */
    private static function variance(array $v): float
    {
        $m = array_sum($v) / count($v);

        return array_sum(array_map(fn ($x) => ($x - $m) ** 2, $v)) / max(1, count($v) - 1);
    }
}
