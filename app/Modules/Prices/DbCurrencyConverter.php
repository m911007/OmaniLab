<?php

namespace App\Modules\Prices;

use Illuminate\Support\Facades\DB;

/**
 * تحويل العملات بأسعار الصرف المخزّنة في النظام (fx.usd_* من البنك المركزي الأوروبي عبر Frankfurter) وربط عملات الخليج الرسمي.
 * يعمل داخل حاوية الواجهة المعزولة عن الإنترنت؛ يُرجع null لعملة غير معروفة بدل تخمين سعر.
 */
final class DbCurrencyConverter
{
    /** @var array<string, float> */
    private const PEGS = ['USD' => 1.0, 'OMR' => 0.3845, 'AED' => 3.6725, 'SAR' => 3.75, 'QAR' => 3.64, 'BHD' => 0.376];

    /** @var array<string, float>|null */
    private ?array $rates = null;

    /** @return array<string, float> وحدات العملة لكل دولار */
    public function rates(): array
    {
        if ($this->rates === null) {
            $this->rates = self::PEGS;
            $rows = DB::table('core.indicators as i')->where('i.key', 'like', 'fx.usd_%')
                ->selectRaw('i.key, (SELECT o.value FROM core.observations o WHERE o.indicator_id = i.id ORDER BY o.period DESC LIMIT 1) AS v')->get();
            foreach ($rows as $r) {
                if ($r->v !== null && (float) $r->v > 0) {
                    $this->rates[strtoupper(substr($r->key, 7))] = (float) $r->v;
                }
            }
        }

        return $this->rates;
    }

    public function toOmr(float $amount, string $currency): ?float
    {
        $rate = $this->rates()[strtoupper($currency)] ?? null;

        return $rate ? $amount / $rate * self::PEGS['OMR'] : null;
    }
}
