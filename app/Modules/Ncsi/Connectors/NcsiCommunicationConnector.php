<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesScale;

/**
 * الاتصالات في عُمان — شهري. لا تُضمَّن مؤشرات النطاق العريض الثابت/المتنقل (1000650/1000670):
 * فيها خلل في المصدر منذ 2023 (الثابت والمتنقل مختلطان).
 */
final class NcsiCommunicationConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'OMCMC2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.communication';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: اشتراكات الاتصالات (شهري)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman telecom subscriptions (monthly)';
    }

    protected function section(): string
    {
        return 'digital';
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(0, 1e8), new SeriesScale(100), new RelativeRevision(0.5, 0.25), new Completeness(0.1)];

        return [
            new KnoemaSeries('MOBILE_SUBSCRIBERS', 'إجمالي مشتركي الهاتف المتنقل', 'Total mobile subscribers', 'مشترك', 'M', 1000530, $rules()),
            new KnoemaSeries('MOBILE_POSTPAID', 'مشتركو الهاتف المتنقل (دفع آجل)', 'Mobile postpaid subscribers', 'مشترك', 'M', 1000540, $rules()),
            new KnoemaSeries('MOBILE_PREPAID', 'مشتركو الهاتف المتنقل (مسبق الدفع)', 'Mobile prepaid subscribers', 'مشترك', 'M', 1000550, $rules()),
            new KnoemaSeries('FIXED_LINES', 'خطوط الهاتف الثابت', 'Fixed telephone lines', 'خط', 'M', 1000440, $rules()),
            new KnoemaSeries('FIXED_INTERNET', 'مشتركو الإنترنت الثابت', 'Fixed internet subscribers', 'مشترك', 'M', 1000660, $rules()),
        ];
    }
}
