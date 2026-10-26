<?php

namespace App\Modules\ValueChain;

use Illuminate\Support\Facades\DB;

/**
 * التحقق الإحصائي من أوزان السلسلة: انحدار التغير الشهري (لوغاريتمي) لسعر العقدة على تغيرات مدخلاتها، بأفضل تأخر (0–6 أشهر) لكل مدخل.
 * في سوق تنافسي تقارب مرونة سعر المنتج لسعر المدخل حصةَ المدخل من التكلفة، فإن ابتعدت المرونة كثيرًا عن الوزن المقدّر نُبّه المحلل.
 * الانتقال يتوزع على عدة أشهر، فالمرونة = مجموع معاملات التأخرات 0–3 (انحدار بتأخرات موزعة)، وخطؤها المعياري من مصفوفة التباين؛
 * ويُذكر معها تأخر أعلى ارتباط (0–6) دليلًا على فترة الانتقال. تكاليف التحويل (عمالة، إهلاك…) لا تدخل الانحدار لأنها لا تملك سلسلة سعرية معبّرة.
 * يُستخدم أول سلسلة شهرية للعقدة (الأساسية أولًا). السلاسل الجمركية متقلبة، فتُفضَّل المؤشرات؛ ويُشترط 30 شهرًا مشتركًا على الأقل.
 */
final class WeightValidator
{
    private const MAX_LAG = 6;

    private const DL_LAGS = 3;

    private const MIN_OBS = 30;

    /** @return list<array<string, mixed>> نتائج كل عقدة فُحصت */
    public function validateChain(int $chainId): array
    {
        $nodes = DB::table('intel.chain_nodes')->where('chain_id', $chainId)->get()->keyBy('id');
        $edges = DB::table('intel.chain_edges')->where('chain_id', $chainId)->whereNotNull('weight')->get()->groupBy('to_node_id');
        $out = [];
        foreach ($edges as $targetId => $incoming) {
            $target = $nodes[$targetId];
            $y = $this->nodeSeries((int) $targetId);
            if (! $y) {
                continue;
            }
            $inputs = [];
            foreach ($incoming as $e) {
                if ($nodes[$e->from_node_id]->kind === 'conversion') {
                    continue;
                }
                $x = $this->nodeSeries((int) $e->from_node_id);
                if ($x) {
                    $inputs[] = ['edge' => $e, 'node' => $nodes[$e->from_node_id], 'series' => $x];
                }
            }
            if ($inputs === []) {
                continue;
            }
            $result = $this->regress($y, $inputs);
            if ($result === null) {
                continue;
            }
            DB::table('intel.regression_checks')->insert([
                'chain_id' => $chainId, 'node_id' => $targetId, 'computed_at' => now(), 'method' => 'ols_dlog_distributed_lags_0_3',
                'window_from' => $result['from'], 'window_to' => $result['to'], 'n_obs' => $result['n'], 'r2' => $result['r2'],
                'results' => json_encode(['target_series' => $y['key'], 'inputs' => $result['inputs']], JSON_UNESCAPED_UNICODE), 'flagged' => $result['flagged'],
            ]);
            $out[] = ['node' => $target->slug] + $result;
        }

        return $out;
    }

    /** @return array{key: string, points: array<string, float>}|null أول سلسلة شهرية للعقدة بها بيانات كافية */
    private function nodeSeries(int $nodeId): ?array
    {
        $links = DB::table('intel.node_series as ns')->join('intel.price_series as s', 's.id', '=', 'ns.series_id')
            ->where('ns.node_id', $nodeId)->where('s.frequency', 'M')->where('s.enabled', true)
            ->orderByRaw("CASE ns.role WHEN 'primary' THEN 0 WHEN 'alternative' THEN 1 ELSE 2 END")->orderByRaw("CASE s.price_type WHEN 'index' THEN 0 WHEN 'reference' THEN 1 ELSE 2 END")->orderBy('ns.priority')
            ->get(['s.indicator_key', 's.entity_code']);
        foreach ($links as $l) {
            $pts = (new SeriesReader)->points($l->indicator_key, $l->entity_code, (string) (date('Y') - 10));
            if (count($pts) >= self::MIN_OBS + self::MAX_LAG) {
                return ['key' => "{$l->indicator_key}|{$l->entity_code}", 'points' => $pts];
            }
        }

        return null;
    }

    /**
     * @param  array{key: string, points: array<string, float>}  $y
     * @param  list<array{edge: object, node: object, series: array{key: string, points: array<string, float>}}>  $inputs
     * @return array<string, mixed>|null
     */
    public function regress(array $y, array $inputs): ?array
    {
        $dy = $this->dlog($y['points']);
        $chosen = [];
        foreach ($inputs as $in) {
            $dx = $this->dlog($in['series']['points']);
            $best = [0, -INF];
            for ($lag = 0; $lag <= self::MAX_LAG; $lag++) {
                $pairs = $this->pairs($dy, $dx, $lag);
                if (count($pairs) < self::MIN_OBS) {
                    continue;
                }
                $c = $this->corr(array_column($pairs, 0), array_column($pairs, 1));
                if ($c > $best[1]) {
                    $best = [$lag, $c];
                }
            }
            if ($best[1] > -INF) {
                $chosen[] = $in + ['lag' => $best[0], 'corr' => $best[1], 'dx' => $dx];
            }
        }
        if ($chosen === []) {
            return null;
        }
        // مصفوفة التصميم: ثابت + تأخرات 0..3 لكل مدخل، على الأشهر المشتركة.
        $rows = [];
        foreach (array_keys($dy) as $m) {
            $row = [1.0];
            foreach ($chosen as $c) {
                for ($l = 0; $l <= self::DL_LAGS; $l++) {
                    $src = $this->shift($m, $l);
                    if (! isset($c['dx'][$src])) {
                        continue 3;
                    }
                    $row[] = $c['dx'][$src];
                }
            }
            $rows[$m] = [$dy[$m], $row];
        }
        $n = count($rows);
        $k = count($chosen) * (self::DL_LAGS + 1) + 1;
        if ($n < self::MIN_OBS || $n <= $k + 5) {
            return null;
        }
        $ols = $this->ols(array_column(array_values($rows), 1), array_column(array_values($rows), 0));
        if ($ols === null) {
            return null;
        }
        $flagged = false;
        $res = [];
        foreach ($chosen as $i => $c) {
            $idx = range(1 + $i * (self::DL_LAGS + 1), ($i + 1) * (self::DL_LAGS + 1));
            $beta = array_sum(array_map(fn ($j) => $ols['beta'][$j], $idx));
            $var = 0.0;
            foreach ($idx as $a) {
                foreach ($idx as $b) {
                    $var += $ols['cov'][$a][$b];
                }
            }
            $se = sqrt(max(0.0, $var));
            $w = (float) $c['edge']->weight;
            $lo = $c['edge']->weight_min !== null ? (float) $c['edge']->weight_min : $w;
            $hi = $c['edge']->weight_max !== null ? (float) $c['edge']->weight_max : $w;
            $conflict = ($beta < $lo - 0.10 || $beta > $hi + 0.10) && abs($beta - $w) > 0.15 && $se > 0 && abs($beta - $w) / $se > 2;
            $flagged = $flagged || $conflict;
            $res[] = ['from' => $c['node']->slug, 'name_ar' => $c['node']->name_ar, 'series' => $c['series']['key'], 'lag_months' => $c['lag'], 'corr' => round($c['corr'], 3),
                'beta' => round($beta, 3), 'se' => round($se, 3), 't' => $se > 0 ? round($beta / $se, 2) : null, 'lag_profile' => array_map(fn ($j) => round($ols['beta'][$j], 3), $idx),
                'weight' => $w, 'weight_min' => $lo, 'weight_max' => $hi, 'conflict' => $conflict];
        }

        return ['n' => $n, 'r2' => round($ols['r2'], 3), 'from' => array_key_first($rows), 'to' => array_key_last($rows), 'inputs' => $res, 'flagged' => $flagged];
    }

    /** @param  array<string, float>  $p @return array<string, float> */
    private function dlog(array $p): array
    {
        ksort($p);
        $out = [];
        $prev = null;
        foreach ($p as $period => $v) {
            if ($prev !== null && $v > 0 && $prev[1] > 0 && $this->shift($period, 1) === $prev[0]) {
                $out[$period] = log($v) - log($prev[1]);
            }
            $prev = [$period, $v];
        }

        return $out;
    }

    private function shift(string $period, int $months): string
    {
        return (new \DateTimeImmutable($period.'-01'))->modify("-{$months} month")->format('Y-m');
    }

    /** @return list<array{0: float, 1: float}> */
    private function pairs(array $dy, array $dx, int $lag): array
    {
        $out = [];
        foreach ($dy as $m => $v) {
            $src = $this->shift($m, $lag);
            if (isset($dx[$src])) {
                $out[] = [$v, $dx[$src]];
            }
        }

        return $out;
    }

    /** @param  list<float>  $a @param  list<float>  $b */
    private function corr(array $a, array $b): float
    {
        $n = count($a);
        $ma = array_sum($a) / $n;
        $mb = array_sum($b) / $n;
        $cov = $va = $vb = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $cov += ($a[$i] - $ma) * ($b[$i] - $mb);
            $va += ($a[$i] - $ma) ** 2;
            $vb += ($b[$i] - $mb) ** 2;
        }

        return $va > 0 && $vb > 0 ? $cov / sqrt($va * $vb) : 0.0;
    }

    /**
     * المربعات الصغرى العادية بالمعادلات الطبيعية.
     *
     * @param  list<list<float>>  $X
     * @param  list<float>  $Y
     * @return array{beta: list<float>, se: list<float>, cov: list<list<float>>, r2: float}|null
     */
    public function ols(array $X, array $Y): ?array
    {
        $n = count($X);
        $k = count($X[0]);
        $xtx = array_fill(0, $k, array_fill(0, $k, 0.0));
        $xty = array_fill(0, $k, 0.0);
        for ($r = 0; $r < $n; $r++) {
            for ($i = 0; $i < $k; $i++) {
                $xty[$i] += $X[$r][$i] * $Y[$r];
                for ($j = 0; $j < $k; $j++) {
                    $xtx[$i][$j] += $X[$r][$i] * $X[$r][$j];
                }
            }
        }
        $inv = $this->invert($xtx);
        if ($inv === null) {
            return null;
        }
        $beta = array_map(fn ($row) => array_sum(array_map(fn ($a, $b) => $a * $b, $row, $xty)), $inv);
        $my = array_sum($Y) / $n;
        $sse = $sst = 0.0;
        for ($r = 0; $r < $n; $r++) {
            $fit = array_sum(array_map(fn ($a, $b) => $a * $b, $X[$r], $beta));
            $sse += ($Y[$r] - $fit) ** 2;
            $sst += ($Y[$r] - $my) ** 2;
        }
        $s2 = $sse / max(1, $n - $k);

        $cov = array_map(fn ($row) => array_map(fn ($v) => $s2 * $v, $row), $inv);

        return ['beta' => $beta, 'se' => array_map(fn ($i) => sqrt(max(0.0, $cov[$i][$i])), range(0, $k - 1)), 'cov' => $cov, 'r2' => $sst > 0 ? 1 - $sse / $sst : 0.0];
    }

    /** @param  list<list<float>>  $m @return list<list<float>>|null */
    private function invert(array $m): ?array
    {
        $n = count($m);
        $a = [];
        foreach ($m as $i => $row) {
            $a[$i] = [...$row, ...array_map(fn ($j) => $i === $j ? 1.0 : 0.0, range(0, $n - 1))];
        }
        for ($c = 0; $c < $n; $c++) {
            $p = $c;
            for ($r = $c + 1; $r < $n; $r++) {
                if (abs($a[$r][$c]) > abs($a[$p][$c])) {
                    $p = $r;
                }
            }
            if (abs($a[$p][$c]) < 1e-12) {
                return null;
            }
            [$a[$c], $a[$p]] = [$a[$p], $a[$c]];
            $div = $a[$c][$c];
            $a[$c] = array_map(fn ($v) => $v / $div, $a[$c]);
            for ($r = 0; $r < $n; $r++) {
                if ($r !== $c && $a[$r][$c] != 0.0) {
                    $f = $a[$r][$c];
                    foreach ($a[$r] as $j => $v) {
                        $a[$r][$j] = $v - $f * $a[$c][$j];
                    }
                }
            }
        }

        return array_map(fn ($row) => array_slice($row, $n), $a);
    }
}
