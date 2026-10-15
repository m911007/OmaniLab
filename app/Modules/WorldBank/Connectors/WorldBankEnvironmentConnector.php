<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankEnvironmentConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.environment';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: البيئة والطاقة';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: environment and energy';
    }

    protected function section(): string
    {
        return 'energy';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::value('EN.GHG.CO2.PC.CE.AR5', 'انبعاثات ثاني أكسيد الكربون للفرد', 'CO2 emissions per capita', 'طن مكافئ للفرد', 1000),
            WbIndicator::share('EG.FEC.RNEW.ZS', 'الطاقة المتجددة', 'Renewable energy consumption', '% من الطاقة النهائية'),
            WbIndicator::share('EG.ELC.ACCS.ZS', 'الوصول إلى الكهرباء', 'Access to electricity', '% من السكان'),
            WbIndicator::value('EG.USE.ELEC.KH.PC', 'استهلاك الكهرباء للفرد', 'Electric power consumption per capita', 'كيلوواط ساعة', 1e6),
            WbIndicator::share('AG.LND.FRST.ZS', 'المساحة الحرجية', 'Forest area', '% من الأرض'),
            WbIndicator::value('EN.ATM.PM25.MC.M3', 'تلوث الهواء PM2.5', 'PM2.5 air pollution', 'ميكروغرام/م³', 1000),
        ];
    }
}
