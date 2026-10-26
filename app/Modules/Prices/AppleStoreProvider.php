<?php

namespace App\Modules\Prices;

use Illuminate\Support\Facades\Http;

/** أسعار متجر آبل (تطبيقات وموسيقى وأفلام وكتب) لكل بلد عبر iTunes Search API العامة المجانية بلا مفتاح. */
final class AppleStoreProvider implements ShoppingProvider
{
    public function key(): string
    {
        return 'apple';
    }

    public function label(): string
    {
        return 'متجر آبل (iTunes)';
    }

    public function configured(): bool
    {
        return true;
    }

    public function search(string $query, string $countryIso2, int $limit = 20): array
    {
        $res = Http::timeout(30)->retry(2, 1500)->get('https://itunes.apple.com/search', ['term' => $query, 'country' => strtolower($countryIso2), 'limit' => min(50, $limit + 10)])->throw()->json();
        $out = [];
        foreach ($res['results'] ?? [] as $r) {
            $price = $r['price'] ?? $r['trackPrice'] ?? $r['collectionPrice'] ?? null;
            $title = $r['trackName'] ?? $r['collectionName'] ?? null;
            if (! is_numeric($price) || $price <= 0 || ! $title || empty($r['currency'])) {
                continue;
            }
            $link = $r['trackViewUrl'] ?? $r['collectionViewUrl'] ?? null;
            $out[] = [
                'title' => (string) $title, 'store' => (string) ($r['sellerName'] ?? $r['artistName'] ?? 'Apple'), 'price' => (float) $price, 'currency' => strtoupper((string) $r['currency']),
                'link' => is_string($link) && str_starts_with($link, 'https://') ? $link : null, 'thumbnail' => isset($r['artworkUrl100']) && str_starts_with((string) $r['artworkUrl100'], 'https://') ? $r['artworkUrl100'] : null,
                'rating' => isset($r['averageUserRating']) && is_numeric($r['averageUserRating']) ? (float) $r['averageUserRating'] : null,
                'reviews' => isset($r['userRatingCount']) && is_numeric($r['userRatingCount']) ? (int) $r['userRatingCount'] : null, 'delivery' => null,
            ];
        }

        return array_slice($out, 0, $limit);
    }
}
