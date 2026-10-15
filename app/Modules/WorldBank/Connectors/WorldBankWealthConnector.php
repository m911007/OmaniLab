<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankWealthConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.wealth';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: حسابات الثروة (رأس المال)';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: wealth accounts';
    }

    protected function section(): string
    {
        return 'development';
    }

    protected function sourceId(): int
    {
        return 59;
    }

    protected function sourceTitle(): string
    {
        return 'Wealth Accounts';
    }

    protected function intervalMinutes(): int
    {
        return 14 * 24 * 60;
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::ratio('NW.TOW.PC', 'الثروة الشاملة للفرد', 'Comprehensive wealth per capita', 'دولار ثابت 2019'),
            WbIndicator::ratio('NW.HCA.PC', 'رأس المال البشري للفرد', 'Human capital per capita', 'دولار ثابت 2019'),
            WbIndicator::ratio('NW.PCA.PC', 'رأس المال المنتَج للفرد', 'Produced capital per capita', 'دولار ثابت 2019'),
            WbIndicator::ratio('NW.NCA.TOTL.PC', 'رأس المال الطبيعي المتجدد للفرد', 'Renewable natural capital per capita', 'دولار ثابت 2019'),
            WbIndicator::ratio('NW.NCA.SSOI.PC', 'رأس المال الطبيعي غير المتجدد للفرد (نفط وغاز ومعادن)', 'Nonrenewable natural capital per capita', 'دولار ثابت 2019'),
            WbIndicator::ratio('NW.NFA.PC', 'الأصول الأجنبية الصافية للفرد', 'Net foreign assets per capita', 'دولار ثابت 2019'),
        ];
    }
}
