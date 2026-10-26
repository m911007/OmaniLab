<?php

namespace App\Modules\MarketIntel;

/**
 * محرك التقييم لذكاء الأسواق (حتمي وقابل للاختبار، بلا ذكاء اصطناعي): يحوّل تجارة منتج إلى مرشَّحين (موردين للاستيراد أو أسواق للتصدير)
 * بخمس درجات من 0 إلى 100: الحجم، والنمو، والسعر، واللوجستيات، والمخاطر؛ ودرجة مركّبة بأوزان قابلة للتعديل.
 * القيمة المفقودة (مثل الكمية فلا سعر وحدة) تُترك null وتُستبعد وزنها عند التركيب فلا تُعاقَب الدولة على نقص البيانات.
 */
final class SupplierScorer
{
    /** @var array<string, float> */
    public const DEFAULT_WEIGHTS = ['scale' => 0.25, 'growth' => 0.15, 'price' => 0.3, 'logistics' => 0.1, 'risk' => 0.2];

    /** @var array<string, float> للتصدير يهم حجم السوق ونموه أكثر من السعر */
    public const EXPORT_WEIGHTS = ['scale' => 0.35, 'growth' => 0.2, 'price' => 0.2, 'logistics' => 0.1, 'risk' => 0.15];

    /** @return array<string, float> */
    public static function weightsFor(string $mode): array
    {
        return $mode === 'export' ? self::EXPORT_WEIGHTS : self::DEFAULT_WEIGHTS;
    }

    /**
     * @param  list<array{iso: string, name: string, year: int, value: float, qty: ?float}>  $world  تجارة المنتج مع كل دولة (مُصدِّرة في وضع import، مستوردة في export)
     * @param  list<array{iso: string, name: string, year: int, value: float, qty: ?float}>  $oman  تجارة عُمان نفسها مع هذه الدول
     * @param  array<string, array{lpi: ?float, stability: ?float, inform: ?float}>  $risk
     * @param  'import'|'export'  $mode
     * @return array{candidates: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function score(array $world, array $oman, array $risk, string $mode, int $latestYear, int $limit = 25): array
    {
        $byIso = [];
        foreach ($world as $r) {
            if ($r['iso'] === 'OMN') {
                continue;
            }
            $byIso[$r['iso']]['name'] = $r['name'];
            $byIso[$r['iso']]['years'][$r['year']] = ['value' => $r['value'], 'qty' => $r['qty']];
        }
        $omanBy = [];
        foreach ($oman as $r) {
            $omanBy[$r['iso']][$r['year']] = ['value' => $r['value'], 'qty' => $r['qty']];
        }

        $rows = [];
        foreach ($byIso as $iso => $d) {
            $now = $d['years'][$latestYear] ?? null;
            if (! $now || $now['value'] <= 0) {
                continue;
            }
            $firstYear = min(array_keys($d['years']));
            $first = $d['years'][$firstYear];
            $span = $latestYear - $firstYear;
            $cagr = ($span > 0 && $first['value'] > 0) ? (($now['value'] / $first['value']) ** (1 / $span) - 1) * 100 : null;
            $uvSeries = [];
            foreach ($d['years'] as $y => $v) {
                if ($v['qty'] && $v['qty'] > 0) {
                    $uvSeries[$y] = $v['value'] / $v['qty'];
                }
            }
            ksort($uvSeries);
            $mine = $omanBy[$iso][$latestYear] ?? null;
            $rows[$iso] = [
                'iso' => $iso, 'name' => $d['name'], 'value' => $now['value'], 'cagr' => $cagr,
                'unit_value' => ($now['qty'] && $now['qty'] > 0) ? $now['value'] / $now['qty'] : null,
                'uv_series' => $uvSeries,
                'oman_value' => $mine['value'] ?? 0.0,
                'oman_unit_value' => ($mine && $mine['qty'] && $mine['qty'] > 0) ? $mine['value'] / $mine['qty'] : null,
                'oman_qty' => $mine['qty'] ?? null,
                'risk' => ['lpi' => $risk[$iso]['lpi'] ?? null, 'stability' => $risk[$iso]['stability'] ?? null, 'inform' => $risk[$iso]['inform'] ?? null],
            ];
        }
        uasort($rows, fn ($a, $b) => $b['value'] <=> $a['value']);
        $total = array_sum(array_column($rows, 'value'));
        $rows = array_slice($rows, 0, $limit, true);

        $uvs = array_filter(array_column($rows, 'unit_value'), fn ($v) => $v !== null);
        $medianUv = $this->median(array_values($uvs));
        $maxLogValue = log(max(1.0, max(array_column($rows, 'value') ?: [1.0])));

        $candidates = [];
        foreach ($rows as $r) {
            $price = null;
            if ($r['unit_value'] !== null && $medianUv) {
                $rel = ($r['unit_value'] - $medianUv) / $medianUv;
                $price = $this->clamp(50 + ($mode === 'import' ? -$rel : $rel) * 50);
            }
            $logistics = $r['risk']['lpi'] !== null ? $this->clamp(($r['risk']['lpi'] - 1) / 4 * 100) : null;
            $riskParts = array_filter([
                $r['risk']['stability'] !== null ? $this->clamp(($r['risk']['stability'] + 2.5) / 5 * 100) : null,
                $r['risk']['inform'] !== null ? $this->clamp(100 - $r['risk']['inform'] * 10) : null,
            ], fn ($v) => $v !== null);
            $components = [
                'scale' => $maxLogValue > 0 ? $this->clamp(log(max(1.0, $r['value'])) / $maxLogValue * 100) : null,
                'growth' => $r['cagr'] !== null ? $this->clamp(50 + $r['cagr'] * (50 / 20)) : null,
                'price' => $price,
                'logistics' => $logistics,
                'risk' => $riskParts !== [] ? array_sum($riskParts) / count($riskParts) : null,
            ];
            $candidates[] = $r + [
                'share' => $total > 0 ? $r['value'] / $total : null,
                'price_vs_median' => ($r['unit_value'] !== null && $medianUv) ? ($r['unit_value'] - $medianUv) / $medianUv : null,
                'components' => array_map(fn ($v) => $v === null ? null : round($v, 1), $components),
                'score' => $this->composite($components, self::weightsFor($mode)),
            ];
        }
        usort($candidates, fn ($a, $b) => ($b['score'] ?? -1) <=> ($a['score'] ?? -1));

        $omanTotal = 0.0;
        $omanShares = [];
        $omanQty = 0.0;
        $omanValueWithQty = 0.0;
        foreach ($omanBy as $iso => $years) {
            $v = $years[$latestYear]['value'] ?? 0.0;
            if ($v > 0) {
                $omanTotal += $v;
                $omanShares[$iso] = $v;
                if (! empty($years[$latestYear]['qty'])) {
                    $omanQty += $years[$latestYear]['qty'];
                    $omanValueWithQty += $v;
                }
            }
        }
        arsort($omanShares);
        $omanUv = $omanQty > 0 ? $omanValueWithQty / $omanQty : null;
        $p25 = $this->percentile(array_values($uvs), 0.25);
        $saving = ($mode === 'import' && $omanUv && $p25 && $omanUv > $p25 && $omanQty > 0) ? ($omanUv - $p25) * $omanQty : null;

        return [
            'candidates' => $candidates,
            'summary' => [
                'mode' => $mode, 'year' => $latestYear, 'market_value' => $total,
                'hhi_market' => $this->hhi(array_column($rows, 'value')),
                'oman_value' => $omanTotal, 'hhi_oman' => $omanTotal > 0 ? $this->hhi(array_values($omanShares)) : null,
                'oman_top_partner' => array_key_first($omanShares),
                'oman_unit_value' => $omanUv, 'median_unit_value' => $medianUv, 'p25_unit_value' => $p25,
                'saving_estimate' => $saving,
            ],
        ];
    }

    /**
     * @param  array<string, ?float>  $components
     * @param  array<string, float>  $weights
     */
    public function composite(array $components, array $weights): ?float
    {
        $sum = 0.0;
        $w = 0.0;
        foreach ($weights as $k => $weight) {
            if (($components[$k] ?? null) !== null && $weight > 0) {
                $sum += $components[$k] * $weight;
                $w += $weight;
            }
        }

        return $w > 0 ? round($sum / $w, 1) : null;
    }

    /** @param  list<float>  $values */
    private function hhi(array $values): float
    {
        $t = array_sum($values);

        return $t > 0 ? round(array_sum(array_map(fn ($v) => ($v / $t * 100) ** 2, $values))) : 0.0;
    }

    /** @param  list<float>  $v */
    private function median(array $v): ?float
    {
        return $this->percentile($v, 0.5);
    }

    /** @param  list<float>  $v */
    private function percentile(array $v, float $p): ?float
    {
        if ($v === []) {
            return null;
        }
        sort($v);
        $i = ($p) * (count($v) - 1);
        $lo = (int) floor($i);
        $hi = (int) ceil($i);

        return $v[$lo] + ($v[$hi] - $v[$lo]) * ($i - $lo);
    }

    private function clamp(float $v): float
    {
        return max(0.0, min(100.0, $v));
    }
}
