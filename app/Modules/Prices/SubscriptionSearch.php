<?php

namespace App\Modules\Prices;

use App\Modules\MarketIntel\AiAdvisor;

/**
 * أسعار الاشتراكات (بث، موسيقى، اتصالات، إنترنت، برامج…) في عدة دول، يستخرجها الذكاء الاصطناعي من صفحات المزوّدين العامة عبر أداة البحث في الويب
 * (تحترم robots وشروط المواقع من جهة المزوّد، لا من كشطنا). لا مصدر رسمي موحّد لهذه الأسعار، فكل صف يحمل رابط صفحته وتاريخ التحقق،
 * وتُرفض الصفوف بلا رابط https أو بسعر غير رقمي، وتُوسَم النتيجة «مستخرَجة بالذكاء الاصطناعي» لا حقيقة مؤكَّدة.
 */
final class SubscriptionSearch
{
    public function __construct(private AiAdvisor $ai, private CurrencyConverter $fx) {}

    /**
     * @param  list<string>  $countries  رموز ISO2
     * @return array{offers: list<array<string, mixed>>, note: ?string}
     */
    public function search(string $service, array $countries): array
    {
        $names = implode('، ', array_map(fn ($c) => "{$c} ({$this->arName($c)})", $countries));
        $system = 'أنت باحث أسعار دقيق. استخدم أداة البحث في الويب للوصول إلى الصفحات الرسمية العامة للمزوّد فقط (صفحة الأسعار/الباقات الرسمية لكل بلد)، ولا تعتمد على مواقع طرف ثالث إلا إن لم توجد صفحة رسمية. '
            .'أعد JSON فقط بلا أي نص خارجه: {"offers":[{"country":"ISO2","plan":"اسم الباقة بالعربية أو الإنجليزية","price":رقم,"currency":"ISO 3 حروف","period":"month|year|one_time","url":"https://صفحة المصدر","as_of":"YYYY-MM-DD تاريخ ظهور السعر في الصفحة إن عُرف وإلا تاريخ اليوم","notes":"شروط مهمة كالسعر شامل الضريبة أو العرض الترويجي أو null"}],"note":"تنبيه عام أو null"}. '
            .'لا تخترع سعرًا: إن لم تجد سعر بلد فاتركه خارج القائمة. اذكر كل باقة رئيسية متاحة في البلد (حتى 4 باقات لكل بلد).';
        $text = $this->ai->call($system, "الخدمة: {$service}\nالدول: {$names}", 4000, [['type' => 'web_search_20250305', 'name' => 'web_search', 'max_uses' => 8]]);
        if (! preg_match('/\{.*\}/s', $text, $m)) {
            throw new \RuntimeException('رد الذكاء الاصطناعي بلا JSON.');
        }
        $j = json_decode($m[0], true, flags: JSON_THROW_ON_ERROR);

        return ['offers' => $this->clean(is_array($j['offers'] ?? null) ? $j['offers'] : [], $countries), 'note' => isset($j['note']) ? (string) $j['note'] : null];
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<string>  $countries
     * @return list<array<string, mixed>>
     */
    public function clean(array $rows, array $countries): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (! is_array($r)) {
                continue;
            }
            $country = strtoupper((string) ($r['country'] ?? ''));
            $currency = strtoupper((string) ($r['currency'] ?? ''));
            $url = (string) ($r['url'] ?? '');
            $period = in_array($r['period'] ?? '', ['month', 'year', 'one_time'], true) ? $r['period'] : null;
            if (! in_array($country, $countries, true) || ! is_numeric($r['price'] ?? null) || (float) $r['price'] < 0 || ! preg_match('/^[A-Z]{3}$/', $currency) || ! str_starts_with($url, 'https://') || $period === null) {
                continue;
            }
            $price = (float) $r['price'];
            $omr = $this->fx->toOmr($price, $currency);
            $monthly = $omr === null ? null : ($period === 'year' ? $omr / 12 : ($period === 'month' ? $omr : null));
            $out[] = [
                'country' => $country, 'country_ar' => $this->arName($country), 'plan' => mb_substr((string) ($r['plan'] ?? ''), 0, 80), 'price' => $price, 'currency' => $currency, 'period' => $period,
                'omr' => $omr === null ? null : round($omr, 3), 'omr_monthly' => $monthly === null ? null : round($monthly, 3), 'url' => $url,
                'as_of' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($r['as_of'] ?? '')) ? $r['as_of'] : null, 'notes' => isset($r['notes']) && $r['notes'] !== null ? mb_substr((string) $r['notes'], 0, 200) : null,
            ];
        }
        usort($out, fn ($a, $b) => ($a['omr_monthly'] ?? INF) <=> ($b['omr_monthly'] ?? INF));

        return $out;
    }

    private function arName(string $iso2): string
    {
        return Countries::ALL[$iso2][0] ?? $iso2;
    }
}
