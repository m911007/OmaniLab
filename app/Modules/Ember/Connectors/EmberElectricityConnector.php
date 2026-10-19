<?php

namespace App\Modules\Ember\Connectors;

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
 * الكهرباء عالميًا من منظمة Ember (ملف CSV الرسمي المفتوح CC-BY): التوليد بالتيراواط ساعة وحصصه حسب المصدر (غاز وفحم ونووي ورياح وشمس…)،
 * والقدرة المركّبة بالجيجاواط، والطلب ونصيب الفرد، وانبعاثات القطاع وكثافة الكربون، وصافي الاستيراد. لكل الدول سنويًا منذ 2000.
 * الملف نحو 50 ميغابايت: يُنزَّل إلى ملف مؤقت ويُقرأ سطرًا سطرًا، ويُحتفظ بالمتغيرات المعتمدة للدول ذات رمز ISO3 فقط.
 */
final class EmberElectricityConnector extends ApiConnector
{
    private const URL = 'https://files.ember-energy.org/public-downloads/yearly_full_release_long_format.csv';

    private const FUELS = [
        'Coal' => ['فحم', 'coal'], 'Gas' => ['غاز', 'gas'], 'Nuclear' => ['نووي', 'nuclear'], 'Hydro' => ['كهرمائي', 'hydro'], 'Solar' => ['شمسي', 'solar'],
        'Wind' => ['رياح', 'wind'], 'Bioenergy' => ['طاقة حيوية', 'bioenergy'], 'Other Fossil' => ['وقود أحفوري آخر', 'other_fossil'], 'Clean' => ['نظيفة', 'clean'],
        'Fossil' => ['أحفورية', 'fossil'], 'Renewables' => ['متجددة', 'renewables'], 'Wind and Solar' => ['رياح وشمس', 'wind_solar'],
    ];

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: float}> مفتاح => [فئة، فئة فرعية، متغير، وحدة، عربي، إنجليزي، حد أعلى] */
    private function variables(): array
    {
        $v = [
            'ember.total_generation' => ['Electricity generation', 'Total', 'Total Generation', 'TWh', 'إجمالي توليد الكهرباء', 'Total electricity generation', 1e5],
            'ember.demand' => ['Electricity demand', 'Demand', 'Demand', 'TWh', 'الطلب على الكهرباء', 'Electricity demand', 1e5],
            'ember.demand_per_capita' => ['Electricity demand', 'Demand per capita', 'Demand per capita', 'MWh', 'استهلاك الفرد من الكهرباء', 'Electricity demand per capita', 1e3],
            'ember.net_imports' => ['Electricity imports', 'Electricity imports', 'Net Imports', 'TWh', 'صافي استيراد الكهرباء', 'Net electricity imports', 1e5],
            'ember.power_emissions' => ['Power sector emissions', 'Total', 'Total emissions', 'mtCO2', 'انبعاثات قطاع الكهرباء', 'Power sector emissions', 1e5],
            'ember.co2_intensity' => ['Power sector emissions', 'CO2 intensity', 'CO2 intensity', 'gCO2/kWh', 'كثافة الكربون في الكهرباء', 'Power CO2 intensity', 5000],
        ];
        foreach (self::FUELS as $name => [$ar, $slug]) {
            $sub = in_array($name, ['Clean', 'Fossil', 'Renewables', 'Wind and Solar'], true) ? 'Aggregate fuel' : 'Fuel';
            $v["ember.gen_{$slug}"] = ['Electricity generation', $sub, $name, 'TWh', "التوليد من {$ar}", "Generation: {$name}", 1e5];
            $v["ember.share_{$slug}"] = ['Electricity generation', $sub, $name, '%', "حصة {$ar} من التوليد", "Share of generation: {$name}", 100.5];
        }
        foreach (['Coal', 'Gas', 'Nuclear', 'Hydro', 'Solar', 'Wind', 'Bioenergy', 'Clean', 'Fossil', 'Renewables'] as $name) {
            [$ar, $slug] = self::FUELS[$name];
            $sub = in_array($name, ['Clean', 'Fossil', 'Renewables'], true) ? 'Aggregate fuel' : 'Fuel';
            $v["ember.capacity_{$slug}"] = ['Capacity', $sub, $name, 'GW', "القدرة المركّبة: {$ar}", "Installed capacity: {$name}", 1e4];
        }

        return $v;
    }

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach ($this->variables() as $key => [, , , $unit, $ar, $en, $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(-$max, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'ember.electricity',
            source: new SourceDefinition(key: 'ember', nameAr: 'منظمة Ember للطاقة', nameEn: 'Ember (global electricity data)', publisher: 'Ember', url: 'https://ember-energy.org/data/yearly-electricity-data/', license: 'CC-BY 4.0'),
            nameAr: 'الكهرباء عالميًا: التوليد والقدرة والانبعاثات',
            nameEn: 'Global electricity: generation, capacity, emissions',
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
        $path = tempnam(sys_get_temp_dir(), 'ember');
        try {
            $this->httpAccepting('text/csv')->timeout(600)->sink($path)->get(self::URL)->throw();
            $handle = fopen($path, 'r');
            $header = $handle ? fgetcsv($handle, 0, ',', '"', '') : false;
            $col = $header ? array_flip($header) : [];
            foreach (['ISO 3 code', 'Year', 'Category', 'Subcategory', 'Variable', 'Unit', 'Value', 'Area'] as $need) {
                if (! isset($col[$need])) {
                    throw new RuntimeException("Ember: تغيّر شكل الملف (العمود {$need}).");
                }
            }
            $wanted = [];
            foreach ($this->variables() as $key => [$cat, $sub, $var, $unit]) {
                $wanted["{$cat}|{$sub}|{$var}|{$unit}"] = $key;
            }
            $out = [];
            $names = [];
            while (($f = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $iso = $f[$col['ISO 3 code']] ?? '';
                $key = $wanted["{$f[$col['Category']]}|{$f[$col['Subcategory']]}|{$f[$col['Variable']]}|{$f[$col['Unit']]}"] ?? null;
                $value = $f[$col['Value']] ?? '';
                if ($key === null || ! preg_match('/^[A-Z]{3}$/', $iso) || ! is_numeric($value) || ! preg_match('/^\d{4}$/', $f[$col['Year']])) {
                    continue;
                }
                $out[] = [$key, $iso, $f[$col['Year']], (float) $value];
                $names[$iso] = $f[$col['Area']];
            }
            fclose($handle);
        } finally {
            @unlink($path);
        }
        if ($out === []) {
            throw new RuntimeException('Ember: لا بيانات.');
        }

        return new RawPayload(json_encode(['names' => $names, 'rows' => $out], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        foreach ($data['names'] as $iso => $name) {
            yield new ParsedEntity($iso, $name, 'country');
        }
        foreach ($data['rows'] as [$key, $iso, $year, $value]) {
            yield new ParsedRecord($key, $iso, $year, $value);
        }
    }
}
