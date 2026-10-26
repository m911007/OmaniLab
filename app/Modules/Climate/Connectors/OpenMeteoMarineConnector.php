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
use Illuminate\Http\Client\RequestException;
use RuntimeException;

/**
 * البحر قبالة السواحل العُمانية شهريًا (Open-Meteo Marine: نموذج الأمواج ERA5-Ocean): حرارة سطح البحر ومتوسط وأقصى ارتفاع للموج ومتوسط زمن الموجة
 * في ست نقاط ساحلية على بحر العرب وبحر عُمان ومضيق هرمز. تفسّر موسم الصيد وإيقاف الملاحة والموانئ في الخريف (الخريف الموسمي) وتأثير الأعاصير.
 */
final class OpenMeteoMarineConnector extends ApiConnector
{
    private const URL = 'https://marine-api.open-meteo.com/v1/marine';

    private const FROM = '1990-01-01';

    /** @var array<string, array{0: string, 1: string, 2: float, 3: float}> رمز => [عربي، إنجليزي، عرض، طول] */
    private const POINTS = [
        'musandam' => ['مسندم (مضيق هرمز)', 'Musandam (Hormuz)', 26.5, 56.7],
        'sohar' => ['صحار', 'Sohar', 24.5, 56.8],
        'muscat' => ['مسقط', 'Muscat', 23.75, 58.65],
        'duqm' => ['الدقم', 'Duqm', 19.6, 57.8],
        'masirah' => ['مصيرة', 'Masirah', 20.4, 59.2],
        'salalah' => ['صلالة', 'Salalah', 16.9, 54.1],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float}> مقياس => [عربي، إنجليزي، وحدة، ...، حد أعلى] */
    private const METRICS = [
        'sst' => ['حرارة سطح البحر', 'Sea surface temperature', '°م', 'mean', 45],
        'wave_mean' => ['متوسط أقصى ارتفاع يومي للموج', 'Mean daily max wave height', 'متر', 'mean', 20],
        'wave_peak' => ['أعلى موجة في الشهر', 'Monthly peak wave height', 'متر', 'max', 30],
        'wave_period' => ['متوسط زمن الموجة', 'Mean wave period', 'ثانية', 'mean', 40],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::POINTS as $p => [$ar, $en]) {
            foreach (self::METRICS as $m => [$mar, $men, $unit, , $max]) {
                $indicators[] = new IndicatorDefinition("marine.{$m}_{$p}", "{$mar} — {$ar}", "{$men}, {$en}", $unit, 'M', [new Range(0, $max, 'reject')]);
            }
        }

        return new DatasetDefinition(
            key: 'climate.open_meteo_marine',
            source: new SourceDefinition(key: 'open_meteo', nameAr: 'Open-Meteo (نموذج البحر)', nameEn: 'Open-Meteo Marine', publisher: 'Open-Meteo / ECMWF Copernicus', url: 'https://open-meteo.com/en/docs/marine-weather-api', license: 'CC BY 4.0'),
            nameAr: 'بحر عُمان: حرارة السطح وارتفاع الموج (شهري)',
            nameEn: 'Oman seas: sea temperature and waves (monthly)',
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
        $end = CarbonImmutable::now('Asia/Muscat')->subDays(5)->toDateString();
        $out = ['last' => $end, 'points' => []];
        foreach (self::POINTS as $p => [, , $lat, $lon]) {
            $res = $this->httpAccepting('application/json')->timeout(120)->retry(5, 65000, fn ($e) => $e instanceof RequestException && $e->response->status() === 429)->get(self::URL, [
                'latitude' => $lat, 'longitude' => $lon, 'start_date' => self::FROM, 'end_date' => $end, 'timezone' => 'Asia/Muscat',
                'daily' => 'wave_height_max,wave_period_max,sea_surface_temperature_mean',
            ])->throw()->json();
            if (! isset($res['daily']['time'], $res['daily']['wave_height_max'])) {
                throw new RuntimeException("Open-Meteo Marine {$p}: تغيّر شكل الاستجابة.");
            }
            $out['points'][$p] = $res['daily'];
            sleep(20);
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $last = $data['last'];
        foreach ($data['points'] as $p => $d) {
            /** @var array<string, array<string, list<float>>> $months */
            $months = [];
            $fields = ['sst' => 'sea_surface_temperature_mean', 'wave_mean' => 'wave_height_max', 'wave_peak' => 'wave_height_max', 'wave_period' => 'wave_period_max'];
            foreach ($d['time'] as $i => $day) {
                foreach ($fields as $metric => $field) {
                    $v = $d[$field][$i] ?? null;
                    if ($v !== null) {
                        $months[$metric][substr($day, 0, 7)][] = (float) $v;
                    }
                }
            }
            foreach ($months as $metric => $byMonth) {
                foreach ($byMonth as $m => $vals) {
                    $days = (int) CarbonImmutable::parse($m.'-01')->daysInMonth;
                    if (count($vals) < $days * 0.9 || $m.'-'.sprintf('%02d', $days) > $last) {
                        continue;
                    }
                    $value = self::METRICS[$metric][3] === 'max' ? max($vals) : array_sum($vals) / count($vals);
                    yield new ParsedRecord("marine.{$metric}_{$p}", 'OMN', $m, round($value, 2));
                }
            }
        }
    }
}
