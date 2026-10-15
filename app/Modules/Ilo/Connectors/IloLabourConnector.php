<?php

namespace App\Modules\Ilo\Connectors;

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
 * منظمة العمل الدولية (ILOSTAT) عبر SDMX: بطالة ومشاركة ونيت لكل الدول، وتفصيل عُمان حسب الجنسية (مواطنون/وافدون)
 * الذي لا يتوفر في المصادر العالمية الأخرى. أرقام التوظيف تصل بالآلاف (UNIT_MULT=3) فتُضرب هنا لتخزَّن بالوحدة.
 * لا فحص رخيص: الاستعلامات مفلترة بمفاتيح SDMX فتبقى صغيرة؛ يُكتشف التغيير ببصمة الحمولة.
 */
final class IloLabourConnector extends ApiConnector
{
    private const BASE = 'https://sdmx.ilo.org/rest/data/ILO,';

    private const FROM = 2000;

    /**
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: int, 7: int|float}>
     *                                                                                                                   مفتاح المؤشر => [عربي، إنجليزي، وحدة، قاعدة بيانات SDMX، مفتاح SDMX، ملاحظة (لا تُستعمل)، مضاعف، حد أعلى]
     */
    private const QUERIES = [
        'ilo.unemployment' => ['البطالة (15+)', 'Unemployment rate (15+)', '%', 'DF_UNE_DEAP_SEX_AGE_RT', '.A.UNE_DEAP_RT.SEX_T.AGE_YTHADULT_YGE15', '', 1, 100],
        'ilo.unemployment_youth' => ['بطالة الشباب (15–24)', 'Youth unemployment rate (15–24)', '%', 'DF_UNE_DEAP_SEX_AGE_RT', '.A.UNE_DEAP_RT.SEX_T.AGE_YTHADULT_Y15-24', '', 1, 100],
        'ilo.lfp_total' => ['معدل المشاركة في القوى العاملة', 'Labour force participation rate', '%', 'DF_EAP_DWAP_SEX_AGE_RT', '.A.EAP_DWAP_RT.SEX_T.AGE_10YRBANDS_YGE15', '', 1, 100],
        'ilo.lfp_female' => ['مشاركة المرأة في القوى العاملة', 'Female labour force participation rate', '%', 'DF_EAP_DWAP_SEX_AGE_RT', '.A.EAP_DWAP_RT.SEX_F.AGE_10YRBANDS_YGE15', '', 1, 100],
        'ilo.neet' => ['الشباب خارج العمل والتعليم والتدريب', 'Youth not in employment, education or training', '%', 'DF_EIP_NEET_SEX_RT', '.A.EIP_NEET_RT.SEX_T', '', 1, 100],
        'ilo.emp_citizen' => ['العاملون العُمانيون', 'Employed Omani citizens', 'عامل', 'DF_EMP_TEMP_SEX_AGE_CCT_NB', 'OMN.A.EMP_TEMP_NB.SEX_T.AGE_AGGREGATE_YGE15.CCT_CIT_CITIZEN', '', 1000, 1e8],
        'ilo.emp_noncitizen' => ['العاملون غير العُمانيين', 'Employed non-citizens', 'عامل', 'DF_EMP_TEMP_SEX_AGE_CCT_NB', 'OMN.A.EMP_TEMP_NB.SEX_T.AGE_AGGREGATE_YGE15.CCT_CIT_NONCIT', '', 1000, 1e8],
        'ilo.unemployment_citizen' => ['بطالة العُمانيين', 'Unemployment rate, Omani citizens', '%', 'DF_UNE_DEAP_SEX_AGE_CCT_RT', 'OMN.A.UNE_DEAP_RT.SEX_T.AGE_AGGREGATE_YGE15.CCT_CIT_CITIZEN', '', 1, 100],
        'ilo.lfp_citizen' => ['مشاركة العُمانيين في القوى العاملة', 'Labour force participation, Omani citizens', '%', 'DF_EAP_DWAP_SEX_AGE_CCT_RT', 'OMN.A.EAP_DWAP_RT.SEX_T.AGE_AGGREGATE_YGE15.CCT_CIT_CITIZEN', '', 1, 100],
        'ilo.lfp_noncitizen' => ['مشاركة غير العُمانيين في القوى العاملة', 'Labour force participation, non-citizens', '%', 'DF_EAP_DWAP_SEX_AGE_CCT_RT', 'OMN.A.EAP_DWAP_RT.SEX_T.AGE_AGGREGATE_YGE15.CCT_CIT_NONCIT', '', 1, 100],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::QUERIES as $key => [$ar, $en, $unit, , , , , $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'ilo.labour',
            source: new SourceDefinition(
                key: 'ilo',
                nameAr: 'منظمة العمل الدولية (ILOSTAT)',
                nameEn: 'International Labour Organization (ILOSTAT)',
                publisher: 'ILO',
                url: 'https://ilostat.ilo.org',
                license: 'CC BY 4.0',
            ),
            nameAr: 'سوق العمل (منظمة العمل الدولية)',
            nameEn: 'Labour market (ILO)',
            section: 'society',
            intervalMinutes: 3 * 24 * 60,
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
        foreach (self::QUERIES as $key => [, , , $flow, $dims]) {
            $url = self::BASE.$flow.'/'.$dims.'?startPeriod='.self::FROM;
            $csv = $this->httpAccepting('application/vnd.sdmx.data+csv')->timeout(120)->get($url)->throw()->body();
            if (! str_starts_with($csv, 'DATAFLOW,')) {
                throw new RuntimeException("ILO {$key}: تغيّر شكل الاستجابة.");
            }
            $body[$key] = $csv;
            usleep(300_000);
        }

        return new RawPayload(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        foreach ($data as $key => $csv) {
            $multiplier = self::QUERIES[$key][6];
            $handle = fopen('php://temp', 'r+');
            fwrite($handle, $csv);
            rewind($handle);
            $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
            $area = array_search('REF_AREA', $header, true);
            $period = array_search('TIME_PERIOD', $header, true);
            $value = array_search('OBS_VALUE', $header, true);
            if ($area === false || $period === false || $value === false) {
                throw new RuntimeException("ILO {$key}: أعمدة SDMX المتوقعة غير موجودة.");
            }
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $iso = $row[$area] ?? '';
                $v = $row[$value] ?? '';
                if (preg_match('/^[A-Z]{3}$/', $iso) && preg_match('/^\d{4}$/', $row[$period] ?? '') && is_numeric($v)) {
                    yield new ParsedRecord($key, $iso, $row[$period], (float) $v * $multiplier);
                }
            }
            fclose($handle);
        }
    }
}
