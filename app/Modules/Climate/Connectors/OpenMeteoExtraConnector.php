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
 * الإشعاع الشمسي والرياح والرطوبة والتبخر وجودة الهواء (الغبار PM10 وPM2.5) في المدن العُمانية شهريًا (Open-Meteo: إعادة تحليل ERA5 ونموذج CAMS).
 * تفسّر إمكانات الطاقة الشمسية وطاقة الرياح، والطلب على التبريد، والري، والغبار. الإشعاع منذ 1991 وجودة الهواء منذ منتصف 2022.
 * يُنشر الشهر المكتمل فقط.
 */
final class OpenMeteoExtraConnector extends ApiConnector
{
    private const ARCHIVE = 'https://archive-api.open-meteo.com/v1/archive';

    private const AIR = 'https://air-quality-api.open-meteo.com/v1/air-quality';

    private const FROM = '1991-01-01';

    private const AIR_FROM = '2022-08-01';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float}> مفتاح => [عربي، إنجليزي، وحدة، تردد داخلي، حد أعلى] */
    private const METRICS = [
        'solar' => ['الإشعاع الشمسي اليومي', 'Daily solar radiation', 'كيلوواط ساعة/م²/يوم', 'mean', 15],
        'wind' => ['متوسط سرعة الرياح (10 م)', 'Mean wind speed (10 m)', 'كم/س', 'mean', 120],
        'humidity' => ['متوسط الرطوبة النسبية', 'Mean relative humidity', '%', 'mean', 100],
        'sunshine' => ['ساعات سطوع الشمس اليومية', 'Daily sunshine hours', 'ساعة/يوم', 'mean', 24],
        'et0' => ['التبخر-نتح المرجعي', 'Reference evapotranspiration', 'ملم/شهر', 'sum', 600],
        'pm10' => ['الجسيمات العالقة PM10', 'PM10 particulate matter', 'ميكروغرام/م³', 'mean', 2000],
        'pm25' => ['الجسيمات الدقيقة PM2.5', 'PM2.5 particulate matter', 'ميكروغرام/م³', 'mean', 1000],
        'dust' => ['الغبار', 'Dust concentration', 'ميكروغرام/م³', 'mean', 3000],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (OpenMeteoClimateConnector::CITIES as $city => [$ar, $en]) {
            foreach (self::METRICS as $m => [$mar, $men, $unit, , $max]) {
                $indicators[] = new IndicatorDefinition("climate.{$m}_{$city}", "{$mar} في {$ar}", "{$men}, {$en}", $unit, 'M', [new Range(0, $max, 'reject')]);
            }
        }

        return new DatasetDefinition(
            key: 'climate.open_meteo_extra',
            source: new SourceDefinition(key: 'open_meteo', nameAr: 'Open-Meteo (ERA5 وCAMS)', nameEn: 'Open-Meteo (ERA5, CAMS)', publisher: 'Open-Meteo / ECMWF Copernicus', url: 'https://open-meteo.com', license: 'CC BY 4.0'),
            nameAr: 'الشمس والرياح والغبار في المدن العُمانية (شهري)',
            nameEn: 'Oman solar, wind, humidity and dust (monthly)',
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
        $out = ['last' => $end, 'cities' => []];
        foreach (OpenMeteoClimateConnector::CITIES as $city => [, , $lat, $lon]) {
            $res = $this->httpAccepting('application/json')->timeout(120)->retry(3, 2000)->get(self::ARCHIVE, [
                'latitude' => $lat, 'longitude' => $lon, 'start_date' => self::FROM, 'end_date' => $end, 'timezone' => 'Asia/Muscat',
                'daily' => 'shortwave_radiation_sum,wind_speed_10m_mean,relative_humidity_2m_mean,sunshine_duration,et0_fao_evapotranspiration',
            ])->throw()->json();
            if (! isset($res['daily']['time'], $res['daily']['shortwave_radiation_sum'])) {
                throw new RuntimeException("Open-Meteo {$city}: تغيّر شكل الاستجابة.");
            }
            $air = [];
            for ($y = (int) substr(self::AIR_FROM, 0, 4); $y <= (int) substr($end, 0, 4); $y++) {
                $start = max("{$y}-01-01", self::AIR_FROM);
                $stop = min("{$y}-12-31", $end);
                $r = $this->httpAccepting('application/json')->timeout(120)->retry(3, 2000)->get(self::AIR, [
                    'latitude' => $lat, 'longitude' => $lon, 'start_date' => $start, 'end_date' => $stop, 'timezone' => 'Asia/Muscat', 'hourly' => 'pm10,pm2_5,dust',
                ])->throw()->json();
                foreach ($r['hourly']['time'] ?? [] as $i => $t) {
                    $day = substr($t, 0, 10);
                    foreach (['pm10', 'pm2_5', 'dust'] as $f) {
                        $v = $r['hourly'][$f][$i] ?? null;
                        if ($v !== null) {
                            $air[$day][$f][] = $v;
                        }
                    }
                }
                usleep(400_000);
            }
            $airDaily = [];
            foreach ($air as $day => $fields) {
                foreach ($fields as $f => $vals) {
                    $airDaily[$f][$day] = array_sum($vals) / count($vals);
                }
            }
            $out['cities'][$city] = ['daily' => $res['daily'], 'air' => $airDaily];
            usleep(500_000);
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $lastDay = $data['last'];
        foreach ($data['cities'] as $city => $c) {
            $d = $c['daily'];
            /** @var array<string, array<string, array<int, float>>> $series metric => month => values */
            $series = [];
            $fields = ['solar' => 'shortwave_radiation_sum', 'wind' => 'wind_speed_10m_mean', 'humidity' => 'relative_humidity_2m_mean', 'sunshine' => 'sunshine_duration', 'et0' => 'et0_fao_evapotranspiration'];
            foreach ($d['time'] as $i => $day) {
                foreach ($fields as $metric => $field) {
                    $v = $d[$field][$i] ?? null;
                    if ($v !== null) {
                        $series[$metric][substr($day, 0, 7)][] = match ($metric) {
                            'solar' => $v / 3.6,
                            'sunshine' => $v / 3600,
                            default => (float) $v,
                        };
                    }
                }
            }
            foreach (['pm10' => 'pm10', 'pm25' => 'pm2_5', 'dust' => 'dust'] as $metric => $field) {
                foreach ($c['air'][$field] ?? [] as $day => $v) {
                    $series[$metric][substr($day, 0, 7)][] = $v;
                }
            }
            foreach ($series as $metric => $months) {
                foreach ($months as $m => $vals) {
                    $days = (int) CarbonImmutable::parse($m.'-01')->daysInMonth;
                    if (count($vals) < $days || $m.'-'.sprintf('%02d', $days) > $lastDay) {
                        continue;
                    }
                    $value = self::METRICS[$metric][3] === 'sum' ? array_sum($vals) : array_sum($vals) / count($vals);
                    yield new ParsedRecord("climate.{$metric}_{$city}", 'OMN', $m, round($value, 2));
                }
            }
        }
    }
}
