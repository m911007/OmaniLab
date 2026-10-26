<?php

namespace App\Modules\Ons\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * الاقتصاد البريطاني من مكتب الإحصاءات الوطنية (ONS، ترخيص الحكومة المفتوح OGL): التضخم (CPI وCPIH)، البطالة والتوظيف، الأجور، نمو الناتج الفصلي،
 * الدين الصافي والاقتراض العام، مبيعات التجزئة والإنتاج الصناعي. صفحات السلاسل الزمنية العامة بصيغة JSON.
 */
final class OnsUkConnector extends ApiConnector
{
    private const BASE = 'https://www.ons.gov.uk/';

    private const MONTHS = ['JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6, 'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: string, 7: float, 8: float}> مفتاح => [مسار، رمز، مجموعة، تردد، وحدة، عربي، إنجليزي، حد أدنى، حد أعلى] */
    private const SERIES = [
        'ons.cpi_yoy' => ['economy/inflationandpriceindices', 'd7g7', 'mm23', 'M', '%', 'التضخم السنوي في بريطانيا (CPI)', 'UK CPI inflation, y/y', -5, 30],
        'ons.cpih_yoy' => ['economy/inflationandpriceindices', 'l55o', 'mm23', 'M', '%', 'التضخم السنوي (CPIH، يشمل كلفة السكن)', 'UK CPIH inflation, y/y', -5, 30],
        'ons.cpi_index' => ['economy/inflationandpriceindices', 'd7bt', 'mm23', 'M', 'مؤشر 2015=100', 'الرقم القياسي لأسعار المستهلك (2015=100)', 'UK CPI index (2015=100)', 20, 400],
        'ons.unemployment' => ['employmentandlabourmarket/peoplenotinwork/unemployment', 'mgsx', 'lms', 'M', '%', 'معدل البطالة في بريطانيا (16+)', 'UK unemployment rate (16+)', 0, 30],
        'ons.employment_rate' => ['employmentandlabourmarket/peopleinwork/employmentandemployeetypes', 'lf24', 'lms', 'M', '%', 'معدل التوظيف (16–64)', 'UK employment rate (16-64)', 30, 100],
        'ons.weekly_earnings' => ['employmentandlabourmarket/peopleinwork/earningsandworkinghours', 'kab9', 'emp', 'M', 'جنيه', 'متوسط الأجر الأسبوعي (بلا مكافآت)', 'UK average weekly earnings (excl. bonuses)', 100, 3000],
        'ons.gdp_qoq' => ['economy/grossdomesticproductgdp', 'ihyq', 'qna', 'Q', '%', 'نمو الناتج المحلي الفصلي (عن الفصل السابق)', 'UK GDP growth, q/q', -30, 30],
        'ons.gdp_yoy' => ['economy/grossdomesticproductgdp', 'ihyr', 'qna', 'Q', '%', 'نمو الناتج المحلي (عن الفصل نفسه قبل سنة)', 'UK GDP growth, y/y', -30, 40],
        'ons.gdp_level' => ['economy/grossdomesticproductgdp', 'abmi', 'qna', 'Q', 'مليون جنيه', 'الناتج المحلي (حجم متسلسل)', 'UK GDP (chained volume)', 0, 1e8],
        'ons.net_debt_gdp' => ['economy/governmentpublicsectorandtaxes/publicsectorfinance', 'hf6x', 'pusf', 'M', '%', 'صافي الدين العام (% من الناتج)', 'UK public sector net debt, % of GDP', 0, 400],
        'ons.net_borrowing' => ['economy/governmentpublicsectorandtaxes/publicsectorfinance', 'j5ii', 'pusf', 'M', 'مليون جنيه', 'صافي الاقتراض العام شهريًا', 'UK public sector net borrowing', -1e6, 1e6],
        'ons.retail_volume' => ['businessindustryandtrade/retailindustry', 'j5ek', 'drsi', 'M', 'مؤشر', 'حجم مبيعات التجزئة', 'UK retail sales volume', 0, 400],
        'ons.industrial_production' => ['economy/economicoutputandproductivity/output', 'k222', 'diop', 'M', 'مؤشر', 'الإنتاج الصناعي (تصنيع وتعدين وطاقة)', 'UK production index', 0, 400],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as $key => [, , , $freq, $unit, $ar, $en, $lo, $hi]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, $freq, [new Range($lo, $hi, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'ons.uk_economy',
            source: new SourceDefinition(key: 'ons', nameAr: 'مكتب الإحصاءات الوطنية البريطاني (ONS)', nameEn: 'Office for National Statistics (UK)', publisher: 'ONS', url: 'https://www.ons.gov.uk', license: 'Open Government Licence v3.0'),
            nameAr: 'بريطانيا: التضخم والبطالة والنمو والمالية العامة',
            nameEn: 'UK economy: inflation, labour, growth, public finances',
            section: 'macro',
            intervalMinutes: 12 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        $i = 0;
        foreach (self::SERIES as $key => [$path, $cdid, $dataset, $freq]) {
            if ($i++ > 0) {
                usleep(500_000);
            }
            $res = $this->http()->timeout(90)->retry(3, 3000)->get(self::BASE."{$path}/timeseries/{$cdid}/{$dataset}/data")->throw()->json();
            $points = $freq === 'M' ? ($res['months'] ?? []) : ($res['quarters'] ?? []);
            $n = 0;
            foreach ($points as $p) {
                $period = $freq === 'M'
                    ? (preg_match('/^(\d{4}) ([A-Z]{3})$/', (string) ($p['date'] ?? ''), $m) && isset(self::MONTHS[$m[2]]) ? sprintf('%s-%02d', $m[1], self::MONTHS[$m[2]]) : null)
                    : (preg_match('/^(\d{4}) Q([1-4])$/', (string) ($p['date'] ?? ''), $m) ? "{$m[1]}-Q{$m[2]}" : null);
                if ($period !== null && is_numeric($p['value'] ?? null) && $p['value'] !== '') {
                    $out[] = [$key, $period, (float) $p['value']];
                    $n++;
                }
            }
            if ($n === 0) {
                throw new RuntimeException("ONS {$key}: لا نقاط.");
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('GBR', 'United Kingdom', 'country', 'المملكة المتحدة');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $period, $value]) {
            yield new ParsedRecord($key, 'GBR', $period, $value);
        }
    }
}
