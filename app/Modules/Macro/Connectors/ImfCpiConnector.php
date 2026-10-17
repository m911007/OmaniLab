<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Range;

/**
 * أسعار المستهلك الشهرية لدول الخليج من صندوق النقد (IMF CPI): الرقم القياسي والتضخم السنوي العام،
 * ومعها تضخم الغذاء والسكن والنقل — لتحديد أي بنود الإنفاق تقود الغلاء. مصدر ثانٍ يُقارَن بأرقام المركز الوطني للإحصاء.
 */
final class ImfCpiConnector extends ImfSdmxConnector
{
    private const SERIES = [
        '_T.IX' => ['imf.cpi_index', 'الرقم القياسي لأسعار المستهلك (شهري)', 'Consumer price index (monthly)', 'نقطة', 0, 1000],
        '_T.YOY_PCH_PA_PT' => ['imf.cpi_yoy', 'التضخم السنوي العام (شهري)', 'Headline inflation, y/y (monthly)', '%', -30, 80],
        'CP01.YOY_PCH_PA_PT' => ['imf.cpi_yoy_food', 'تضخم الغذاء والمشروبات (سنوي، شهري)', 'Food & beverages inflation, y/y', '%', -30, 100],
        'CP04.YOY_PCH_PA_PT' => ['imf.cpi_yoy_housing', 'تضخم السكن والمرافق (سنوي، شهري)', 'Housing & utilities inflation, y/y', '%', -30, 100],
        'CP07.YOY_PCH_PA_PT' => ['imf.cpi_yoy_transport', 'تضخم النقل (سنوي، شهري)', 'Transport inflation, y/y', '%', -40, 100],
    ];

    protected function flow(): string
    {
        return 'IMF.STA,CPI';
    }

    protected function key(): string
    {
        return 'OMN+SAU+ARE+QAT+KWT+BHR.CPI._T+CP01+CP04+CP07.IX+YOY_PCH_PA_PT.M?startPeriod=2019-01';
    }

    /** @return list<string> */
    protected function codeColumns(): array
    {
        return ['COICOP_1999', 'TYPE_OF_TRANSFORMATION'];
    }

    protected function datasetKey(): string
    {
        return 'imf.cpi';
    }

    protected function datasetNameAr(): string
    {
        return 'أسعار المستهلك الشهرية (IMF CPI)';
    }

    protected function datasetNameEn(): string
    {
        return 'Monthly consumer prices (IMF CPI)';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function indicators(): array
    {
        $out = [];
        foreach (self::SERIES as $code => [$key, $ar, $en, $unit, $min, $max]) {
            $out[$code] = new IndicatorDefinition($key, $ar, $en, $unit, 'M', [new Range($min, $max, 'reject')]);
        }

        return $out;
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$iso, $code, $period, $value]) {
            if (isset(self::SERIES[$code]) && preg_match('/^[A-Z]{3}$/', $iso) && preg_match('/^(\d{4})-M(\d{2})$/', $period, $m) && is_numeric($value)) {
                yield new ParsedRecord(self::SERIES[$code][0], $iso, "{$m[1]}-{$m[2]}", (float) $value);
            }
        }
    }
}
