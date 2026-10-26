<?php

namespace App\Modules\MarketIntel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * يجمع تجارة المنتج من OEC وعوامل المخاطر من قاعدة النظام ويقيّمها. يُنفَّذ في عامل الطابور «intel» الذي له وصول للإنترنت
 * (حاوية الواجهة معزولة عن الإنترنت عمدًا)، وتُقرأ نتائجه من ذاكرة Redis المشتركة.
 */
class MarketIntelService
{
    private const RISK_INDICATORS = ['lpi' => 'wb.LP.LPI.OVRL.XQ', 'stability' => 'wb.GOV_WGI_PV.SC', 'inform' => 'imf.inform_risk'];

    public function __construct(private OecTradeClient $oec, private SupplierScorer $scorer, private ComtradeClient $comtrade) {}

    /** @return array<string, mixed> */
    public function analysis(int $productId, string $mode): array
    {
        $product = collect($this->oec->products())->firstWhere('id', $productId);
        if ($product === null) {
            throw new \InvalidArgumentException('منتج غير معروف.');
        }
        $latest = $this->oec->latestYear();
        $years = range($latest - 3, $latest);
        [$by, $label] = $mode === 'import' ? ['Exporter Country', 'مُصدِّر'] : ['Importer Country', 'مستورد'];
        $world = $this->oec->trade($productId, $by, $years);
        $oman = $this->oec->trade($productId, $by, $years, OecTradeClient::OMAN);
        $isos = array_values(array_unique(array_column($world, 'iso')));

        $result = $this->scorer->score($world, $oman, $this->riskFor($isos), $mode, $latest, 30);
        $names = DB::table('core.entities')->whereIn('code', array_column($result['candidates'], 'iso'))->pluck('name_ar', 'code');
        $result['candidates'] = array_map(fn ($c) => $c + ['name_ar' => $names[$c['iso']] ?? $c['name']], $result['candidates']);

        $hs4 = (string) $product['hs4'];
        $fta = IntelReference::FTA;
        $tariffs = $this->safe(fn () => $this->oec->tariffs($hs4), ['oman' => [], 'mfn_by_country' => []]);
        $omanMfn = collect($tariffs['oman'])->first(fn ($t) => str_contains($t['agreement'], 'Most Favoured'));
        $result['candidates'] = array_map(fn ($c) => $c + [
            'fta' => $fta[$c['iso']] ?? null,
            'tariff' => $mode === 'export'
                ? ($tariffs['mfn_by_country'][$c['iso']] ?? null)
                : ($omanMfn ? ['tariff' => isset($fta[$c['iso']]) ? 0.0 : $omanMfn['tariff'], 'year' => $omanMfn['year']] : null),
        ], $result['candidates']);

        $unit = $this->safe(fn () => $this->comtrade->unitPrices($hs4, $mode, $latest), null);
        if ($unit) {
            $omanBy = collect($unit['oman_by_partner'])->keyBy('iso');
            $result['candidates'] = array_map(function ($c) use ($unit, $omanBy) {
                $w = $unit['world'][$c['iso']] ?? null;
                $o = $omanBy->get($c['iso']);

                return $c + [
                    'item_price' => $w && $w['unit_code'] === $unit['unit_code'] ? $w['unit_price'] : null,
                    'oman_item_price' => $o && $o['unit_code'] === $unit['unit_code'] ? $o['unit_price'] : null,
                ];
            }, $result['candidates']);
            unset($unit['world']);
            $partnerNames = DB::table('core.entities')->whereIn('code', array_column($unit['oman_by_partner'], 'iso'))->pluck('name_ar', 'code');
            $unit['oman_by_partner'] = array_map(fn ($p) => $p + ['name_ar' => $partnerNames[$p['iso']] ?? $p['iso']], $unit['oman_by_partner']);
        }

        $trendYears = range($latest - 9, $latest);
        $omanTrend = $this->safe(fn () => $this->oec->yearly($productId, $trendYears, $mode === 'import' ? OecTradeClient::OMAN : null, $mode === 'export' ? OecTradeClient::OMAN : null), []);
        $worldTrend = $this->safe(fn () => $this->oec->yearly($productId, $trendYears), []);
        $rca = $this->safe(function () use ($productId, $latest, $worldTrend) {
            $omanProduct = $this->oec->yearly($productId, [$latest], null, OecTradeClient::OMAN)[$latest]['value'] ?? 0.0;
            $omanAll = $this->oec->yearly(null, [$latest], null, OecTradeClient::OMAN)[$latest]['value'] ?? 0.0;
            $worldAll = $this->oec->yearly(null, [$latest])[$latest]['value'] ?? 0.0;
            $worldProduct = $worldTrend[$latest]['value'] ?? 0.0;

            return ($omanAll > 0 && $worldAll > 0 && $worldProduct > 0) ? round(($omanProduct / $omanAll) / ($worldProduct / $worldAll), 3) : null;
        }, null);
        $pciSeries = $this->safe(fn () => $this->oec->pci($hs4), []);

        $analysis = $result + [
            'product' => $product + ['section' => intdiv($productId, 10000), 'section_ar' => IntelReference::SECTIONS[intdiv($productId, 10000)] ?? null],
            'oman_trend' => $omanTrend,
            'world_trend' => array_map(fn ($v) => $v + ['unit_value' => $v['qty'] ? $v['value'] / $v['qty'] : null], $worldTrend),
            'rca' => $rca,
            'pci' => $pciSeries === [] ? null : end($pciSeries),
            'tariffs_oman' => $tariffs['oman'],
            'commodity' => $this->commodity($hs4),
            'unit_prices' => $unit,
            'weights' => SupplierScorer::weightsFor($mode),
            'side' => $label,
            'source' => ['name' => 'BACI (CEPII) عبر OEC Tesseract', 'url' => 'https://oec.world', 'note' => "بيانات تجارة ثنائية سنوية مصالَحة بين المُصدِّر والمستورد حتى {$latest}؛ سعر الوحدة = القيمة ÷ الكمية (دولار/طن) تقريبي. المخاطر واللوجستيات من البنك الدولي وINFORM، والرسوم الجمركية من WITS (آخر سنة متاحة غالبًا 2019)، وتعقيد المنتج من OEC، وأسعار السلع الشهرية من صندوق النقد."],
            'generated_at' => now()->toIso8601String(),
        ];
        $analysis['signals'] = (new SignalEngine)->build($analysis);

        return $analysis;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @param  T  $fallback
     * @return T
     */
    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            report($e);

            return $fallback;
        }
    }

    /** @return list<array{key: string, name_ar: string, unit: ?string, period: string, value: float, change_12m: ?float, series: list<array{0: string, 1: float}>}> */
    private function commodity(string $hs4): array
    {
        $out = [];
        foreach (IntelReference::COMMODITIES[$hs4] ?? [] as $key) {
            $ind = DB::table('core.indicators')->where('key', $key)->first(['id', 'name_ar', 'unit']);
            if (! $ind) {
                continue;
            }
            $pts = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')->where('o.indicator_id', $ind->id)->where('e.code', 'WLD')
                ->orderByDesc('o.period')->limit(60)->get(['o.period', 'o.value'])->reverse()->values();
            if ($pts->isEmpty()) {
                continue;
            }
            $last = $pts->last();
            $yearAgo = $pts->count() > 12 ? $pts[$pts->count() - 13] : null;
            $out[] = ['key' => $key, 'name_ar' => $ind->name_ar, 'unit' => $ind->unit, 'period' => $last->period, 'value' => (float) $last->value,
                'change_12m' => $yearAgo && (float) $yearAgo->value > 0 ? (float) $last->value / (float) $yearAgo->value - 1 : null,
                'series' => $pts->map(fn ($p) => [$p->period, (float) $p->value])->all()];
        }

        return $out;
    }

    /**
     * @param  list<string>  $isos
     * @return array<string, array{lpi: ?float, stability: ?float, inform: ?float}>
     */
    private function riskFor(array $isos): array
    {
        $out = [];
        foreach (self::RISK_INDICATORS as $field => $key) {
            $rows = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')
                ->where('i.key', $key)->whereIn('e.code', $isos)->orderBy('o.period')->get(['e.code', 'o.value']);
            foreach ($rows as $r) {
                $out[$r->code][$field] = (float) $r->value;
            }
        }

        return array_map(fn ($v) => $v + ['lpi' => null, 'stability' => null, 'inform' => null], $out);
    }

    /**
     * حزمة أدلة مضغوطة للذكاء الاصطناعي (أعلى 10 مرشَّحين فقط).
     *
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>
     */
    public function evidence(array $a): array
    {
        return Cache::remember('mi:evidence:v3:'.md5(json_encode([$a['product']['id'], $a['summary']['mode'], $a['summary']['year']])), 3600, fn () => [
            'product' => ['name_ar' => $a['product']['name_ar'], 'hs4' => $a['product']['hs4']],
            'mode' => $a['summary']['mode'],
            'year' => $a['summary']['year'],
            'summary' => $a['summary'],
            'candidates' => array_map(fn ($c) => [
                'name_ar' => $c['name_ar'], 'value_usd' => round($c['value']), 'share' => $c['share'] !== null ? round($c['share'], 3) : null,
                'fta' => $c['fta'] ?? null, 'tariff' => $c['tariff'] ?? null, 'item_price_usd' => isset($c['item_price']) && $c['item_price'] !== null ? round($c['item_price'], 2) : null, 'cagr_pct' => $c['cagr'] !== null ? round($c['cagr'], 1) : null, 'unit_value_usd_per_ton' => $c['unit_value'] !== null ? round($c['unit_value']) : null,
                'price_vs_median' => $c['price_vs_median'] !== null ? round($c['price_vs_median'], 2) : null, 'oman_value_usd' => round($c['oman_value']),
                'components' => $c['components'], 'score' => $c['score'], 'risk_inputs' => $c['risk'],
            ], array_slice($a['candidates'], 0, 10)),
            'signals' => array_map(fn ($x) => ['level' => $x['level'], 'title' => $x['title'], 'detail' => $x['detail']], $a['signals'] ?? []),
            'oman_trend_usd' => array_map(fn ($v) => round($v['value']), $a['oman_trend'] ?? []),
            'world_trend_usd' => array_map(fn ($v) => round($v['value']), $a['world_trend'] ?? []),
            'rca' => $a['rca'] ?? null, 'pci' => $a['pci'] ?? null,
            'unit_prices' => isset($a['unit_prices']) ? ['unit' => $a['unit_prices']['unit'], 'year' => $a['unit_prices']['year'], 'oman_avg_per_unit_usd' => $a['unit_prices']['oman_total']['unit_price'] ?? null, 'world_median_per_unit_usd' => $a['unit_prices']['world_median_unit_price'], 'hs6_top' => array_map(fn ($h) => ['code' => $h['code'], 'text' => $h['text'], 'value_usd' => round($h['value']), 'per_unit_usd' => $h['unit_price'] !== null ? round($h['unit_price'], 2) : null], array_slice($a['unit_prices']['hs6'], 0, 6))] : null,
            'oman_tariffs' => $a['tariffs_oman'] ?? [],
            'commodity_prices' => array_map(fn ($x) => ['name_ar' => $x['name_ar'], 'unit' => $x['unit'], 'period' => $x['period'], 'value' => round($x['value'], 2), 'change_12m' => $x['change_12m'] !== null ? round($x['change_12m'], 3) : null], $a['commodity'] ?? []),
            'scale_note' => 'الدرجات من 0 إلى 100 والأعلى أفضل للمشتري (في وضع الاستيراد: السعر الأدنى أفضل) أو للمصدّر (في وضع التصدير: السعر الأعلى أفضل).',
        ]);
    }
}
