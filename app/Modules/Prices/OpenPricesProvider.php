<?php

namespace App\Modules\Prices;

use Illuminate\Support\Facades\Http;

/**
 * أسعار البقالة والأغذية المعبأة المسجَّلة بجهود المتطوعين (Open Prices من Open Food Facts، مفتوحة بلا مفتاح): آخر سعر لكل متجر في البلد.
 * تغطيتها جماعية ومتفاوتة بين البلدان (قوية في أوروبا وضعيفة في الخليج)، فقد لا تعيد شيئًا لبلدك.
 */
final class OpenPricesProvider implements ShoppingProvider
{
    private const BASE = 'https://prices.openfoodfacts.org/api/v1/';

    /** @var array<string, list<array<string, mixed>>> */
    private array $memo = [];

    public function key(): string
    {
        return 'openprices';
    }

    public function label(): string
    {
        return 'Open Prices (بقالة، جماعي)';
    }

    public function configured(): bool
    {
        return true;
    }

    public function search(string $query, string $countryIso2, int $limit = 20): array
    {
        $iso = strtoupper($countryIso2);
        $seen = [];
        $out = [];
        foreach ($this->prices($query) as $p) {
            $loc = $p['location'] ?? [];
            if (strtoupper((string) ($loc['osm_address_country_code'] ?? '')) !== $iso || ! is_numeric($p['price'] ?? null) || $p['price'] <= 0 || empty($p['currency'])) {
                continue;
            }
            $store = (string) ($loc['osm_name'] ?? 'متجر');
            $k = ($p['product']['code'] ?? '').'|'.$store;
            if (isset($seen[$k])) {
                continue;
            }
            $seen[$k] = true;
            $code = (string) ($p['product']['code'] ?? '');
            $out[] = [
                'title' => (string) ($p['product']['product_name'] ?? $code), 'store' => $store, 'price' => (float) $p['price'], 'currency' => strtoupper((string) $p['currency']),
                'link' => $code !== '' ? 'https://prices.openfoodfacts.org/products/'.$code : null, 'thumbnail' => isset($p['product']['image_url']) && str_starts_with((string) $p['product']['image_url'], 'https://') ? $p['product']['image_url'] : null,
                'rating' => null, 'reviews' => null, 'delivery' => isset($p['date']) ? 'سُجّل '.$p['date'] : null,
            ];
        }

        return array_slice($out, 0, $limit);
    }

    /** @return list<array<string, mixed>> أحدث أسعار أشهر 3 منتجات تطابق الاسم (تُجلب مرة لكل بحث لا لكل دولة). */
    private function prices(string $query): array
    {
        return $this->memo[$query] ??= $this->fetch($query);
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $query): array
    {
        $products = Http::timeout(30)->retry(2, 1500)->get(self::BASE.'products', ['product_name__like' => $query, 'order_by' => '-price_count', 'size' => 3])->throw()->json('items') ?? [];
        $all = [];
        foreach ($products as $p) {
            if (empty($p['code']) || ($p['price_count'] ?? 0) < 1) {
                continue;
            }
            $items = Http::timeout(30)->get(self::BASE.'prices', ['product_code' => $p['code'], 'order_by' => '-date', 'size' => 100])->json('items') ?? [];
            foreach ($items as $i) {
                $all[] = $i + ['product' => $p];
            }
        }

        return $all;
    }
}
