<?php

namespace App\Modules\Prices;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * تحويل العملات إلى الريال العُماني بأسعار الصرف الحالية (open.er-api.com ثم Frankfurter احتياطًا). الريال العُماني مربوط بالدولار (0.3845).
 * عملات الخليج المربوطة (الدرهم والريال السعودي والقطري والدينار البحريني) تُثبَّت بربطها الرسمي ولا تتأثر بانقطاع المصدر.
 */
final class CurrencyConverter
{
    public const OMR_PER_USD = 0.3845;

    /** @var array<string, float> وحدة العملة لكل دولار أمريكي (ربط رسمي) */
    private const PEGS = ['USD' => 1.0, 'OMR' => 0.3845, 'AED' => 3.6725, 'SAR' => 3.75, 'QAR' => 3.64, 'BHD' => 0.376];

    /** @return array<string, float> */
    public function rates(): array
    {
        return Cache::remember('prices:fx:v1', 6 * 3600, function (): array {
            $rates = [];
            try {
                $res = Http::timeout(30)->retry(2, 1500)->get('https://open.er-api.com/v6/latest/USD')->throw()->json();
                $rates = is_array($res['rates'] ?? null) ? $res['rates'] : [];
            } catch (Throwable) {
                try {
                    $res = Http::timeout(30)->get('https://api.frankfurter.dev/v1/latest', ['base' => 'USD'])->throw()->json();
                    $rates = is_array($res['rates'] ?? null) ? $res['rates'] : [];
                } catch (Throwable $e) {
                    report($e);
                }
            }

            return array_map('floatval', array_filter($rates, 'is_numeric')) + self::PEGS;
        });
    }

    /** المبلغ بالريال العُماني، أو null إن لم تُعرف العملة. */
    public function toOmr(float $amount, string $currency): ?float
    {
        $rates = [...$this->rates(), ...self::PEGS];
        $rate = $rates[strtoupper($currency)] ?? null;

        return $rate ? $amount / $rate * self::OMR_PER_USD : null;
    }
}
