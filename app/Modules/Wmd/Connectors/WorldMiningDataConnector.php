<?php

namespace App\Modules\Wmd\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Support\CountryNames;
use App\Core\Support\XlsxReader;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * إنتاج 65 معدنًا وخامًا ومصدر طاقة حسب الدولة (World Mining Data، وزارة المالية النمساوية، جدول 6.4): الحديد والنحاس والذهب والكروم والجبس والملح
 * والكبريت والنفط والغاز واليورانيوم وغيرها، لنحو 170 دولة وآخر 5 سنوات في كل إصدار (تتراكم السنوات عبر الإصدارات).
 * رابط الملف يتغير مع كل إصدار فيُستخرج من صفحة الوزارة. الدول بأسمائها الإنجليزية تُحوَّل إلى ISO3 وما لا يُعرف يُتخطى.
 */
final class WorldMiningDataConnector extends ApiConnector
{
    private const PAGE = 'https://www.bmf.gv.at/en/topics/mining/mineral-resources-policy/wmd.html';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> اسم الورقة => [رمز، عربي، وحدة عربية، وحدة المصدر] */
    private const SHEETS = [
        'Iron (Fe)' => ['iron', 'الحديد', 'طن', 'metr. t'],
        'Chromium (Cr2O3)' => ['chromium', 'الكروم', 'طن', 'metr. t'],
        'Cobalt' => ['cobalt', 'الكوبالت', 'طن', 'metr. t'],
        'Manganese' => ['manganese', 'المنغنيز', 'طن', 'metr. t'],
        'Molybdenum' => ['molybdenum', 'الموليبدينوم', 'طن', 'metr. t'],
        'Nickel' => ['nickel', 'النيكل', 'طن', 'metr. t'],
        'Niobium (Nb2O5)' => ['niobium', 'النيوبيوم', 'طن', 'metr. t'],
        'Tantalum (Ta2O5)' => ['tantalum', 'التنتالوم', 'طن', 'metr. t'],
        'Titanium (TiO2)' => ['titanium', 'التيتانيوم', 'طن', 'metr. t'],
        'Tungsten (W)' => ['tungsten', 'التنغستن', 'طن', 'metr. t'],
        'Vanadium (V)' => ['vanadium', 'الفاناديوم', 'طن', 'metr. t'],
        'Aluminium' => ['aluminium', 'الألمنيوم', 'طن', 'metr. t'],
        'Antimony' => ['antimony', 'الأنتيمون', 'طن', 'metr. t'],
        'Arsenic' => ['arsenic', 'الزرنيخ', 'طن', 'metr. t'],
        'Bauxite' => ['bauxite', 'البوكسيت', 'طن', 'metr. t'],
        'Beryllium (conc.)' => ['beryllium', 'البيريليوم', 'طن', 'metr. t'],
        'Bismuth' => ['bismuth', 'البزموت', 'طن', 'metr. t'],
        'Cadmium' => ['cadmium', 'الكادميوم', 'طن', 'metr. t'],
        'Copper' => ['copper', 'النحاس', 'طن', 'metr. t'],
        'Gallium' => ['gallium', 'الغاليوم', 'طن', 'metr. t'],
        'Germanium' => ['germanium', 'الجرمانيوم', 'طن', 'metr. t'],
        'Indium' => ['indium', 'الإنديوم', 'طن', 'metr. t'],
        'Lead' => ['lead', 'الرصاص', 'طن', 'metr. t'],
        'Lithium (Li2O)' => ['lithium', 'الليثيوم', 'طن', 'metr. t'],
        'Mercury' => ['mercury', 'الزئبق', 'طن', 'metr. t'],
        'Rare Earths (REO)' => ['rare_earths', 'العناصر الأرضية النادرة', 'طن', 'metr. t'],
        'Rhenium' => ['rhenium', 'الرينيوم', 'كغ', 'kg'],
        'Selenium' => ['selenium', 'السيلينيوم', 'طن', 'metr. t'],
        'Tellurium' => ['tellurium', 'التيلوريوم', 'طن', 'metr. t'],
        'Tin' => ['tin', 'القصدير', 'طن', 'metr. t'],
        'Zinc' => ['zinc', 'الزنك', 'طن', 'metr. t'],
        'Gold' => ['gold', 'الذهب', 'كغ', 'kg'],
        'Palladium' => ['palladium', 'البلاديوم', 'كغ', 'kg'],
        'Platinum' => ['platinum', 'البلاتين', 'كغ', 'kg'],
        'Rhodium' => ['rhodium', 'الروديوم', 'كغ', 'kg'],
        'Silver' => ['silver', 'الفضة', 'كغ', 'kg'],
        'Asbestos' => ['asbestos', 'الأسبستوس', 'طن', 'metr. t'],
        'Baryte' => ['baryte', 'الباريت', 'طن', 'metr. t'],
        'Bentonite' => ['bentonite', 'البنتونيت', 'طن', 'metr. t'],
        'Boron Minerals' => ['boron', 'معادن البورون', 'طن', 'metr. t'],
        'Diamonds (Gem)' => ['diamonds_gem', 'الماس (الزينة)', 'قيراط', 'ct'],
        'Diamonds (Ind)' => ['diamonds_ind', 'الماس (الصناعي)', 'قيراط', 'ct'],
        'Diatomite' => ['diatomite', 'الدياتوميت', 'طن', 'metr. t'],
        'Feldspar' => ['feldspar', 'الفلسبار', 'طن', 'metr. t'],
        'Fluorspar' => ['fluorspar', 'الفلورسبار', 'طن', 'metr. t'],
        'Graphite' => ['graphite', 'الجرافيت', 'طن', 'metr. t'],
        'Gypsum and Anhydrite' => ['gypsum', 'الجبس والأنهيدريت', 'طن', 'metr. t'],
        'Kaolin (China-Clay)' => ['kaolin', 'الكاولين', 'طن', 'metr. t'],
        'Magnesite' => ['magnesite', 'المغنيسيت', 'طن', 'metr. t'],
        'Perlite' => ['perlite', 'البيرلايت', 'طن', 'metr. t'],
        'Phosphate Rock (P2O5)' => ['phosphate', 'صخر الفوسفات', 'طن', 'metr. t'],
        'Potash (K2O)' => ['potash', 'البوتاس', 'طن', 'metr. t'],
        'Salt (rock, brines, marine)' => ['salt', 'الملح', 'طن', 'metr. t'],
        'Sulfur (elementar & industrial)' => ['sulfur', 'الكبريت', 'طن', 'metr. t'],
        'Talc, Steatite & Pyrophyllite' => ['talc', 'التلك', 'طن', 'metr. t'],
        'Vermiculite' => ['vermiculite', 'الفيرميكوليت', 'طن', 'metr. t'],
        'Zircon' => ['zircon', 'الزركون', 'طن', 'metr. t'],
        'Steam Coal' => ['steam_coal', 'الفحم البخاري', 'طن', 'metr. t'],
        'Coking Coal' => ['coking_coal', 'فحم الكوك', 'طن', 'metr. t'],
        'Lignite' => ['lignite', 'الليغنيت', 'طن', 'metr. t'],
        'Natural Gas' => ['natural_gas', 'الغاز الطبيعي', 'مليون م³', 'Mio m3'],
        'Petroleum' => ['petroleum', 'النفط', 'طن', 'metr. t'],
        'Oil Sands (part of Petroleum)' => ['oil_sands', 'الرمال النفطية', 'طن', 'metr. t'],
        'Oil Shales' => ['oil_shales', 'الصخر الزيتي', 'طن', 'metr. t'],
        'Uranium (U3O8)' => ['uranium', 'اليورانيوم', 'طن', 'metr. t'],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SHEETS as $sheet => [$slug, $ar, $unitAr]) {
            $indicators[] = new IndicatorDefinition("wmd.{$slug}", "إنتاج {$ar}", "Production: {$sheet}", $unitAr, 'A', [new Range(0, 1e11, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'wmd.mineral_production',
            source: new SourceDefinition(key: 'wmd', nameAr: 'World Mining Data (وزارة المالية النمساوية)', nameEn: 'World Mining Data (Austrian Federal Ministry of Finance)', publisher: 'BMF Austria', url: 'https://www.bmf.gv.at/en/topics/mining/mineral-resources-policy/wmd.html', license: 'مع الإشارة إلى المصدر'),
            nameAr: 'إنتاج المعادن والخامات حسب الدولة (65 مادة)',
            nameEn: 'Mineral production by country (65 commodities)',
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
        $page = $this->httpAccepting('text/html')->timeout(60)->retry(3, 3000)->get(self::PAGE)->throw()->body();
        if (! preg_match('#href="(https://www\.bmf\.gv\.at/dam/[^"]*/6\.4\.[^"]+\.xlsx)"#', $page, $m)) {
            throw new RuntimeException('WMD: لم يُعثر على رابط الجدول 6.4 في الصفحة.');
        }
        $path = tempnam(sys_get_temp_dir(), 'wmd');
        $out = [];
        $names = [];
        try {
            $this->http()->timeout(300)->sink($path)->get($m[1])->throw();
            $book = new XlsxReader($path);
            foreach (self::SHEETS as $sheet => [$slug, , , $unit]) {
                $name = in_array($sheet, $book->sheetNames(), true) ? $sheet : (collect($book->sheetNames())->first(fn ($n) => trim($n) === $sheet));
                if ($name === null) {
                    continue;
                }
                $rows = $book->sheet($name);
                $years = [];
                foreach ($rows as $row) {
                    if (($row['A'] ?? null) === 'Country') {
                        foreach ($row as $col => $label) {
                            if ($label !== null && preg_match('/^\d{4}$/', $label)) {
                                $years[$col] = $label;
                            }
                        }
                        break;
                    }
                }
                foreach ($rows as $row) {
                    $iso = CountryNames::iso($row['A'] ?? null);
                    if ($iso === null || ($row['B'] ?? '') !== $unit) {
                        continue;
                    }
                    $names[$iso] = (string) $row['A'];
                    foreach ($years as $col => $year) {
                        if (isset($row[$col]) && is_numeric($row[$col])) {
                            $out[] = ["wmd.{$slug}", $iso, $year, (float) $row[$col]];
                        }
                    }
                }
            }
        } finally {
            @unlink($path);
        }
        if ($out === []) {
            throw new RuntimeException('WMD: لا بيانات.');
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
