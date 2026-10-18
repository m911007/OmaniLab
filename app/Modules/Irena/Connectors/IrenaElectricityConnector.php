<?php

namespace App\Modules\Irena\Connectors;

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
 * الوكالة الدولية للطاقة المتجددة (IRENA): القدرة المركّبة (ميغاواط) والتوليد (غيغاواط ساعة) لكل دولة وتقنية منذ 2000
 * (شمسية، رياح، غاز، نفط، مائية، نووي…). قاعدة PxWeb الرسمية تُستعلم بدفعات دول لتجنب حد الخلايا.
 */
final class IrenaElectricityConnector extends ApiConnector
{
    private const TABLE = 'https://pxweb.irena.org/api/v1/en/IRENASTAT/Power%20Capacity%20and%20Generation/Country_ELECSTAT_2026_H2_PX.px';

    /** @var array<string, array{0: string, 1: string, 2: string}> اسم التقنية في IRENA => [رمز، عربي، إنجليزي] */
    private const TECH = [
        'Total Non-Renewable' => ['non_renewable', 'إجمالي غير المتجددة', 'Total non-renewable'],
        'Total Renewable' => ['renewable', 'إجمالي المتجددة', 'Total renewable'],
        'Oil' => ['oil', 'النفط', 'Oil'],
        'Natural gas' => ['gas', 'الغاز الطبيعي', 'Natural gas'],
        'Coal' => ['coal', 'الفحم', 'Coal'],
        'Solar photovoltaic' => ['solar_pv', 'الطاقة الشمسية الكهروضوئية', 'Solar photovoltaic'],
        'Solar thermal energy' => ['solar_thermal', 'الطاقة الشمسية الحرارية', 'Solar thermal'],
        'Onshore wind energy' => ['wind_onshore', 'طاقة الرياح البرية', 'Onshore wind'],
        'Offshore wind energy' => ['wind_offshore', 'طاقة الرياح البحرية', 'Offshore wind'],
        'Renewable hydropower' => ['hydro', 'الطاقة المائية المتجددة', 'Renewable hydropower'],
        'Nuclear energy' => ['nuclear', 'الطاقة النووية', 'Nuclear'],
        'Solid biofuels' => ['biofuel_solid', 'الوقود الحيوي الصلب', 'Solid biofuels'],
        'Geothermal energy' => ['geothermal', 'الطاقة الحرارية الأرضية', 'Geothermal'],
    ];

    private const TYPES = [
        'Electricity Generation (GWh)' => ['gen', 'توليد الكهرباء من', 'Electricity generation from', 'غيغاواط ساعة'],
        'Electrical Installed Capacity (MW)' => ['cap', 'القدرة المركّبة:', 'Installed capacity:', 'ميغاواط'],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::TYPES as [$t, $prefixAr, $prefixEn, $unit]) {
            foreach (self::TECH as [$k, $ar, $en]) {
                $indicators[] = new IndicatorDefinition("irena.{$t}.{$k}", "{$prefixAr} {$ar}", "{$prefixEn} {$en}", $unit, 'A', [new Range(0, 1e8, 'reject')]);
            }
        }

        return new DatasetDefinition(
            key: 'irena.electricity',
            source: new SourceDefinition(key: 'irena', nameAr: 'الوكالة الدولية للطاقة المتجددة (IRENA)', nameEn: 'International Renewable Energy Agency (IRENA)', publisher: 'IRENA', url: 'https://www.irena.org/Data', license: 'CC BY 4.0'),
            nameAr: 'القدرة المركّبة وتوليد الكهرباء حسب التقنية (IRENA)',
            nameEn: 'Electricity capacity and generation by technology (IRENA)',
            section: 'energy',
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
        $meta = $this->http()->timeout(60)->get(self::TABLE)->throw()->json();
        $country = collect($meta['variables'] ?? [])->firstWhere('code', 'Country/area');
        if (! is_array($country)) {
            throw new RuntimeException('IRENA: تغيّر وصف الجدول.');
        }
        $isoByName = array_combine($country['valueTexts'], $country['values']);

        $rows = [];
        foreach (array_chunk($country['values'], 60) as $chunk) {
            $csv = $this->http()->timeout(180)->retry(3, 10_000)->asJson()->post(self::TABLE, [
                'query' => [
                    ['code' => 'Country/area', 'selection' => ['filter' => 'item', 'values' => $chunk]],
                    ['code' => 'Grid connection', 'selection' => ['filter' => 'item', 'values' => ['0']]],
                ],
                'response' => ['format' => 'csv'],
            ])->throw()->body();
            $lines = preg_split('/\r?\n/', trim(ltrim($csv, "\u{FEFF}")));
            array_shift($lines);
            foreach ($lines as $line) {
                [$name, $tech, $type, , $year, $value] = array_pad(str_getcsv($line, ',', '"', ''), 6, null);
                if (isset($isoByName[$name], self::TECH[$tech], self::TYPES[$type]) && is_numeric($value) && preg_match('/^\d{4}$/', (string) $year)) {
                    $rows[] = [$isoByName[$name], self::TYPES[$type][0].'.'.self::TECH[$tech][0], $year, (float) $value];
                }
            }
            usleep(500_000);
        }
        if ($rows === []) {
            throw new RuntimeException('IRENA: لا بيانات.');
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$iso, $key, $year, $value]) {
            if (preg_match('/^[A-Z]{3}$/', (string) $iso)) {
                yield new ParsedRecord('irena.'.$key, $iso, (string) $year, (float) $value);
            }
        }
    }
}
