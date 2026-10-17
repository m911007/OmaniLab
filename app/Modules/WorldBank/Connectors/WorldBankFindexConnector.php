<?php

namespace App\Modules\WorldBank\Connectors;

/**
 * الشمول المالي (Global Findex، البنك الدولي): نسبة البالغين الذين لديهم حساب مصرفي (إجمالًا وللنساء والرجال)، والاقتراض، وحيازة بطاقة الخصم.
 * مسح دوري (2011، 2014، 2017، 2021، 2024) فسلاسله متقطعة.
 */
final class WorldBankFindexConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.findex';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: الشمول المالي (Findex)';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: financial inclusion (Global Findex)';
    }

    protected function section(): string
    {
        return 'finance';
    }

    protected function sourceId(): ?int
    {
        return 28;
    }

    protected function sourceTitle(): string
    {
        return 'Global Findex database';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::share('account.t.d', 'من لديهم حساب مصرفي (15+)', 'Account ownership (age 15+)', '% من البالغين'),
            WbIndicator::share('account.t.d.1', 'النساء اللاتي لديهن حساب مصرفي', 'Account ownership, women', '% من النساء'),
            WbIndicator::share('account.t.d.2', 'الرجال الذين لديهم حساب مصرفي', 'Account ownership, men', '% من الرجال'),
            WbIndicator::share('borrow.any.t.d', 'من اقترضوا خلال السنة', 'Borrowed any money in the past year', '% من البالغين'),
            WbIndicator::share('fin2.t.d', 'من يملكون بطاقة خصم', 'Owns a debit card', '% من البالغين'),
        ];
    }
}
