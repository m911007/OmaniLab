<?php

namespace App\Modules\Sdg\Connectors;

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
 * القيمة الاقتصادية للمياه حسب القطاع — مؤشر أهداف التنمية المستدامة 6.4.1 «كفاءة استخدام المياه» (دولار من القيمة المضافة لكل متر مكعب مسحوب)
 * لكل الدول منذ 2000، من قاعدة بيانات الأمم المتحدة (unstats.un.org/sdgapi). يجيب: كم يُنتج المتر المكعب في الزراعة مقارنةً بالصناعة والخدمات؟
 */
final class SdgWaterEfficiencyConnector extends ApiConnector
{
    private const API = 'https://unstats.un.org/sdgapi/v1/sdg/Series/Data?seriesCode=ER_H2O_WUEYST&pageSize=5000&page=';

    private const UN_REPORTERS = 'https://comtradeapi.un.org/files/v1/app/reference/Reporters.json';

    /** @var array<string, array{0: string, 1: string, 2: string}> نشاط المصدر => [مفتاح، عربي، إنجليزي] */
    private const ACTIVITIES = [
        'TOTAL' => ['sdg.water_eff.total', 'القيمة الاقتصادية للمياه: كل القطاعات', 'Water use efficiency, total'],
        'ISIC4_A01_A0210_A0322' => ['sdg.water_eff.agriculture', 'القيمة الاقتصادية للمياه: الزراعة', 'Water use efficiency, agriculture'],
        'INDUSTRIES' => ['sdg.water_eff.industry', 'القيمة الاقتصادية للمياه: الصناعة', 'Water use efficiency, industries'],
        'ISIC4_GTT' => ['sdg.water_eff.services', 'القيمة الاقتصادية للمياه: الخدمات', 'Water use efficiency, services'],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::ACTIVITIES as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, 'دولار لكل م³', 'A', [new Range(0, 1e6, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'sdg.water_efficiency',
            source: new SourceDefinition(
                key: 'un_sdg',
                nameAr: 'قاعدة بيانات أهداف التنمية المستدامة — الأمم المتحدة',
                nameEn: 'UN SDG Global Database',
                publisher: 'UN Statistics Division (FAO custodian for 6.4.1)',
                url: 'https://unstats.un.org/sdgs/dataportal',
                license: 'UN terms of use (open data, attribution)',
            ),
            nameAr: 'القيمة الاقتصادية للمياه حسب القطاع (هدف التنمية 6.4.1)',
            nameEn: 'Water use efficiency by sector (SDG 6.4.1)',
            section: 'energy',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        $total = $this->getJson(str_replace('pageSize=5000', 'pageSize=1', self::API).'1')['totalElements'] ?? null;

        return new ProbeResult(token: $total === null ? null : 'n'.$total, version: 'SDG 6.4.1');
    }

    public function fetch(): RawPayload
    {
        $iso = $this->isoByM49();
        $rows = [];
        for ($page = 1; $page <= 20; $page++) {
            $res = $this->getJson(self::API.$page);
            foreach ($res['data'] ?? [] as $r) {
                $activity = $r['dimensions']['Activity'] ?? null;
                $country = $iso[(int) ($r['geoAreaCode'] ?? 0)] ?? null;
                if ($country === null || ! isset(self::ACTIVITIES[$activity]) || ! is_numeric($r['value'] ?? null) || ($r['dimensions']['Reporting Type'] ?? 'G') !== 'G') {
                    continue;
                }
                $rows[] = [$activity, $country, (string) (int) $r['timePeriodStart'], (float) $r['value']];
            }
            if ($page >= (int) ($res['totalPages'] ?? 1)) {
                break;
            }
        }
        if ($rows === []) {
            throw new RuntimeException('SDG 6.4.1: لا بيانات.');
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$activity, $country, $year, $value]) {
            yield new ParsedRecord(self::ACTIVITIES[$activity][0], $country, $year, (float) $value);
        }
    }

    /** @return array<int, string> رمز M49 => ISO3 */
    private function isoByM49(): array
    {
        $map = [];
        foreach ($this->getJson(self::UN_REPORTERS)['results'] ?? [] as $r) {
            if (empty($r['isGroup']) && ! empty($r['reporterCodeIsoAlpha3'])) {
                $map[(int) $r['reporterCode']] ??= (string) $r['reporterCodeIsoAlpha3'];
            }
        }
        if ($map === []) {
            throw new RuntimeException('SDG: تعذّر تحميل جدول رموز الدول.');
        }

        return $map;
    }
}
