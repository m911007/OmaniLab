<?php

namespace App\Modules\ValueChain;

/** توحيد الوحدات والعملات النصية (طن/كغم/م³…، دولار/يوان/ريال) لمقارنة الأسعار بوحدة العقدة نفسها. */
final class Units
{
    /** @var array<string, array{0: string, 1: float}> اسم الوحدة => [الوحدة الأساسية، المعامل إليها] */
    private const UNITS = [
        'طن' => ['t', 1], 't' => ['t', 1], 'ton' => ['t', 1], 'tonne' => ['t', 1], 'mt' => ['t', 1],
        'كغم' => ['t', 0.001], 'كجم' => ['t', 0.001], 'kg' => ['t', 0.001],
        'م³' => ['m3', 1], 'م3' => ['m3', 1], 'm3' => ['m3', 1],
        'قطعة' => ['u', 1], 'u' => ['u', 1], 'pcs' => ['u', 1],
        'متر' => ['m', 1], 'm' => ['m', 1], 'كم' => ['m', 1000], 'km' => ['m', 1000],
        'لتر' => ['l', 1], 'l' => ['l', 1], 'برميل' => ['bbl', 1],
    ];

    private const CURRENCIES = ['دولار' => 'USD', 'usd' => 'USD', '$' => 'USD', 'يوان' => 'CNY', 'cny' => 'CNY', 'ريال عماني' => 'OMR', 'ر.ع' => 'OMR', 'omr' => 'OMR', 'يورو' => 'EUR', 'eur' => 'EUR', 'درهم' => 'AED', 'aed' => 'AED', 'ريال سعودي' => 'SAR', 'sar' => 'SAR'];

    /** كم وحدةَ «to» في وحدة «from» (مثلًا من كغم إلى طن = 0.001)؛ null إن اختلف النوع. */
    public static function factor(?string $from, ?string $to): ?float
    {
        $a = self::UNITS[mb_strtolower(trim((string) $from))] ?? null;
        $b = self::UNITS[mb_strtolower(trim((string) $to))] ?? null;
        if (! $a || ! $b || $a[0] !== $b[0]) {
            return null;
        }

        return $a[1] / $b[1];
    }

    /** يفكك «دولار/طن» أو «يوان/طن» إلى [العملة، الوحدة]. */
    public static function parsePriceUnit(?string $unit): ?array
    {
        if (! $unit || ! str_contains($unit, '/')) {
            return null;
        }
        [$cur, $u] = array_map('trim', explode('/', $unit, 2));
        $code = self::CURRENCIES[mb_strtolower($cur)] ?? null;

        return $code ? [$code, $u] : null;
    }
}
