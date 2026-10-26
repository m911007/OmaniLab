<?php

namespace App\Modules\Prices;

use Illuminate\Support\Facades\Http;

/** أسعار ألعاب Steam لكل بلد عبر واجهة بحث المتجر العامة المجانية (السعر بعملة المتجر المحلية). */
final class SteamProvider implements ShoppingProvider
{
    public function key(): string
    {
        return 'steam';
    }

    public function label(): string
    {
        return 'Steam (ألعاب)';
    }

    public function configured(): bool
    {
        return true;
    }

    public function search(string $query, string $countryIso2, int $limit = 20): array
    {
        $res = Http::timeout(30)->retry(2, 1500)->get('https://store.steampowered.com/api/storesearch/', ['term' => $query, 'cc' => strtolower($countryIso2), 'l' => 'arabic'])->throw()->json();
        $out = [];
        foreach ($res['items'] ?? [] as $r) {
            $final = $r['price']['final'] ?? null;
            if (! is_numeric($final) || $final <= 0 || empty($r['name']) || empty($r['price']['currency']) || ! isset($r['id'])) {
                continue;
            }
            $out[] = [
                'title' => (string) $r['name'], 'store' => 'Steam', 'price' => $final / 100, 'currency' => strtoupper((string) $r['price']['currency']),
                'link' => 'https://store.steampowered.com/app/'.(int) $r['id'], 'thumbnail' => isset($r['tiny_image']) && str_starts_with((string) $r['tiny_image'], 'https://') ? $r['tiny_image'] : null,
                'rating' => null, 'reviews' => null, 'delivery' => null,
            ];
        }

        return array_slice($out, 0, $limit);
    }
}
