<?php

namespace App\Modules\MarketIntel;

use App\Core\Support\ApiKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * UN Comtrade (الواجهة العامة بلا مفتاح، حد 500 صف للطلب): يضيف ما لا تعطيه BACI — الكمية بوحدتها الطبيعية (قطعة، زوج، لتر، كغم)
 * فيُحسب «سعر القطعة الواحدة» لا سعر الطن فقط، وتفصيل البنود الفرعية HS6 لواردات عُمان أو صادراتها كما صرّحت بها عُمان نفسها.
 */
final class ComtradeClient
{
    private const BASE = 'https://comtradeapi.un.org/public/v1/preview/C/A/HS';

    private const OMAN = 512;

    /** @var array<int, string> رمز وحدة الكمية => تسميتها بالعربية */
    public const UNITS = [5 => 'قطعة', 6 => 'زوج', 7 => 'لتر', 8 => 'كغم', 2 => 'م²', 4 => 'متر', 9 => 'ألف قطعة', 10 => 'قيراط', 11 => 'م³', 12 => 'غم', 13 => 'رأس', 3 => 'ألف ك.و.س'];

    private float $lastCall = 0.0;

    /** @return array<int, string> رمز M49 => ISO3 */
    public function partners(): array
    {
        return Cache::remember('mi:ct:partners:v1', 30 * 86400, function (): array {
            $out = [];
            foreach (Http::timeout(60)->retry(2, 2000)->get('https://comtradeapi.un.org/files/v1/app/reference/partnerAreas.json')->throw()->json('results') ?? [] as $r) {
                if (! ($r['isGroup'] ?? false) && preg_match('/^[A-Z]{3}$/', (string) ($r['PartnerCodeIsoAlpha3'] ?? ''))) {
                    $out[(int) $r['PartnerCode']] = (string) $r['PartnerCodeIsoAlpha3'];
                }
            }

            return $out;
        });
    }

    /** @return list<array{code: string, text: string}> البنود الفرعية HS6 للمنتج */
    public function hs6(string $hs4): array
    {
        $all = Cache::remember('mi:ct:hs6:v1', 30 * 86400, function (): array {
            $map = [];
            foreach (Http::timeout(120)->retry(2, 3000)->get('https://comtradeapi.un.org/files/v1/app/reference/HS.json')->throw()->json('results') ?? [] as $r) {
                $id = (string) ($r['id'] ?? '');
                if (strlen($id) === 6 && ctype_digit($id)) {
                    $map[substr($id, 0, 4)][] = ['code' => $id, 'text' => trim((string) preg_replace('/^\d+\s*-\s*/', '', (string) ($r['text'] ?? '')))];
                }
            }

            return $map;
        });

        return $all[$hs4] ?? [];
    }

    /**
     * @param  array<string, mixed>  $params
     * @return list<array<string, mixed>>
     */
    private function query(array $params): array
    {
        return Cache::remember('mi:ct:q:v1:'.md5(json_encode($params)), 7 * 86400, function () use ($params): array {
            $wait = 1.3 - (microtime(true) - $this->lastCall);
            if ($wait > 0) {
                usleep((int) ($wait * 1e6));
            }
            $this->lastCall = microtime(true);

            return Http::withHeaders(ApiKeys::comtradeHeaders())->timeout(90)->retry(3, 4000)->get(ApiKeys::comtradeUrl(self::BASE), $params)->throw()->json('data') ?? [];
        });
    }

    /**
     * أسعار الوحدة لمنتج: عُمان حسب الشريك، وكل دول العالم (مُصدِّرين في وضع الاستيراد، مستوردين في وضع التصدير)، وبنود HS6 لعُمان.
     *
     * @param  'import'|'export'  $mode
     * @return array<string, mixed>
     */
    public function unitPrices(string $hs4, string $mode, int $latestYear): array
    {
        $flowOman = $mode === 'import' ? 'M' : 'X';
        $year = null;
        $omanRows = [];
        foreach ([$latestYear, $latestYear - 1, $latestYear - 2] as $y) {
            $omanRows = $this->query(['reporterCode' => self::OMAN, 'period' => $y, 'cmdCode' => $hs4, 'flowCode' => $flowOman]);
            if ($omanRows !== []) {
                $year = $y;
                break;
            }
        }
        $year ??= $latestYear - 1;
        $iso = $this->partners();

        $row = fn (array $r) => [
            'value' => (float) ($r['primaryValue'] ?? 0), 'qty' => (float) ($r['qty'] ?? 0), 'unit_code' => (int) ($r['qtyUnitCode'] ?? -1),
            'net_kg' => (float) ($r['netWgt'] ?? 0), 'estimated' => (bool) ($r['isQtyEstimated'] ?? false),
        ];
        $price = fn (array $x) => $x['qty'] > 0 && $x['value'] > 0 ? $x['value'] / $x['qty'] : null;

        $byPartner = [];
        $total = null;
        foreach ($omanRows as $r) {
            if (($r['customsCode'] ?? 'C00') !== 'C00' || (int) ($r['motCode'] ?? 0) !== 0 || (int) ($r['partner2Code'] ?? 0) !== 0) {
                continue;
            }
            $x = $row($r);
            if ((int) $r['partnerCode'] === 0) {
                $total = $x;

                continue;
            }
            $code = $iso[(int) $r['partnerCode']] ?? null;
            if ($code && $x['value'] > 0) {
                $byPartner[] = ['iso' => $code] + $x + ['unit_price' => $price($x), 'kg_per_unit' => $x['qty'] > 0 && $x['net_kg'] > 0 ? $x['net_kg'] / $x['qty'] : null];
            }
        }
        usort($byPartner, fn ($a, $b) => $b['value'] <=> $a['value']);

        $world = [];
        foreach ($this->query(['period' => $year, 'cmdCode' => $hs4, 'flowCode' => $mode === 'import' ? 'X' : 'M', 'partnerCode' => 0]) as $r) {
            if (($r['customsCode'] ?? 'C00') !== 'C00' || (int) ($r['motCode'] ?? 0) !== 0 || (int) ($r['partner2Code'] ?? 0) !== 0) {
                continue;
            }
            $x = $row($r);
            $code = $iso[(int) ($r['reporterCode'] ?? 0)] ?? null;
            if ($code && $x['value'] > 0) {
                $world[$code] = $x + ['unit_price' => $price($x)];
            }
        }

        $children = $this->hs6($hs4);
        $hs6 = [];
        if ($children !== []) {
            $names = array_column($children, 'text', 'code');
            foreach ($this->query(['reporterCode' => self::OMAN, 'period' => $year, 'cmdCode' => implode(',', array_column($children, 'code')), 'flowCode' => $flowOman, 'partnerCode' => 0]) as $r) {
                if (($r['customsCode'] ?? 'C00') !== 'C00' || (int) ($r['motCode'] ?? 0) !== 0 || (int) ($r['partner2Code'] ?? 0) !== 0) {
                    continue;
                }
                $x = $row($r);
                if ($x['value'] > 0) {
                    $hs6[] = ['code' => (string) $r['cmdCode'], 'text' => $names[(string) $r['cmdCode']] ?? (string) $r['cmdCode']] + $x + ['unit_price' => $price($x)];
                }
            }
            usort($hs6, fn ($a, $b) => $b['value'] <=> $a['value']);
        }

        // وحدة المنتج: الأكثر قيمةً بين صفوف الشركاء ذات الكمية (صف الإجمالي قد يأتي بلا كمية).
        $byUnit = [];
        foreach ($byPartner as $p) {
            if ($p['unit_code'] > 0 && $p['qty'] > 0) {
                $byUnit[$p['unit_code']] = ($byUnit[$p['unit_code']] ?? 0) + $p['value'];
            }
        }
        arsort($byUnit);
        $unitCode = $byUnit !== [] ? (int) array_key_first($byUnit) : ($total['unit_code'] ?? -1);
        if ((! $total || $total['qty'] <= 0 || $total['unit_code'] !== $unitCode) && $byUnit !== []) {
            $same = array_filter($byPartner, fn ($p) => $p['unit_code'] === $unitCode && $p['qty'] > 0);
            $total = ['value' => array_sum(array_column($same, 'value')), 'qty' => array_sum(array_column($same, 'qty')), 'unit_code' => $unitCode, 'net_kg' => array_sum(array_column($same, 'net_kg')), 'estimated' => true];
        }
        $worldPrices = array_values(array_filter(array_map(fn ($w) => $w['unit_code'] === $unitCode ? $w['unit_price'] : null, $world)));
        sort($worldPrices);

        return [
            'year' => $year, 'unit_code' => $unitCode, 'unit' => self::UNITS[$unitCode] ?? null,
            'oman_total' => $total ? $total + ['unit_price' => $price($total)] : null,
            'oman_by_partner' => array_slice($byPartner, 0, 25),
            'world' => $world,
            'world_median_unit_price' => $worldPrices === [] ? null : $worldPrices[intdiv(count($worldPrices), 2)],
            'world_p25_unit_price' => $worldPrices === [] ? null : $worldPrices[intdiv(count($worldPrices), 4)],
            'hs6' => array_slice($hs6, 0, 20),
        ];
    }
}
