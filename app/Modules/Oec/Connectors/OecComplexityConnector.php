<?php

namespace App\Modules\Oec\Connectors;

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
 * مؤشر التعقيد الاقتصادي ECI (مرصد التعقيد الاقتصادي OEC، جامعة هارفارد/MIT): يقيس تنوع وتعقيد ما تصدّره الدولة وليس حجمه؛
 * الأعلى أعقد وأقدر على النمو. واجهة Tesseract العامة بلا مفتاح. رمز الدولة في OEC قارة (حرفان) ثم ISO3، فيؤخذ الثلاثة الأخيرة.
 */
final class OecComplexityConnector extends ApiConnector
{
    private const URL = 'https://api-v2.oec.world/tesseract/data.jsonrecords';

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'oec.complexity',
            source: new SourceDefinition(
                key: 'oec',
                nameAr: 'مرصد التعقيد الاقتصادي (OEC)',
                nameEn: 'Observatory of Economic Complexity',
                publisher: 'OEC',
                url: 'https://oec.world',
                license: 'OEC terms — attribution required',
                reliability: 'secondary',
            ),
            nameAr: 'التعقيد الاقتصادي للصادرات (ECI)',
            nameEn: 'Economic Complexity Index (ECI)',
            section: 'trade',
            intervalMinutes: 7 * 24 * 60,
            indicators: [new IndicatorDefinition('oec.eci', 'مؤشر التعقيد الاقتصادي ECI (الأعلى = أعقد)', 'Economic Complexity Index (higher = more complex)', 'مؤشر', 'A', [new Range(-6, 6, 'reject')])],
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $res = $this->http()->timeout(90)->get(self::URL, ['cube' => 'complexity_eci_a_hs12_hs6', 'drilldowns' => 'Country,Year', 'measures' => 'ECI'])->throw()->json();
        if (! isset($res['data']) || ! is_array($res['data']) || count($res['data']) < 500) {
            throw new RuntimeException('OEC ECI: استجابة غير مكتملة.');
        }
        $rows = [];
        foreach ($res['data'] as $r) {
            if (isset($r['Country ID'], $r['Year'], $r['ECI']) && is_numeric($r['ECI']) && preg_match('/^[a-z]{5}$/', (string) $r['Country ID'])) {
                $rows[] = [strtoupper(substr($r['Country ID'], 2)), (string) $r['Year'], $r['ECI']];
            }
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$iso, $year, $eci]) {
            if (preg_match('/^\d{4}$/', $year)) {
                yield new ParsedRecord('oec.eci', $iso, $year, (float) $eci);
            }
        }
    }
}
