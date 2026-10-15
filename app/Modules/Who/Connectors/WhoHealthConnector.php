<?php

namespace App\Modules\Who\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * منظمة الصحة العالمية (GHO OData): مؤشرات صحية لا يغطيها البنك الدولي — العمر الصحي المتوقع، السمنة، الوفيات المبكرة
 * من الأمراض غير المعدية، تغطية الخدمات الصحية الشاملة، وكثافة الكوادر والأسرّة.
 * تُستثنى التقديرات المستقبلية (سنة أكبر من السنة الحالية).
 */
final class WhoHealthConnector extends ApiConnector
{
    private const BASE = 'https://ghoapi.azureedge.net/api/';

    /**
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: bool, 5: int|float}>
     *                                                                                              مفتاح المؤشر => [رمز GHO، عربي، إنجليزي، وحدة، يتطلب فلتر الجنس (كلا الجنسين)، حد أعلى]
     */
    private const INDICATORS = [
        'who.hale' => ['WHOSIS_000002', 'العمر الصحي المتوقع عند الولادة', 'Healthy life expectancy (HALE) at birth', 'سنة', true, 100],
        'who.obesity' => ['NCD_BMI_30A', 'السمنة بين البالغين (معيارية العمر)', 'Adult obesity prevalence (age-standardised)', '%', true, 100],
        'who.ncd_mortality' => ['NCDMORT3070', 'احتمال الوفاة المبكرة (30–70) من الأمراض غير المعدية', 'Premature NCD mortality (30–70)', '%', true, 100],
        'who.uhc_index' => ['UHC_INDEX_REPORTED', 'مؤشر تغطية الخدمات الصحية الشاملة', 'UHC service coverage index', '0–100', false, 100],
        'who.doctors' => ['HWF_0001', 'الأطباء لكل 10 آلاف نسمة', 'Medical doctors per 10,000 population', 'لكل 10 آلاف', false, 500],
        'who.nurses' => ['HWF_0006', 'التمريض والقبالة لكل 10 آلاف نسمة', 'Nursing and midwifery personnel per 10,000', 'لكل 10 آلاف', false, 500],
        'who.hospital_beds' => ['WHS6_102', 'أسرّة المستشفيات لكل 10 آلاف نسمة', 'Hospital beds per 10,000 population', 'لكل 10 آلاف', false, 500],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::INDICATORS as $key => [, $ar, $en, $unit, , $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'who.health',
            source: new SourceDefinition(
                key: 'who',
                nameAr: 'منظمة الصحة العالمية (WHO)',
                nameEn: 'World Health Organization (GHO)',
                publisher: 'WHO',
                url: 'https://www.who.int/data/gho',
                license: 'CC BY-NC-SA 3.0 IGO',
            ),
            nameAr: 'الصحة (منظمة الصحة العالمية)',
            nameEn: 'Health (WHO)',
            section: 'society',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $body = [];
        foreach (self::INDICATORS as $key => [$code, , , , $sexed]) {
            $filter = "SpatialDimType eq 'COUNTRY'".($sexed ? " and Dim1 eq 'SEX_BTSX'" : '');
            $res = $this->http()->timeout(120)->get(self::BASE.$code, ['$filter' => $filter, '$select' => 'SpatialDim,TimeDim,NumericValue'])->throw()->json();
            if (! isset($res['value']) || ! is_array($res['value'])) {
                throw new RuntimeException("WHO {$code}: استجابة غير متوقعة.");
            }
            $body[$key] = array_map(fn (array $r) => [$r['SpatialDim'], $r['TimeDim'], $r['NumericValue']], $res['value']);
            usleep(300_000);
        }

        return new RawPayload(json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $thisYear = (int) date('Y');
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        foreach ($data as $key => $rows) {
            foreach ($rows as [$iso, $year, $value]) {
                if (is_string($iso) && preg_match('/^[A-Z]{3}$/', $iso) && is_int($year) && $year <= $thisYear && is_numeric($value)) {
                    yield new ParsedRecord($key, $iso, (string) $year, (float) $value);
                }
            }
        }
    }
}
