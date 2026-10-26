<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * الكهرباء في عُمان (هيئة تنظيم الخدمات العامة عبر NCSI): الإنتاج الشهري، والقدرة المركّبة، واشتراكات الكهرباء، والاستهلاك حسب القطاع (سنوي).
 * الاستهلاك والقدرة بوحدة المصدر (جيجاواط ساعة وميجاواط).
 */
final class NcsiElectricityConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'OMELCT2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.electricity';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الكهرباء';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman electricity';
    }

    protected function section(): string
    {
        return 'energy';
    }

    public function revision(): int
    {
        return 2; // تصحيح وحدة اشتراكات الكهرباء 2025
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(0, 1e8, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('ELEC_PRODUCTION', 'إنتاج الكهرباء (شهري)', 'Electricity production (monthly)', 'جيجاواط ساعة', 'M', 1000010, $rules()),
            // 2025 نُشرت بالآلاف (1544.03) بعد سنوات بالآحاد (1497669 في 2024)
            new KnoemaSeries('ELEC_CONNECTIONS', 'اشتراكات الكهرباء', 'Electricity connections', 'اشتراك', 'A', 1000040, $rules(), fixUnitShift: true),
            new KnoemaSeries('ELEC_CAPACITY', 'القدرة المركّبة', 'Installed capacity', 'ميجاواط', 'A', 1000060, $rules()),
            new KnoemaSeries('ELEC_CONSUMPTION', 'استهلاك الكهرباء', 'Electricity consumption', 'جيجاواط ساعة', 'A', 1000080, $rules()),
            new KnoemaSeries('ELEC_CONSUMPTION_RESIDENTIAL', 'الاستهلاك السكني', 'Residential consumption', 'جيجاواط ساعة', 'A', 1000090, $rules()),
            new KnoemaSeries('ELEC_CONSUMPTION_COMMERCIAL', 'الاستهلاك التجاري', 'Commercial consumption', 'جيجاواط ساعة', 'A', 1000100, $rules()),
            new KnoemaSeries('ELEC_CONSUMPTION_INDUSTRIAL', 'الاستهلاك الصناعي', 'Industrial consumption', 'جيجاواط ساعة', 'A', 1000110, $rules()),
            new KnoemaSeries('ELEC_CONSUMPTION_GOVERNMENT', 'الاستهلاك الحكومي', 'Government consumption', 'جيجاواط ساعة', 'A', 1000120, $rules()),
        ];
    }
}
