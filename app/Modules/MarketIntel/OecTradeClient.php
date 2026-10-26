<?php

namespace App\Modules\MarketIntel;

use App\Modules\Comtrade\HsChapters;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * عميل واجهة OEC Tesseract العامة (بلا مفتاح) لتجارة المنتجات الثنائية من قاعدة BACI (CEPII): الكمية والقيمة لكل (مُصدِّر، مستورد، منتج HS4، سنة).
 * معرّف المنتج في OEC = رقم القسم + HS4 (مثل 52709 = النفط الخام، القسم 5). النتائج تُخزَّن مؤقتًا لأن BACI سنوية ولا تتغير يوميًا.
 */
final class OecTradeClient
{
    private const BASE = 'https://api-v2.oec.world/tesseract/';

    public const OMAN = 'asomn';

    private const CUBE = 'trade_i_baci_a_92';

    /** @return list<array{id: int, hs4: string, name_en: string, name_ar: string}> */
    public function products(): array
    {
        return Cache::remember('mi:products:v2', 30 * 86400, function (): array {
            $en = $this->members('en');
            $out = [];
            foreach ($en as $id => $name) {
                $hs4 = str_pad(substr((string) $id, -4), 4, '0', STR_PAD_LEFT);
                // OEC لا يترجم أسماء المنتجات، فيُعرَّب فصل HS (خانتان) ويُرفق باسم المنتج الإنجليزي.
                $chapter = HsChapters::AR[substr($hs4, 0, 2)] ?? null;
                $out[] = ['id' => $id, 'hs4' => $hs4, 'name_en' => $name, 'name_ar' => $chapter ? "{$chapter} — {$name}" : $name];
            }

            return $out;
        });
    }

    /** @return array<int, string> */
    private function members(string $locale): array
    {
        $res = Http::timeout(60)->retry(2, 2000)->get(self::BASE.'members', ['cube' => self::CUBE, 'level' => 'HS4', 'locale' => $locale])->throw()->json();
        $out = [];
        foreach ($res['members'] ?? [] as $m) {
            if (isset($m['key'], $m['caption'])) {
                $out[(int) $m['key']] = (string) $m['caption'];
            }
        }
        if ($out === []) {
            throw new RuntimeException('OEC: قائمة المنتجات فارغة.');
        }

        return $out;
    }

    /** آخر سنة متاحة في BACI (تُكتشف بسؤال عن منتج ضخم الحجم). */
    public function latestYear(): int
    {
        return Cache::remember('mi:year:v1', 7 * 86400, function (): int {
            foreach (range((int) date('Y') - 1, (int) date('Y') - 4) as $year) {
                $res = Http::timeout(60)->get(self::BASE.'data.jsonrecords', ['cube' => self::CUBE, 'drilldowns' => 'Year', 'measures' => 'Trade Value', 'HS4' => 52709, 'Year' => $year, 'limit' => 1])->json();
                if (! empty($res['data'])) {
                    return $year;
                }
            }

            return (int) date('Y') - 3;
        });
    }

    /**
     * تجارة منتج: كل المُصدّرين (أو المستوردين) حسب السنة، مع تقييد اختياري بطرف مقابل.
     *
     * @param  'Exporter Country'|'Importer Country'  $by  البُعد المطلوب تفصيله
     * @param  list<int>  $years
     * @return list<array{iso: string, name: string, year: int, value: float, qty: ?float}>
     */
    public function trade(int $productId, string $by, array $years, ?string $otherSideOecId = null): array
    {
        $other = $by === 'Exporter Country' ? 'Importer Country' : 'Exporter Country';
        $params = ['cube' => self::CUBE, 'drilldowns' => "{$by},Year", 'measures' => 'Trade Value,Quantity', 'locale' => 'en', 'HS4' => $productId, 'Year' => implode(',', $years), 'limit' => 5000];
        if ($otherSideOecId !== null) {
            $params[$other] = $otherSideOecId;
        }

        return Cache::remember('mi:trade:v1:'.md5(json_encode($params)), 7 * 86400, function () use ($params, $by): array {
            $res = Http::timeout(120)->retry(3, 3000)->get(self::BASE.'data.jsonrecords', $params)->throw()->json();
            if (! isset($res['data']) || ! is_array($res['data'])) {
                throw new RuntimeException('OEC: تغيّر شكل الاستجابة.');
            }
            $rows = [];
            foreach ($res['data'] as $r) {
                $id = (string) ($r["{$by} ID"] ?? '');
                if (! preg_match('/^[a-z]{2}([a-z]{3})$/', $id, $m) || ! isset($r['Trade Value'], $r['Year'])) {
                    continue;
                }
                $rows[] = ['iso' => strtoupper($m[1]), 'name' => (string) ($r[$by] ?? ''), 'year' => (int) $r['Year'], 'value' => (float) $r['Trade Value'], 'qty' => isset($r['Quantity']) && $r['Quantity'] > 0 ? (float) $r['Quantity'] : null];
            }

            return $rows;
        });
    }

    /**
     * إجمالي تجارة منتج (أو كل المنتجات إن كان null) حسب السنة، مع تقييد اختياري بالمستورد أو المُصدِّر.
     *
     * @param  list<int>  $years
     * @return array<int, array{value: float, qty: ?float}>
     */
    public function yearly(?int $productId, array $years, ?string $importer = null, ?string $exporter = null): array
    {
        $params = ['cube' => self::CUBE, 'drilldowns' => 'Year', 'measures' => $productId ? 'Trade Value,Quantity' : 'Trade Value', 'Year' => implode(',', $years), 'limit' => 100];
        if ($productId) {
            $params['HS4'] = $productId;
        }
        if ($importer) {
            $params['Importer Country'] = $importer;
        }
        if ($exporter) {
            $params['Exporter Country'] = $exporter;
        }

        return Cache::remember('mi:yearly:v1:'.md5(json_encode($params)), 7 * 86400, function () use ($params): array {
            $out = [];
            foreach (Http::timeout(120)->retry(3, 3000)->get(self::BASE.'data.jsonrecords', $params)->throw()->json('data') ?? [] as $r) {
                if (isset($r['Year'], $r['Trade Value'])) {
                    $out[(int) $r['Year']] = ['value' => (float) $r['Trade Value'], 'qty' => isset($r['Quantity']) && $r['Quantity'] > 0 ? (float) $r['Quantity'] : null];
                }
            }
            ksort($out);

            return $out;
        });
    }

    /** @return array<int, float> مؤشر تعقيد المنتج PCI حسب السنة (الأعلى = منتج أعقد يحتاج قدرات أكثر). */
    public function pci(string $hs4): array
    {
        return Cache::remember("mi:pci:v1:{$hs4}", 30 * 86400, function () use ($hs4): array {
            $out = [];
            foreach (Http::timeout(60)->retry(2, 2000)->get(self::BASE.'data.jsonrecords', ['cube' => 'complexity_pci_a_hs92_hs4', 'drilldowns' => 'Year', 'measures' => 'PCI', 'HS4 Official' => $hs4])->throw()->json('data') ?? [] as $r) {
                if (isset($r['Year'], $r['PCI'])) {
                    $out[(int) $r['Year']] = (float) $r['PCI'];
                }
            }
            ksort($out);

            return $out;
        });
    }

    /**
     * الرسوم الجمركية من قاعدة WITS عبر OEC (آخر سنة متاحة، غالبًا 2019): الرسم الذي تفرضه عُمان (MFN والتفضيلي حسب الاتفاقية)،
     * والرسم العام (MFN) الذي تفرضه كل دولة على المنتج.
     *
     * @return array{oman: list<array{agreement: string, tariff: float, year: int}>, mfn_by_country: array<string, array{tariff: float, year: int}>}
     */
    public function tariffs(string $hs4): array
    {
        return Cache::remember("mi:tariffs:v1:{$hs4}", 30 * 86400, function () use ($hs4): array {
            $oman = [];
            foreach (Http::timeout(60)->retry(2, 2000)->get(self::BASE.'data.jsonrecords', ['cube' => 'tariffs_i_wits_a_hs_new', 'drilldowns' => 'Year,Agreement', 'measures' => 'Tariff', 'Reporter Country Official' => 'omn', 'HS4 Official' => $hs4, 'limit' => 200])->throw()->json('data') ?? [] as $r) {
                $a = (string) ($r['Agreement'] ?? '');
                if ($a !== '' && isset($r['Tariff'], $r['Year']) && (! isset($oman[$a]) || $oman[$a]['year'] < (int) $r['Year'])) {
                    $oman[$a] = ['agreement' => $a, 'tariff' => round((float) $r['Tariff'], 2), 'year' => (int) $r['Year']];
                }
            }
            $mfn = [];
            foreach (Http::timeout(90)->retry(2, 2000)->get(self::BASE.'data.jsonrecords', ['cube' => 'tariffs_i_wits_a_hs_new', 'drilldowns' => 'Reporter Country Official,Year', 'measures' => 'Tariff', 'Agreement' => 136, 'HS4 Official' => $hs4, 'limit' => 5000])->throw()->json('data') ?? [] as $r) {
                $iso = strtoupper((string) ($r['Reporter Country Official ID'] ?? ''));
                if (preg_match('/^[A-Z]{3}$/', $iso) && isset($r['Tariff'], $r['Year']) && (! isset($mfn[$iso]) || $mfn[$iso]['year'] < (int) $r['Year'])) {
                    $mfn[$iso] = ['tariff' => round((float) $r['Tariff'], 2), 'year' => (int) $r['Year']];
                }
            }
            usort($oman, fn ($a, $b) => $a['tariff'] <=> $b['tariff']);

            return ['oman' => array_values($oman), 'mfn_by_country' => $mfn];
        });
    }
}
