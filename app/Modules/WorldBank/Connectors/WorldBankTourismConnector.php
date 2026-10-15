<?php

namespace App\Modules\WorldBank\Connectors;

final class WorldBankTourismConnector extends WorldBankConnector
{
    protected function datasetKey(): string
    {
        return 'worldbank.tourism';
    }

    protected function datasetNameAr(): string
    {
        return 'البنك الدولي: السياحة الدولية';
    }

    protected function datasetNameEn(): string
    {
        return 'World Bank: international tourism';
    }

    protected function section(): string
    {
        return 'tourism';
    }

    protected function indicators(): array
    {
        return [
            WbIndicator::level('ST.INT.ARVL', 'الوافدون السياحيون', 'International tourism arrivals', 'سائح'),
            WbIndicator::level('ST.INT.DPRT', 'المغادرون سياحيًا', 'International tourism departures', 'مسافر'),
            WbIndicator::level('ST.INT.RCPT.CD', 'إيرادات السياحة الدولية', 'International tourism receipts', 'دولار جارٍ'),
        ];
    }
}
