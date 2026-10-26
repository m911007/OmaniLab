<?php

namespace App\Modules\Prices;

use Illuminate\Support\Facades\DB;

/** مقارنة أسعار منتج في عدة أسواق: عروض المتاجر بالريال العُماني مع إحصاءات لكل بلد (الأدنى والوسيط) وأفضل عرض. */
final class PriceComparison
{
    public const MIN_RELEVANCE = 0.5;

    /** نسبة كلمات البحث المهمة الموجودة في عنوان العرض (0–1)؛ بحث بكلمة واحدة يقبل أي تطابق جزئي. */
    public static function relevance(string $query, string $title): float
    {
        $norm = fn (string $s) => mb_strtolower(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $s) ?? $s);
        $stop = ['the', 'and', 'for', 'with', 'of', 'in', 'edition', 'standard', 'new', 'pc', 'من', 'في', 'مع'];
        $words = array_values(array_unique(array_filter(explode(' ', $norm($query)), fn ($w) => mb_strlen($w) >= 2 && ! in_array($w, $stop, true))));
        if ($words === []) {
            return 1.0;
        }
        $t = ' '.$norm($title).' ';
        $hits = count(array_filter($words, fn ($w) => str_contains($t, $w)));

        return $hits / count($words);
    }

    /** كل المزودين: المجانية دائمًا وGoogle Shopping إن ضُبط مفتاحه. */
    public static function defaultProviders(): self
    {
        return new self([new SerpApiShoppingProvider, new AppleStoreProvider, new SteamProvider, new OpenPricesProvider], new CurrencyConverter);
    }

    /** @param  list<ShoppingProvider>  $providers */
    public function __construct(private array $providers, private CurrencyConverter $fx) {}

    /**
     * @param  list<string>  $countries  رموز ISO2
     * @return array{offers: list<array<string, mixed>>, by_country: list<array<string, mixed>>, summary: array<string, mixed>, errors: list<string>, sources: list<array<string, mixed>>}
     */
    public function compare(string $query, array $countries): array
    {
        $offers = [];
        $errors = [];
        $sources = [];
        foreach ($this->providers as $provider) {
            if (! $provider->configured()) {
                continue;
            }
            $sources[$provider->key()] = ['key' => $provider->key(), 'label' => $provider->label(), 'count' => 0];
            foreach ($countries as $c) {
                try {
                    foreach ($provider->search($query, $c, 20) as $o) {
                        $rel = self::relevance($query, $o['title']);
                        if ($rel < self::MIN_RELEVANCE) {
                            continue; // بحث المتاجر يعيد أحيانًا ما لا علاقة له (أفلام وكتب باسم مشابه)
                        }
                        $omr = $this->fx->toOmr($o['price'], $o['currency']);
                        if ($omr === null) {
                            continue;
                        }
                        $offers[] = $o + ['country' => $c, 'country_ar' => Countries::ALL[$c][0], 'omr' => round($omr, 3), 'source' => $provider->key(), 'relevance' => round($rel, 2)];
                        $sources[$provider->key()]['count']++;
                    }
                } catch (\Throwable $e) {
                    report($e);
                    $errors[] = $provider->label().' — '.Countries::ALL[$c][0];
                }
            }
        }
        usort($offers, fn ($a, $b) => $a['omr'] <=> $b['omr']);

        return ['offers' => $offers] + $this->stats($offers) + ['errors' => $errors, 'sources' => array_values($sources)];
    }

    /**
     * يحفظ لقطة لكل (دولة، مصدر) ولكل دولة مجمّعة (source = all) لتاريخ السعر.
     *
     * @param  array<string, mixed>  $result
     */
    public static function snapshot(string $query, array $result): void
    {
        $groups = [];
        foreach ($result['offers'] ?? [] as $o) {
            $groups[$o['country'].'|'.$o['source']][] = $o['omr'];
            $groups[$o['country'].'|all'][] = $o['omr'];
        }
        $rows = [];
        foreach ($groups as $k => $v) {
            [$country, $source] = explode('|', $k);
            sort($v);
            $n = count($v);
            $rows[] = ['query' => mb_substr($query, 0, 160), 'country' => $country, 'source' => $source, 'n_offers' => $n, 'min_omr' => $v[0],
                'median_omr' => $n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2, 'fetched_at' => now()];
        }
        if ($rows !== []) {
            DB::table('intel.price_snapshots')->insert($rows);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $offers
     * @return array{by_country: list<array<string, mixed>>, summary: array<string, mixed>}
     */
    public function stats(array $offers): array
    {
        $by = [];
        foreach ($offers as $o) {
            $by[$o['country']][] = $o['omr'];
        }
        $rows = [];
        foreach ($by as $c => $v) {
            sort($v);
            $rows[] = ['country' => $c, 'country_ar' => Countries::ALL[$c][0], 'count' => count($v), 'min' => $v[0], 'median' => $this->median($v), 'max' => end($v)];
        }
        usort($rows, fn ($a, $b) => $a['median'] <=> $b['median']);
        $all = array_column($offers, 'omr');
        $median = $this->median($all);
        $best = $offers[0] ?? null;
        $om = collect($rows)->firstWhere('country', 'OM');

        return ['by_country' => $rows, 'summary' => [
            'count' => count($offers), 'median' => $median, 'min' => $best['omr'] ?? null, 'max' => $all === [] ? null : max($all),
            'best' => $best, 'oman_median' => $om['median'] ?? null,
            'oman_vs_best_country_pct' => ($om && $rows !== [] && $rows[0]['median'] > 0) ? round(($om['median'] - $rows[0]['median']) / $rows[0]['median'] * 100, 1) : null,
        ]];
    }

    /** @param  list<float>  $v */
    private function median(array $v): ?float
    {
        if ($v === []) {
            return null;
        }
        sort($v);
        $n = count($v);

        return round($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2, 3);
    }
}
