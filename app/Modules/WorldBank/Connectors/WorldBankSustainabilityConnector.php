<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankSustainabilityConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.sustainability';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: الاستدامة والكهرباء والمياه والغذاء';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: sustainability, electricity, water and food';
    }

    protected function section(): string
    {
        return 'energy';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::ratio('NY.ADJ.SVNG.GN.ZS', 'المدخرات الصافية المعدَّلة (تشمل استنزاف الموارد)', 'Adjusted net savings', '% من الدخل القومي'),
            WbIndicator::share('NY.ADJ.DRES.GN.ZS', 'استنزاف الموارد الطبيعية', 'Natural resources depletion', '% من الدخل القومي'),
            WbIndicator::share('NY.ADJ.DNGY.GN.ZS', 'استنزاف الطاقة', 'Energy depletion', '% من الدخل القومي'),
            WbIndicator::share('EG.ELC.FOSL.ZS', 'الكهرباء من الوقود الأحفوري', 'Electricity from oil, gas and coal', '% من إنتاج الكهرباء'),
            WbIndicator::share('EG.ELC.NGAS.ZS', 'الكهرباء من الغاز الطبيعي', 'Electricity from natural gas', '% من إنتاج الكهرباء'),
            WbIndicator::share('EG.ELC.RNEW.ZS', 'الكهرباء المتجددة', 'Renewable electricity output', '% من إنتاج الكهرباء'),
            WbIndicator::value('EG.USE.COMM.GD.PP.KD', 'كثافة استخدام الطاقة', 'Energy use per $1,000 GDP', 'كغم مكافئ نفط', 5000),
            WbIndicator::ratio('ER.H2O.FWST.ZS', 'الإجهاد المائي (السحب إلى الموارد المتجددة)', 'Level of water stress', '%'),
            WbIndicator::value('ER.H2O.INTR.PC', 'الموارد المائية المتجددة للفرد', 'Renewable internal freshwater per capita', 'م³', 1e7),
            WbIndicator::share('SH.H2O.SMDW.ZS', 'مياه الشرب المأمونة', 'Safely managed drinking water', '% من السكان'),
            WbIndicator::value('AG.PRD.FOOD.XD', 'مؤشر الإنتاج الغذائي (2014–2016 = 100)', 'Food production index', 'مؤشر', 1000),
            WbIndicator::share('SN.ITK.DEFC.ZS', 'سوء التغذية', 'Prevalence of undernourishment', '% من السكان'),
        ];
    }
}
