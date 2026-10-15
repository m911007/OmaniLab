<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesSpike;

/** الحسابات القومية لعُمان — سنوي. */
final class NcsiNationalAccountsConnector extends KnoemaConnector
{
    private const TOTAL = 1000000;

    private const CURRENT = 1000010;

    private const CONSTANT = 1000020;

    protected function knoemaDatasetId(): string
    {
        return 'zoangf';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.national_accounts';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الحسابات القومية';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman national accounts';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(0, 1e9), new SeriesSpike(50), new RelativeRevision(0.5, 0.25), new Completeness(0.1)];
        $total = ['economic-activity-of-non-petrol' => self::TOTAL, 'institutional-sector' => self::TOTAL];

        return [
            new KnoemaSeries('GDP_CURRENT', 'الناتج المحلي الإجمالي بالأسعار الجارية', 'GDP at market prices, current', 'مليون ريال عُماني', 'A', 1000010, $rules(), $total + ['price-type' => self::CURRENT]),
            new KnoemaSeries('GDP_CONSTANT', 'الناتج المحلي الإجمالي بالأسعار الثابتة', 'GDP at market prices, constant', 'مليون ريال عُماني', 'A', 1000010, $rules(), $total + ['price-type' => self::CONSTANT]),
            new KnoemaSeries('GDP_PER_CAPITA_USD', 'نصيب الفرد من الناتج المحلي', 'GDP per capita', 'دولار', 'A', 1000060, $rules(), $total + ['price-type' => self::CURRENT]),
            new KnoemaSeries('EXPORTS_GS', 'صادرات السلع والخدمات', 'Exports of goods and services', 'مليون ريال عُماني', 'A', 1000120, $rules(), $total + ['price-type' => self::CURRENT]),
            new KnoemaSeries('IMPORTS_GS', 'واردات السلع والخدمات', 'Imports of goods and services', 'مليون ريال عُماني', 'A', 1000130, $rules(), $total + ['price-type' => self::CURRENT]),
        ];
    }
}
