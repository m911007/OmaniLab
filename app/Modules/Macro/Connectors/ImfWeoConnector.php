<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rule;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesSpike;

/**
 * صندوق النقد الدولي — آفاق الاقتصاد العالمي (WEO). يصدر مرتين في السنة (أبريل وأكتوبر) مع تحديثات فرعية؛ الفحص أسبوعي.
 * حدود Range واسعة عمدًا: تحتمل قيمًا متطرفة حقيقية (تضخم زيمبابوي 2008 = 231 مليون %، عجز غينيا الاستوائية 1992 = −557%).
 */
final class ImfWeoConnector extends ImfDataMapperConnector
{
    protected function datasetKey(): string
    {
        return 'imf.weo';
    }

    protected function datasetNameAr(): string
    {
        return 'آفاق الاقتصاد العالمي (WEO)';
    }

    protected function datasetNameEn(): string
    {
        return 'World Economic Outlook';
    }

    /** @return list<IndicatorDefinition> */
    protected function indicators(): array
    {
        /** @return list<Rule> */
        $rules = fn (float $min, float $max, float $delta) => [new Range($min, $max), new MaxRevision($delta), new Completeness(0.1)];
        $level = fn (float $max, float $rel = 0.5) => [new Range(0, $max), new SeriesSpike(50), new RelativeRevision($rel, 0.25), new Completeness(0.1)];

        return [
            new IndicatorDefinition('imf.NGDP_RPCH', 'نمو الناتج المحلي الإجمالي الحقيقي', 'Real GDP growth', '%', rules: $rules(-100, 200, 5)),
            new IndicatorDefinition('imf.PCPIPCH', 'معدل التضخم (متوسط أسعار المستهلك)', 'Inflation rate, average consumer prices', '%', rules: $rules(-50, 1_000_000_000, 10)),
            new IndicatorDefinition('imf.PCPIEPCH', 'معدل التضخم (نهاية الفترة)', 'Inflation rate, end of period', '%', rules: $rules(-100, 1_000_000_000, 10)),
            new IndicatorDefinition('imf.NGDPD', 'الناتج المحلي الإجمالي بالأسعار الجارية', 'GDP, current prices', 'مليار دولار', rules: $rules(0, 200_000, 100)),
            new IndicatorDefinition('imf.NGDPDPC', 'نصيب الفرد من الناتج المحلي', 'GDP per capita, current prices', 'دولار', rules: $rules(0, 500_000, 5_000)),
            new IndicatorDefinition('imf.PPPGDP', 'الناتج المحلي بتعادل القوة الشرائية', 'GDP, PPP', 'مليار دولار دولي', rules: $level(1e7)),
            new IndicatorDefinition('imf.PPPPC', 'نصيب الفرد من الناتج بتعادل القوة الشرائية', 'GDP per capita, PPP', 'دولار دولي', rules: $level(1e6)),
            new IndicatorDefinition('imf.PPPSH', 'حصة الاقتصاد من الناتج العالمي (بتعادل القوة الشرائية)', 'Share of world GDP, PPP', '% من العالم', rules: [new Range(0, 100), new MaxRevision(1.0, 0.25), new Completeness(0.1)]),
            new IndicatorDefinition('imf.LP', 'عدد السكان', 'Population', 'مليون نسمة', rules: [new Range(0, 10_000), new RelativeRevision(0.3, 0.25), new Completeness(0.1)]),
            new IndicatorDefinition('imf.GGXWDG_NGDP', 'إجمالي الدين الحكومي', 'General government gross debt', '% من الناتج', rules: $rules(0, 2_000, 15)),
            new IndicatorDefinition('imf.GGXCNL_NGDP', 'صافي الإقراض/الاقتراض الحكومي (رصيد الميزانية)', 'General government net lending/borrowing', '% من الناتج', rules: $rules(-1_000, 1_000, 8)),
            new IndicatorDefinition('imf.BCA_NGDPD', 'رصيد الحساب الجاري', 'Current account balance', '% من الناتج', rules: $rules(-1_000, 1_000, 6)),
            new IndicatorDefinition('imf.BCA', 'رصيد الحساب الجاري (بالدولار)', 'Current account balance, US$', 'مليار دولار', rules: $rules(-5_000, 5_000, 50)),
            new IndicatorDefinition('imf.LUR', 'معدل البطالة', 'Unemployment rate', '%', rules: $rules(0, 100, 3)),
        ];
    }
}
