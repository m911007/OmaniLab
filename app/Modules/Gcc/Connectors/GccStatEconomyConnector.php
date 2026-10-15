<?php

namespace App\Modules\Gcc\Connectors;

/** الاقتصاد الكلي والتجارة والنقد لدول الخليج: سنوي وشهري. */
final class GccStatEconomyConnector extends GccStatConnector
{
    protected function datasetKey(): string
    {
        return 'gccstat.economy';
    }

    protected function datasetNameAr(): string
    {
        return 'اقتصاد دول الخليج: الناتج والأسعار والتجارة والنقد';
    }

    protected function datasetNameEn(): string
    {
        return 'GCC economy: output, prices, trade, money';
    }

    protected function section(): string
    {
        return 'gcc';
    }

    protected function flows(): array
    {
        return [
            GccFlow::level('MOBILE_DF_NA_GDP_CUR', 'GDP_CUR', 'الناتج المحلي الإجمالي بالأسعار الجارية', 'GDP at current prices', 'مليار دولار'),
            GccFlow::level('MOBILE_DF_GDP_CON', 'GDP_CON', 'الناتج المحلي الإجمالي بالأسعار الثابتة', 'GDP at constant prices', 'مليار دولار'),
            GccFlow::level('MOBILE_DF_GDP_CAP_CUR', 'GDP_PC_CUR', 'نصيب الفرد من الناتج بالأسعار الجارية', 'GDP per capita, current prices', 'ألف دولار'),
            GccFlow::level('MOBILE_DF_GDP_CAP_CON', 'GDP_PC_CON', 'نصيب الفرد من الناتج بالأسعار الثابتة', 'GDP per capita, constant prices', 'ألف دولار'),
            GccFlow::level('MOBILE_DF_NA_GDP_CUR_Q', 'GDP_CUR_Q', 'الناتج المحلي الإجمالي الفصلي بالأسعار الجارية', 'Quarterly GDP at current prices', 'مليون دولار', 'Q'),
            GccFlow::percent('MOBILE_DF_CPI_INF_Y', 'INFLATION_Y', 'التضخم السنوي', 'Annual inflation'),
            GccFlow::percent('MOBILE_DF_CPI_INF', 'INFLATION_M', 'التضخم الشهري (سنويًا)', 'Monthly inflation (y/y)', 'M'),
            GccFlow::level('MOBILE_DF_IT_GEXP', 'GOODS_EXPORTS', 'إجمالي صادرات السلع', 'Total goods exports', 'مليون دولار'),
            GccFlow::level('MOBILE_DF_IT_GIMP', 'GOODS_IMPORTS', 'إجمالي واردات السلع', 'Total goods imports', 'مليون دولار'),
            GccFlow::level('MOBILE_DF_IT_REXP', 'RE_EXPORTS', 'إعادة تصدير السلع', 'Goods re-exports', 'مليون دولار'),
            GccFlow::level('MOBILE_DF_IT_Intra_Trade', 'INTRA_TRADE', 'التجارة البينية الخليجية في السلع', 'Intra-GCC goods trade', 'مليون دولار'),
            GccFlow::level('MOBILE_DF_MF_M2', 'M2', 'عرض النقد (M2)', 'Money supply M2', 'مليون دولار', 'M'),
            GccFlow::level('MOBILE_DF_MF_TBD', 'BANK_DEPOSITS', 'إجمالي ودائع البنوك', 'Total bank deposits', 'مليون دولار', 'M'),
            GccFlow::level('MOBILE_DF_MF_TCD', 'DOMESTIC_CREDIT', 'إجمالي الائتمان المحلي', 'Total domestic credit', 'مليون دولار', 'M'),
        ];
    }
}
