<?php

namespace App\Modules\Markets\Connectors;

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
 * أسعار الفائدة المرجعية الأمريكية اليومية من بنك الاحتياطي الفدرالي في نيويورك: سعر الفائدة الفعلي على الأموال الفدرالية (EFFR)
 * ومعدل التمويل المضمون بين عشية وضحاها (SOFR) وOBFR وTGCR وBGCR وأحجام التداول (EFFR منذ 2000)، ومعها حدّا المدى المستهدف للفائدة الفدرالية. الريال العُماني مربوط بالدولار فتنتقل إليه هذه الفائدة.
 */
final class NyFedRatesConnector extends ApiConnector
{
    private const BASE = 'https://markets.newyorkfed.org/api/rates/';

    /** @var array<string, array{0: string, 1: string}> مسار => [نوع المعدل] */
    private const RATES = ['unsecured/effr' => 'EFFR', 'unsecured/obfr' => 'OBFR', 'secured/sofr' => 'SOFR', 'secured/tgcr' => 'TGCR', 'secured/bgcr' => 'BGCR'];

    public function definition(): DatasetDefinition
    {
        $r = fn () => [new Range(-1, 30, 'reject')];

        return new DatasetDefinition(
            key: 'us.nyfed_rates',
            source: new SourceDefinition(key: 'nyfed', nameAr: 'بنك الاحتياطي الفدرالي في نيويورك', nameEn: 'Federal Reserve Bank of New York', publisher: 'FRBNY', url: 'https://www.newyorkfed.org/markets/reference-rates', license: 'بيانات عامة'),
            nameAr: 'أسعار الفائدة المرجعية الأمريكية (EFFR وSOFR، يومي)',
            nameEn: 'US reference rates (EFFR, SOFR, daily)',
            section: 'markets',
            intervalMinutes: 60,
            indicators: [
                new IndicatorDefinition('us.effr', 'الفائدة الفعلية على الأموال الفدرالية (EFFR)', 'Effective federal funds rate', '%', 'D', $r()),
                new IndicatorDefinition('us.fed_target_upper', 'الحد الأعلى للمدى المستهدف للفائدة الفدرالية', 'Fed funds target range, upper bound', '%', 'D', $r()),
                new IndicatorDefinition('us.fed_target_lower', 'الحد الأدنى للمدى المستهدف للفائدة الفدرالية', 'Fed funds target range, lower bound', '%', 'D', $r()),
                new IndicatorDefinition('us.sofr', 'معدل التمويل المضمون بين عشية وضحاها (SOFR)', 'Secured overnight financing rate', '%', 'D', $r()),
                new IndicatorDefinition('us.obfr', 'معدل التمويل البنكي بين عشية وضحاها (OBFR)', 'Overnight bank funding rate', '%', 'D', $r()),
                new IndicatorDefinition('us.tgcr', 'معدل الريبو الثلاثي الأطراف (TGCR)', 'Tri-party general collateral rate', '%', 'D', $r()),
                new IndicatorDefinition('us.bgcr', 'معدل الريبو الواسع لضمانات عامة (BGCR)', 'Broad general collateral rate', '%', 'D', $r()),
                new IndicatorDefinition('us.sofr_volume', 'حجم تداول SOFR اليومي (مليار دولار)', 'SOFR daily volume ($bn)', 'مليار دولار', 'D', [new Range(0, 20000, 'reject')]),
                new IndicatorDefinition('us.effr_volume', 'حجم تداول الأموال الفدرالية اليومي (مليار دولار)', 'EFFR daily volume ($bn)', 'مليار دولار', 'D', [new Range(0, 20000, 'reject')]),
            ],
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $rows = [];
        $start = '2000-01-01';
        foreach (self::RATES as $path => $type) {
            $res = $this->http()->timeout(60)->retry(3, 3000)->get(self::BASE.$path.'/search.json', ['startDate' => $start, 'endDate' => gmdate('Y-m-d')])->throw()->json();
            if (! isset($res['refRates']) || ! is_array($res['refRates'])) {
                throw new RuntimeException("NY Fed {$type}: تغيّر شكل الاستجابة.");
            }
            foreach ($res['refRates'] as $r) {
                if (isset($r['effectiveDate'], $r['percentRate']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $r['effectiveDate'])) {
                    $rows[] = [strtolower($type), $r['effectiveDate'], (float) $r['percentRate']];
                    if (in_array($type, ['SOFR', 'EFFR'], true) && isset($r['volumeInBillions']) && is_numeric($r['volumeInBillions'])) {
                        $rows[] = [strtolower($type).'_volume', $r['effectiveDate'], (float) $r['volumeInBillions']];
                    }
                    if ($type === 'EFFR' && isset($r['targetRateFrom'], $r['targetRateTo'])) {
                        $rows[] = ['fed_target_lower', $r['effectiveDate'], (float) $r['targetRateFrom']];
                        $rows[] = ['fed_target_upper', $r['effectiveDate'], (float) $r['targetRateTo']];
                    }
                }
            }
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $day, $value]) {
            yield new ParsedRecord("us.{$key}", 'USA', $day, $value);
        }
    }
}
