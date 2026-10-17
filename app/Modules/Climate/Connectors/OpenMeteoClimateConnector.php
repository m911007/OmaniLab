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
 * مناخ المدن العُمانية الرئيسية شهريًا منذ 1991 (Open-Meteo، إعادة تحليل ERA5 من ECMWF): متوسط الحرارة، ومتوسط العظمى،
 * وأيام الحرارة ≥ 40°م، ومجموع الأمطار. يفسّر الطلب على الكهرباء والتبريد، وموسم السياحة (خريف صلالة)، وأثر المناخ على الزراعة والمياه.
 * يُحسب الشهر المكتمل فقط، فلا يظهر شهر جزئي بقيمة مضللة.
 */
final class OpenMeteoClimateConnector extends ApiConnector
{
    private const URL = 'https://archive-api.open-meteo.com/v1/archive';

    private const FROM = '1991-01-01';

    /** @var array<string, array{0: string, 1: string, 2: float, 3: float}> رمز المدينة => [عربي، إنجليزي، خط العرض، خط الطول] */
    public const CITIES = [
        'muscat' => ['مسقط', 'Muscat', 23.59, 58.41],
        'salalah' => ['صلالة', 'Salalah', 17.02, 54.09],
        'nizwa' => ['نزوى', 'Nizwa', 22.93, 57.53],
        'duqm' => ['الدقم', 'Duqm', 19.67, 57.71],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::CITIES as $city => [$ar, $en]) {
            $indicators[] = new IndicatorDefinition("climate.temp_mean_{$city}", "متوسط درجة الحرارة في {$ar}", "Mean temperature, {$en}", '°م', 'M', [new Range(-5, 60, 'reject')]);
            $indicators[] = new IndicatorDefinition("climate.temp_max_{$city}", "متوسط الحرارة العظمى في {$ar}", "Mean daily maximum temperature, {$en}", '°م', 'M', [new Range(-5, 65, 'reject')]);
            $indicators[] = new IndicatorDefinition("climate.hot_days_{$city}", "أيام الحرارة 40°م فأكثر في {$ar}", "Days at or above 40°C, {$en}", 'يوم', 'M', [new Range(0, 31, 'reject')]);
            $indicators[] = new IndicatorDefinition("climate.precip_{$city}", "الأمطار في {$ar}", "Precipitation, {$en}", 'ملم', 'M', [new Range(0, 2000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'climate.open_meteo',
            source: new SourceDefinition(
                key: 'open_meteo',
                nameAr: 'Open-Meteo (إعادة تحليل ERA5)',
                nameEn: 'Open-Meteo (ERA5 reanalysis)',
                publisher: 'Open-Meteo / ECMWF Copernicus',
                url: 'https://open-meteo.com',
                license: 'CC BY 4.0',
            ),
            nameAr: 'مناخ المدن العُمانية (شهري)',
            nameEn: 'Oman city climate (monthly)',
            section: 'energy',
            intervalMinutes: 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $end = CarbonImmutable::now('Asia/Muscat')->subDays(7)->toDateString();
        $out = [];
        foreach (self::CITIES as $city => [, , $lat, $lon]) {
            $res = $this->httpAccepting('application/json')->timeout(120)->retry(3, 2000)->get(self::URL, [
                'latitude' => $lat, 'longitude' => $lon, 'start_date' => self::FROM, 'end_date' => $end,
                'daily' => 'temperature_2m_mean,temperature_2m_max,precipitation_sum', 'timezone' => 'Asia/Muscat',
            ])->throw()->json();
            $daily = $res['daily'] ?? null;
            if (! is_array($daily) || ! isset($daily['time'], $daily['temperature_2m_mean'])) {
                throw new RuntimeException("Open-Meteo {$city}: تغيّر شكل الاستجابة.");
            }
            $out[$city] = ['last' => $end, 'daily' => $daily];
            usleep(500_000);
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $city => $d) {
            $daily = $d['daily'];
            $lastDay = $d['last'];
            $months = [];
            foreach ($daily['time'] as $i => $day) {
                $m = substr($day, 0, 7);
                $mean = $daily['temperature_2m_mean'][$i] ?? null;
                $max = $daily['temperature_2m_max'][$i] ?? null;
                $rain = $daily['precipitation_sum'][$i] ?? null;
                if ($mean === null || $max === null) {
                    continue;
                }
                $months[$m]['n'] = ($months[$m]['n'] ?? 0) + 1;
                $months[$m]['mean'] = ($months[$m]['mean'] ?? 0) + $mean;
                $months[$m]['max'] = ($months[$m]['max'] ?? 0) + $max;
                $months[$m]['hot'] = ($months[$m]['hot'] ?? 0) + ($max >= 40 ? 1 : 0);
                $months[$m]['rain'] = ($months[$m]['rain'] ?? 0) + ($rain ?? 0);
            }
            foreach ($months as $m => $s) {
                // الشهر لا يُنشر إلا مكتملًا (كل أيامه موجودة وانتهى قبل آخر يوم مُحمَّل).
                $days = (int) CarbonImmutable::parse($m.'-01')->daysInMonth;
                if ($s['n'] < $days || $m.'-'.sprintf('%02d', $days) > $lastDay) {
                    continue;
                }
                yield new ParsedRecord("climate.temp_mean_{$city}", 'OMN', $m, round($s['mean'] / $s['n'], 2));
                yield new ParsedRecord("climate.temp_max_{$city}", 'OMN', $m, round($s['max'] / $s['n'], 2));
                yield new ParsedRecord("climate.hot_days_{$city}", 'OMN', $m, (float) $s['hot']);
                yield new ParsedRecord("climate.precip_{$city}", 'OMN', $m, round($s['rain'], 1));
            }
        }
    }
}
