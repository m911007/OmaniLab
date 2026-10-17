<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** الطاقة المتجددة في عُمان (هيئة تنظيم الخدمات العامة عبر NCSI): إنتاج الشمس والرياح شهريًا منذ 2022، وهو أحدث وأدق من تقديرات المصادر العالمية. */
final class NcsiRenewableEnergyConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'cogacdd';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.renewable_energy';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: إنتاج الطاقة المتجددة (شهري)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman renewable energy production (monthly)';
    }

    protected function section(): string
    {
        return 'energy';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(0, 10000000.0, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('RE_TOTAL', 'إنتاج الطاقة المتجددة', 'Renewable energy production', 'جيجاواط ساعة', 'M', 1000000, $rules()),
            new KnoemaSeries('RE_SOLAR', 'إنتاج الطاقة الشمسية', 'Solar energy production', 'جيجاواط ساعة', 'M', 1000010, $rules()),
            new KnoemaSeries('RE_WIND', 'إنتاج طاقة الرياح', 'Wind energy production', 'جيجاواط ساعة', 'M', 1000020, $rules()),
        ];
    }
}
