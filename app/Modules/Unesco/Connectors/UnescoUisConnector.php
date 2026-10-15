<?php

namespace App\Modules\Unesco\Connectors;

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
 * معهد اليونسكو للإحصاء (UIS): الإنفاق على التعليم ومعدلات الالتحاق الإجمالية ومحو الأمية، لكل الدول، من المصدر الأول
 * (البنك الدولي ينقل بعضها عنه)، فتتيح مطابقة المصدرين. الدول رمز ISO3 فقط (المجموعات الإقليمية تُتخطى).
 */
final class UnescoUisConnector extends ApiConnector
{
    private const URL = 'https://api.uis.unesco.org/api/public/data/indicators';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: int|float}> رمز UIS => [مفتاح، عربي، إنجليزي، وحدة، حد أعلى] */
    private const INDICATORS = [
        'XGDP.FSGOV' => ['unesco.edu_spend_gdp', 'الإنفاق الحكومي على التعليم', 'Government expenditure on education', '% من الناتج', 30],
        'GER.1' => ['unesco.ger_primary', 'الالتحاق الإجمالي بالتعليم الابتدائي', 'Gross enrolment ratio, primary', '%', 300],
        'GER.2T3' => ['unesco.ger_secondary', 'الالتحاق الإجمالي بالتعليم الثانوي', 'Gross enrolment ratio, secondary', '%', 300],
        'GER.5T8' => ['unesco.ger_tertiary', 'الالتحاق الإجمالي بالتعليم العالي', 'Gross enrolment ratio, tertiary', '%', 300],
        'LR.AG15T99' => ['unesco.literacy_adult', 'محو أمية الكبار (15+)', 'Adult literacy rate (15+)', '%', 100],
        'LR.AG15T24' => ['unesco.literacy_youth', 'محو أمية الشباب (15–24)', 'Youth literacy rate (15–24)', '%', 100],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::INDICATORS as [$key, $ar, $en, $unit, $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'unesco.education',
            source: new SourceDefinition(
                key: 'unesco',
                nameAr: 'معهد اليونسكو للإحصاء (UIS)',
                nameEn: 'UNESCO Institute for Statistics',
                publisher: 'UNESCO UIS',
                url: 'https://databrowser.uis.unesco.org',
                license: 'CC BY-SA 3.0 IGO',
            ),
            nameAr: 'التعليم (اليونسكو)',
            nameEn: 'Education (UNESCO UIS)',
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
        foreach (self::INDICATORS as $code => [$key]) {
            $res = $this->http()->connectTimeout(45)->retry(5, 4000, throw: false)->timeout(120)->get(self::URL, ['indicator' => $code, 'start' => 1990])->throw()->json();
            if (! isset($res['records']) || ! is_array($res['records'])) {
                throw new RuntimeException("UIS {$code}: استجابة غير متوقعة.");
            }
            $body[$key] = array_map(fn (array $r) => [$r['geoUnit'], $r['year'], $r['value']], $res['records']);
            usleep(400_000);
        }

        return new RawPayload(json_encode($body, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $key => $rows) {
            foreach ($rows as [$iso, $year, $value]) {
                if (is_string($iso) && preg_match('/^[A-Z]{3}$/', $iso) && is_int($year) && is_numeric($value)) {
                    yield new ParsedRecord($key, $iso, (string) $year, (float) $value);
                }
            }
        }
    }
}
