<?php

namespace App\Modules\Prices;

use App\Core\Support\Secrets;
use Illuminate\Support\Facades\Http;

/** أسعار Google Shopping الحية عبر SerpAPI (خدمة مرخّصة بمفتاح SERPAPI_KEY)؛ تُرجع عروض المتاجر بحسب بلد البحث. */
final class SerpApiShoppingProvider implements ShoppingProvider
{
    /** @var array<string, string> رمز/رمز عملة ظاهر في نص السعر => رمز ISO */
    private const SYMBOLS = ['د.إ' => 'AED', 'AED' => 'AED', 'ر.س' => 'SAR', 'SAR' => 'SAR', 'ر.ع' => 'OMR', 'OMR' => 'OMR', 'ر.ق' => 'QAR', 'QAR' => 'QAR', 'د.ك' => 'KWD', 'KWD' => 'KWD', 'د.ب' => 'BHD', 'BHD' => 'BHD', '£' => 'GBP', '€' => 'EUR', '₹' => 'INR', '¥' => 'JPY', '₺' => 'TRY', 'EGP' => 'EGP', 'ج.م' => 'EGP', 'US$' => 'USD', 'CA$' => 'CAD', 'A$' => 'AUD', 'S$' => 'SGD', '$' => 'USD'];

    public function key(): string
    {
        return 'google';
    }

    public function label(): string
    {
        return 'Google Shopping (SerpAPI)';
    }

    public function configured(): bool
    {
        return Secrets::has('serpapi_key');
    }

    public function search(string $query, string $countryIso2, int $limit = 20): array
    {
        $res = Http::timeout(60)->retry(2, 2000)->get('https://serpapi.com/search.json', [
            'engine' => 'google_shopping', 'q' => $query, 'gl' => strtolower($countryIso2), 'hl' => 'ar', 'num' => max(10, $limit),
            'api_key' => Secrets::get('serpapi_key'),
        ])->throw()->json();

        $out = [];
        foreach (array_slice($res['shopping_results'] ?? [], 0, $limit) as $r) {
            $price = $r['extracted_price'] ?? null;
            if (! is_numeric($price) || $price <= 0 || empty($r['title'])) {
                continue;
            }
            $out[] = [
                'title' => (string) $r['title'], 'store' => (string) ($r['source'] ?? 'غير معروف'), 'price' => (float) $price,
                'currency' => $this->currency((string) ($r['price'] ?? ''), $countryIso2),
                'link' => $this->https($r['product_link'] ?? $r['link'] ?? null), 'thumbnail' => $this->https($r['thumbnail'] ?? null),
                'rating' => isset($r['rating']) && is_numeric($r['rating']) ? (float) $r['rating'] : null,
                'reviews' => isset($r['reviews']) && is_numeric($r['reviews']) ? (int) $r['reviews'] : null,
                'delivery' => isset($r['delivery']) ? (string) $r['delivery'] : null,
            ];
        }

        return $out;
    }

    /** العملة من نص السعر؛ وإلا عملة البلد المحلية. */
    public function currency(string $priceText, string $countryIso2): string
    {
        foreach (self::SYMBOLS as $symbol => $code) {
            if ($symbol !== '$' && str_contains($priceText, $symbol)) {
                return $code;
            }
        }
        if (str_contains($priceText, '$') && in_array(strtoupper($countryIso2), ['US', 'CA', 'AU', 'SG'], true)) {
            return Countries::ALL[strtoupper($countryIso2)][1];
        }

        return str_contains($priceText, '$') ? 'USD' : (Countries::ALL[strtoupper($countryIso2)][1] ?? 'USD');
    }

    private function https(mixed $url): ?string
    {
        return is_string($url) && str_starts_with($url, 'https://') ? $url : null;
    }
}
