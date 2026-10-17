<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * سلاسل المالية العامة الطويلة (IMF Fiscal Policy Primer): الإيرادات والمصروفات والرصيد الأولي والفوائد المدفوعة على الدين كنسبة من الناتج،
 * بتاريخ أطول من مرصد المالية العامة. الفوائد المدفوعة مقياس مباشر لعبء خدمة الدين.
 */
final class ImfFiscalPolicyConnector extends ImfDataMapperConnector
{
    protected function datasetKey(): string
    {
        return 'imf.fiscal_policy';
    }

    protected function datasetNameAr(): string
    {
        return 'المالية العامة طويلة الأمد (IMF FPP)';
    }

    protected function datasetNameEn(): string
    {
        return 'Long-run fiscal series (IMF Fiscal Policy Primer)';
    }

    protected function section(): string
    {
        return 'fiscal';
    }

    /** @return list<IndicatorDefinition> */
    protected function indicators(): array
    {
        $rules = fn () => [new Range(-1_000, 1_000, 'reject'), new Completeness(0.2)];

        return [
            new IndicatorDefinition('imf.rev', 'الإيرادات الحكومية (سلسلة طويلة)', 'Government revenue (long-run)', '% من الناتج', rules: $rules()),
            new IndicatorDefinition('imf.exp', 'المصروفات الحكومية (سلسلة طويلة)', 'Government expenditure (long-run)', '% من الناتج', rules: $rules()),
            new IndicatorDefinition('imf.prim_exp', 'المصروفات الأولية (دون الفوائد)', 'Government primary expenditure', '% من الناتج', rules: $rules()),
            new IndicatorDefinition('imf.ie', 'الفوائد المدفوعة على الدين العام', 'Interest paid on public debt', '% من الناتج', rules: $rules()),
            new IndicatorDefinition('imf.pb', 'الرصيد الأولي (سلسلة طويلة)', 'Government primary balance (long-run)', '% من الناتج', rules: $rules()),
        ];
    }
}
