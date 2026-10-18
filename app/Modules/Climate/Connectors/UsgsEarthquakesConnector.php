<?php

namespace App\Modules\Climate\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * الزلازل في بحر عُمان وما حوله (هيئة المسح الجيولوجي الأمريكية USGS): عدد الهزات بقوة 3 فأكثر وأشدها شهريًا منذ 2000 في صندوق
 * خط العرض 16–27 والطول 51–62 (يشمل سواحل عُمان وخليج عُمان ومكران). مؤشر مخاطر طبيعية للتخطيط والبنية التحتية.
 * الأشهر الخالية من هزات تُسجَّل صفرًا كي لا تظهر فجوات في السلسلة، ولا يُنشر الشهر الجاري لأنه ناقص.
 */
final class UsgsEarthquakesConnector extends ApiConnector
{
    private const URL = 'https://earthquake.usgs.gov/fdsnws/event/1/query';

    private const FROM = '2000-01';

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: 'hazard.usgs_earthquakes',
            source: new SourceDefinition(key: 'usgs', nameAr: 'هيئة المسح الجيولوجي الأمريكية (USGS)', nameEn: 'U.S. Geological Survey', publisher: 'USGS', url: 'https://earthquake.usgs.gov', license: 'عام (Public domain)'),
            nameAr: 'الزلازل في بحر عُمان وما حوله',
            nameEn: 'Earthquakes around Oman and the Gulf of Oman',
            section: 'energy',
            intervalMinutes: 24 * 60,
            indicators: [
                new IndicatorDefinition('hazard.quakes_m3_count', 'عدد الهزات الأرضية (قوة 3 فأكثر)', 'Earthquakes of magnitude 3+', 'هزة', 'M', [new Range(0, 5000, 'reject')]),
                new IndicatorDefinition('hazard.quakes_max_magnitude', 'أشد هزة في الشهر', 'Strongest earthquake in the month', 'ريختر', 'M', [new Range(0, 10, 'reject')]),
            ],
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $csv = $this->httpAccepting('text/csv')->timeout(120)->retry(3, 5000)->get(self::URL, [
            'format' => 'csv', 'starttime' => self::FROM.'-01', 'minmagnitude' => 3, 'minlatitude' => 16, 'maxlatitude' => 27, 'minlongitude' => 51, 'maxlongitude' => 62, 'orderby' => 'time-asc', 'limit' => 20000,
        ])->throw()->body();
        if (! str_starts_with($csv, 'time,')) {
            throw new RuntimeException('USGS: تغيّر شكل الاستجابة.');
        }
        $months = [];
        foreach (array_slice(preg_split('/\r?\n/', trim($csv)), 1) as $line) {
            $f = str_getcsv($line, ',', '"', '');
            if (isset($f[4]) && preg_match('/^(\d{4}-\d{2})/', (string) $f[0], $m) && is_numeric($f[4])) {
                $months[$m[1]]['n'] = ($months[$m[1]]['n'] ?? 0) + 1;
                $months[$m[1]]['max'] = max($months[$m[1]]['max'] ?? 0, (float) $f[4]);
            }
        }

        return new RawPayload(json_encode(['months' => $months, 'through' => CarbonImmutable::now('UTC')->subMonth()->format('Y-m')], JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $d = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $cursor = CarbonImmutable::parse(self::FROM.'-01');
        $end = CarbonImmutable::parse($d['through'].'-01');
        while ($cursor <= $end) {
            $m = $cursor->format('Y-m');
            yield new ParsedRecord('hazard.quakes_m3_count', 'OMN', $m, (float) ($d['months'][$m]['n'] ?? 0));
            if (isset($d['months'][$m])) {
                yield new ParsedRecord('hazard.quakes_max_magnitude', 'OMN', $m, (float) $d['months'][$m]['max']);
            }
            $cursor = $cursor->addMonth();
        }
    }
}
