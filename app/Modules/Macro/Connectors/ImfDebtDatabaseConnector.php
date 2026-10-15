<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\MaxRevision;
use App\Core\Validation\Rules\Range;

/** قاعدة الدين العالمية (Global Debt Database): دين القطاع الخاص والأسر والشركات والقطاع العام كنسب من الناتج. */
final class ImfDebtDatabaseConnector extends ImfDataMapperConnector
{
    protected function datasetKey(): string
    {
        return 'imf.debt_database';
    }

    protected function datasetNameAr(): string
    {
        return 'قاعدة الدين العالمية (IMF Global Debt Database)';
    }

    protected function datasetNameEn(): string
    {
        return 'IMF Global Debt Database';
    }

    protected function section(): string
    {
        return 'fiscal';
    }

    /** @return list<IndicatorDefinition> */
    protected function indicators(): array
    {
        $rules = fn () => [new Range(0, 3_000, 'reject'), new MaxRevision(15.0, 0.25), new Completeness(0.2)];
        $mk = fn (string $code, string $ar, string $en) => new IndicatorDefinition("imf.{$code}", $ar, $en, '% من الناتج', rules: $rules());

        return [
            $mk('Privatedebt_all', 'دين القطاع الخاص (كل الأدوات)', 'Private debt, all instruments'),
            $mk('HH_ALL', 'دين الأسر (كل الأدوات)', 'Household debt, all instruments'),
            $mk('NFC_ALL', 'دين الشركات غير المالية (كل الأدوات)', 'Nonfinancial corporate debt, all instruments'),
            $mk('PVD_LS', 'دين القطاع الخاص (قروض وسندات)', 'Private debt, loans and debt securities'),
            $mk('HH_LS', 'دين الأسر (قروض وسندات)', 'Household debt, loans and debt securities'),
            $mk('NFC_LS', 'دين الشركات غير المالية (قروض وسندات)', 'Nonfinancial corporate debt, loans and securities'),
            $mk('PS_DEBT_GDP', 'دين القطاع العام', 'Public sector debt'),
            $mk('NFPS_DEBT_GDP', 'دين القطاع العام غير المالي', 'Nonfinancial public sector debt'),
            $mk('GG_DEBT_GDP', 'دين الحكومة العامة', 'General government debt'),
            $mk('CG_DEBT_GDP', 'دين الحكومة المركزية', 'Central government debt'),
        ];
    }
}
