<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Range;

/**
 * ميزان المدفوعات لعُمان (الحساب الجاري وتفاصيله) من إحصاءات البنك المركزي العُماني كما ينشرها المركز الوطني للإحصاء — سنوي.
 * بالمليون ريال عُماني كما في المصدر: المدفوعات (الواردات، دخل الاستثمار المدفوع…) بإشارة سالبة. تفصيل الصادرات حسب الغاز والنفط وغير النفطي
 * وإعادة التصدير لا يتوفر في المصادر العالمية.
 */
final class NcsiBalanceOfPaymentsConnector extends KnoemaConnector
{
    private const CURRENT_ACCOUNT = 1000000;

    protected function knoemaDatasetId(): string
    {
        return 'jabivub';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.balance_of_payments';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: ميزان المدفوعات (الحساب الجاري)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman balance of payments (current account)';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(-1e7, 1e7, 'reject')];
        $filters = ['account-type' => self::CURRENT_ACCOUNT];
        $unit = 'مليون ريال عُماني';
        $s = fn (string $code, string $ar, string $en, int $member) => new KnoemaSeries($code, $ar, $en, $unit, 'A', $member, $rules(), $filters);

        return [
            $s('BOP_CURRENT_ACCOUNT', 'رصيد الحساب الجاري', 'Current account balance', 1000000),
            $s('BOP_GOODS_SERVICES', 'رصيد السلع والخدمات', 'Goods and services balance', 1000010),
            $s('BOP_GOODS', 'رصيد السلع', 'Goods balance', 1000020),
            $s('BOP_EXPORTS_FOB', 'الصادرات السلعية (فوب)', 'Goods exports (FOB)', 1000030),
            $s('BOP_EXPORTS_OIL', 'صادرات النفط', 'Oil exports', 1000040),
            $s('BOP_EXPORTS_CRUDE', 'صادرات النفط الخام', 'Crude oil exports', 1000050),
            $s('BOP_EXPORTS_REFINED', 'صادرات المشتقات النفطية', 'Refined oil exports', 1000060),
            $s('BOP_EXPORTS_GAS', 'صادرات الغاز', 'Gas exports', 1000070),
            $s('BOP_EXPORTS_NON_OIL', 'الصادرات غير النفطية', 'Non-oil exports', 1000080),
            $s('BOP_REEXPORTS', 'إعادة التصدير', 'Re-exports', 1000090),
            $s('BOP_IMPORTS_FOB', 'الواردات السلعية (فوب، سالب)', 'Goods imports (FOB, negative)', 1000100),
            $s('BOP_SERVICES', 'رصيد الخدمات', 'Services balance', 1000110),
            $s('BOP_SERVICES_EXPORTS', 'صادرات الخدمات', 'Services exports', 1000120),
            $s('BOP_SERVICES_EXPORTS_TRAVEL', 'إيرادات السفر والسياحة', 'Travel receipts', 1000140),
            $s('BOP_SERVICES_IMPORTS', 'واردات الخدمات (سالب)', 'Services imports (negative)', 1000200),
            $s('BOP_PRIMARY_INCOME', 'رصيد الدخل الأولي', 'Primary income balance', 1000280),
            $s('BOP_SECONDARY_INCOME', 'رصيد الدخل الثانوي', 'Secondary income balance', 1000410),
            $s('BOP_WORKERS_REMITTANCES', 'تحويلات العاملين (سالب = خارج)', "Workers' remittances (negative = outflow)", 1000440),
        ];
    }
}
