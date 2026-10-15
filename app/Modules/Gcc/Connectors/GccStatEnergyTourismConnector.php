<?php

namespace App\Modules\Gcc\Connectors;

/** الطاقة والسياحة والمؤشرات المركّبة. */
final class GccStatEnergyTourismConnector extends GccStatConnector
{
    protected function datasetKey(): string
    {
        return 'gccstat.energy_tourism';
    }

    protected function datasetNameAr(): string
    {
        return 'الطاقة والسياحة في دول الخليج';
    }

    protected function datasetNameEn(): string
    {
        return 'GCC energy and tourism';
    }

    protected function section(): string
    {
        return 'energy';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function flows(): array
    {
        return [
            GccFlow::level('MOBILE_DF_ENR_CRD_OIL_PROD', 'CRUDE_PRODUCTION', 'إنتاج النفط الخام', 'Crude oil production', 'ألف برميل/يوم'),
            GccFlow::level('MOBILE_DF_ENR_CRUDE_OIL_RES', 'CRUDE_RESERVES', 'احتياطي النفط الخام', 'Crude oil reserves', 'مليار برميل'),
            GccFlow::level('MOBILE_DF_ENR_MARK_NATU_GAS_PROD', 'GAS_PRODUCTION', 'إنتاج الغاز الطبيعي المسوّق', 'Marketed natural gas production', 'مليون م³'),
            GccFlow::level('MOBILE_DF_ENR_NATU_GAS_RES', 'GAS_RESERVES', 'احتياطي الغاز الطبيعي', 'Natural gas reserves', 'مليار م³'),
            GccFlow::level('MOBILE_DF_ENR_ELECT_PROD', 'ELECTRICITY_PRODUCTION', 'إنتاج الكهرباء', 'Electricity production', 'جيجاواط ساعة'),
            GccFlow::level('MOBILE_DF_ENR_ELECT_CONS', 'ELECTRICITY_CONSUMPTION', 'استهلاك الكهرباء', 'Electricity consumption', 'جيجاواط ساعة'),
            GccFlow::level('MOBILE_DF_TUR_TOURISTS', 'TOURISTS', 'عدد السياح القادمين', 'Tourists arriving', 'ألف سائح'),
            GccFlow::level('MOBILE_DF_TUR_EXP', 'TOURIST_SPENDING', 'إنفاق الزوار', 'Visitor expenditure', null),
            GccFlow::level('MOBILE_DF_TUR_HOTELS', 'HOTELS', 'عدد المنشآت الفندقية', 'Hotel establishments', 'منشأة'),
            GccFlow::level('MOBILE_DF_WTR_DESALINATED_WATER', 'DESALINATED_WATER', 'كمية المياه المحلاة', 'Desalinated water', null),
        ];
    }
}
