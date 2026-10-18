<?php

namespace App\Modules\WorldBank\Connectors;

/**
 * الاقتصاد الرقمي (البنك الدولي): صادرات خدمات الاتصالات والحاسوب بالدولار، وتجارة سلع تقنية المعلومات، والباحثون،
 * وطلبات البراءات لغير المقيمين، والهاتف الثابت. تكمل مستخدمي الإنترنت والنطاق العريض والجوال الموجودة في مجموعات أخرى.
 */
final class WorldBankIctConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.ict';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: تجارة التقنية والباحثون والبراءات';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: ICT trade, researchers and patents';
    }

    protected function section(): string
    {
        return 'digital';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::level('BX.GSR.CCIS.CD', 'صادرات خدمات الاتصالات والحاسوب والمعلومات (بالدولار)', 'ICT service exports (BoP, current US$)', 'دولار جارٍ'),
            WbIndicator::share('TX.VAL.ICTG.ZS.UN', 'صادرات سلع تقنية المعلومات', 'ICT goods exports', '% من صادرات السلع'),
            WbIndicator::share('TM.VAL.ICTG.ZS.UN', 'واردات سلع تقنية المعلومات', 'ICT goods imports', '% من واردات السلع'),
            WbIndicator::level('IP.PAT.NRES', 'طلبات البراءات (غير المقيمين)', 'Patent applications, non-residents', 'طلب'),
            WbIndicator::value('IT.MLT.MAIN.P2', 'اشتراكات الهاتف الثابت (لكل 100 نسمة)', 'Fixed telephone subscriptions (per 100)', 'لكل 100', 300),
        ];
    }
}
