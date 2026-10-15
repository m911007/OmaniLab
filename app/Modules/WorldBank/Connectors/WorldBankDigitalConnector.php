<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankDigitalConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.digital';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: الاقتصاد الرقمي والابتكار والطاقة';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: digital economy, innovation and energy';
    }

    protected function section(): string
    {
        return 'digital';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::share('IT.NET.USER.ZS', 'مستخدمو الإنترنت', 'Individuals using the Internet', '% من السكان'),
            WbIndicator::value('IT.CEL.SETS.P2', 'اشتراكات الهاتف المحمول', 'Mobile cellular subscriptions', 'لكل 100 نسمة', 500),
            WbIndicator::value('IT.NET.BBND.P2', 'اشتراكات النطاق العريض الثابت', 'Fixed broadband subscriptions', 'لكل 100 نسمة', 200),
            WbIndicator::ratio('GB.XPD.RSDV.GD.ZS', 'الإنفاق على البحث والتطوير', 'Research and development expenditure', '% من الناتج المحلي'),
            WbIndicator::level('IP.PAT.RESD', 'طلبات براءات الاختراع (المقيمون)', 'Patent applications, residents', 'عدد'),
            WbIndicator::value('EG.USE.PCAP.KG.OE', 'استهلاك الطاقة للفرد', 'Energy use per capita', 'كغم مكافئ نفط', 1e6),
            WbIndicator::level('AG.LND.TOTL.K2', 'المساحة الأرضية', 'Land area', 'كم²'),
        ];
    }
}
