<?php

namespace App\Modules\Prices;

use App\Core\Support\Secrets;
use Illuminate\Support\Facades\Http;

/**
 * مقارنة سعر منتج في المتاجر الإلكترونية الكبرى: أمازون (الإمارات والسعودية وأمريكا وبريطانيا)، وإيباي، ووولمارت، والمتاجر الصينية
 * (AliExpress وTemu وAlibaba وShein وDHgate…) والخليجية (نون وغيرها) كما تظهر في Google Shopping. كلها عبر SerpAPI المرخّصة،
 * فلا كشط مباشر لمواقع المتاجر. الأسعار بالريال العُماني لتُقارن بسعر الوحدة الجمركي.
 */
final class MarketplaceSearch
{
    private const URL = 'https://serpapi.com/search.json';

    /** @var array<string, string> نطاق أمازون => عملته */
    private const AMAZON = ['amazon.ae' => 'AED', 'amazon.sa' => 'SAR', 'amazon.com' => 'USD', 'amazon.co.uk' => 'GBP'];

    /** @var array<string, array{0: string, 1: string}> نمط اسم المتجر => [المنصة، المجموعة] */
    private const PLATFORMS = [
        'aliexpress' => ['AliExpress', 'china'], 'temu' => ['Temu', 'china'], 'alibaba' => ['Alibaba', 'china'], 'shein' => ['SHEIN', 'china'], 'dhgate' => ['DHgate', 'china'],
        'banggood' => ['Banggood', 'china'], 'made-in-china' => ['Made-in-China', 'china'], 'jd.com' => ['JD', 'china'], 'tmall' => ['Tmall', 'china'], 'lightinthebox' => ['LightInTheBox', 'china'], 'wish' => ['Wish', 'china'],
        'amazon' => ['Amazon', 'amazon'], 'ebay' => ['eBay', 'ebay'], 'walmart' => ['Walmart', 'walmart'],
        'noon' => ['noon', 'gulf'], 'lulu' => ['Lulu', 'gulf'], 'carrefour' => ['Carrefour', 'gulf'], 'sharaf' => ['Sharaf DG', 'gulf'], 'jarir' => ['Jarir', 'gulf'], 'extra' => ['eXtra', 'gulf'], 'namshi' => ['Namshi', 'gulf'], 'xcite' => ['Xcite', 'gulf'], 'virgin' => ['Virgin Megastore', 'gulf'],
    ];

    /** @var array<string, string> */
    public const GROUPS = ['amazon' => 'أمازون', 'china' => 'المتاجر الصينية', 'ebay' => 'إيباي', 'walmart' => 'وولمارت', 'gulf' => 'متاجر خليجية', 'other' => 'متاجر أخرى'];

    public function __construct(private CurrencyConverter $fx) {}

    public function configured(): bool
    {
        return Secrets::has('serpapi_key');
    }

    /** @return array{offers: list<array<string, mixed>>, groups: list<array<string, mixed>>, errors: list<string>} */
    public function search(string $query): array
    {
        $offers = [];
        $errors = [];
        $key = Secrets::get('serpapi_key');
        $run = function (string $label, array $params, callable $map) use (&$offers, &$errors, $key): void {
            try {
                $res = Http::timeout(60)->retry(2, 2000)->get(self::URL, $params + ['api_key' => $key])->throw()->json();
                foreach ($map($res) as $o) {
                    if ($o) {
                        $offers[] = $o;
                    }
                }
            } catch (\Throwable $e) {
                report($e);
                $errors[] = $label;
            }
        };

        foreach (self::AMAZON as $domain => $currency) {
            $run($domain, ['engine' => 'amazon', 'k' => $query, 'amazon_domain' => $domain], fn ($r) => array_map(fn ($x) => $this->offer($x['title'] ?? null, $this->num($x['extracted_price'] ?? $x['price'] ?? null), $currency, $x['link'] ?? null, 'Amazon ('.$domain.')', 'amazon', $x['thumbnail'] ?? null, $x['rating'] ?? null, $x['reviews'] ?? null), array_slice($r['organic_results'] ?? [], 0, 10)));
        }
        $run('eBay', ['engine' => 'ebay', '_nkw' => $query, 'ebay_domain' => 'ebay.com'], fn ($r) => array_map(fn ($x) => $this->offer($x['title'] ?? null, $this->num($x['price']['extracted'] ?? $x['price']['from']['extracted'] ?? $x['extracted_price'] ?? null), 'USD', $x['link'] ?? null, 'eBay', 'ebay', $x['thumbnail'] ?? null, null, null), array_slice($r['organic_results'] ?? [], 0, 10)));
        $run('Walmart', ['engine' => 'walmart', 'query' => $query], fn ($r) => array_map(fn ($x) => $this->offer($x['title'] ?? null, $this->num($x['primary_offer']['offer_price'] ?? null), 'USD', $x['product_page_url'] ?? null, 'Walmart', 'walmart', $x['thumbnail'] ?? null, $x['rating'] ?? null, $x['reviews'] ?? null), array_slice($r['organic_results'] ?? [], 0, 8)));
        foreach (['us' => 'USD', 'ae' => 'AED'] as $gl => $currency) {
            $run("Google Shopping ({$gl})", ['engine' => 'google_shopping', 'q' => $query, 'gl' => $gl, 'hl' => 'en'], fn ($r) => array_map(function ($x) use ($currency) {
                [$platform, $group] = $this->platform((string) ($x['source'] ?? ''));

                return $this->offer($x['title'] ?? null, $this->num($x['extracted_price'] ?? null), $currency, $x['product_link'] ?? $x['link'] ?? null, $platform, $group, $x['thumbnail'] ?? null, $x['rating'] ?? null, $x['reviews'] ?? null);
            }, array_slice($r['shopping_results'] ?? [], 0, 30)));
        }

        usort($offers, fn ($a, $b) => $a['omr'] <=> $b['omr']);
        $groups = [];
        foreach (collect($offers)->groupBy('group') as $g => $rows) {
            $p = $rows->pluck('omr')->sort()->values();
            $groups[] = ['group' => $g, 'label' => self::GROUPS[$g] ?? $g, 'count' => $p->count(), 'min' => $p->first(), 'median' => $p[intdiv($p->count(), 2)], 'max' => $p->last(), 'platforms' => $rows->pluck('platform')->unique()->values()->all()];
        }
        usort($groups, fn ($a, $b) => $a['median'] <=> $b['median']);

        return ['offers' => array_slice($offers, 0, 120), 'groups' => $groups, 'errors' => $errors];
    }

    /** @return array{0: string, 1: string} */
    public function platform(string $store): array
    {
        $s = mb_strtolower($store);
        foreach (self::PLATFORMS as $needle => $p) {
            if (str_contains($s, $needle)) {
                return $p;
            }
        }

        return [$store !== '' ? $store : 'غير معروف', 'other'];
    }

    private function num(mixed $v): ?float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        if (is_string($v) && preg_match('/[\d.,]+/', $v, $m)) {
            return (float) str_replace(',', '', $m[0]);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function offer(mixed $title, ?float $price, string $currency, mixed $link, string $platform, string $group, mixed $thumb, mixed $rating, mixed $reviews): ?array
    {
        if (! is_string($title) || $title === '' || $price === null || $price <= 0) {
            return null;
        }
        $omr = $this->fx->toOmr($price, $currency);
        if ($omr === null) {
            return null;
        }

        return [
            'title' => mb_substr($title, 0, 160), 'price' => $price, 'currency' => $currency, 'omr' => round($omr, 3), 'platform' => $platform, 'group' => $group,
            'link' => is_string($link) && str_starts_with($link, 'https://') ? $link : null, 'thumbnail' => is_string($thumb) && str_starts_with($thumb, 'https://') ? $thumb : null,
            'rating' => is_numeric($rating) ? (float) $rating : null, 'reviews' => is_numeric($reviews) ? (int) $reviews : null,
        ];
    }
}
