<?php

namespace App\Modules\Fao\Connectors;

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
use ZipArchive;

/**
 * الموازين الغذائية (FAOSTAT FBS) لكل الدول منذ 2010: لكل سلعة غذائية الإنتاج والواردات والصادرات والمتاح للاستهلاك
 * المحلي والمستهلك غذاءً واستهلاك الفرد السنوي، ومنها نسبة الاكتفاء الذاتي (الإنتاج ÷ المتاح للاستهلاك).
 * إلى جانب السعرات والبروتين والدهون المتاحة للفرد يوميًا. الملف الرسمي ZIP (نحو 55 ميغابايت) يُقرأ تدفقيًا.
 *
 * الإنتاج الذي «قدّرته المنظمة بالإسناد» (العلامة I: لم تتلقَّ رقمًا من الدولة) لا يُنشر ولا تُحسب منه نسبة الاكتفاء:
 * لعُمان مثلًا تُسند المنظمة إنتاج الدواجن 7 آلاف طن ثابتة سنويًا بينما يعلن المركز الوطني للإحصاء نحو 130 ألف طن.
 */
final class FaoFoodBalanceConnector extends ApiConnector
{
    private const ZIP = 'https://bulks-faostat.fao.org/production/FoodBalanceSheets_E_All_Data_(Normalized).zip';

    private const UN_REPORTERS = 'https://comtradeapi.un.org/files/v1/app/reference/Reporters.json';

    /** @var array<string, array{0: string, 1: string, 2: string}> رمز البند => [مقطع المفتاح، عربي، إنجليزي] */
    public const ITEMS = [
        '2511' => ['wheat', 'القمح', 'Wheat'],
        '2807' => ['rice', 'الأرز', 'Rice'],
        '2905' => ['cereals', 'الحبوب', 'Cereals'],
        '2918' => ['vegetables', 'الخضروات', 'Vegetables'],
        '2919' => ['fruits', 'الفواكه', 'Fruits'],
        '2619' => ['dates', 'التمور', 'Dates'],
        '2731' => ['beef', 'لحوم الأبقار', 'Bovine meat'],
        '2732' => ['mutton', 'لحوم الضأن والماعز', 'Mutton & goat meat'],
        '2734' => ['poultry', 'لحوم الدواجن', 'Poultry meat'],
        '2948' => ['milk', 'الألبان', 'Milk'],
        '2949' => ['eggs', 'البيض', 'Eggs'],
        '2960' => ['fish', 'الأسماك', 'Fish & seafood'],
        '2909' => ['sugar', 'السكر والمحليات', 'Sugar & sweeteners'],
        '2914' => ['oils', 'الزيوت النباتية', 'Vegetable oils'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string}> رمز العنصر => [مقطع المفتاح، عربي، إنجليزي، وحدة] */
    public const ELEMENTS = [
        '5511' => ['prod', 'إنتاج', 'production', 'ألف طن'],
        '5611' => ['imp', 'واردات', 'imports', 'ألف طن'],
        '5911' => ['exp', 'صادرات', 'exports', 'ألف طن'],
        '5301' => ['supply', 'المتاح للاستهلاك المحلي من', 'domestic supply', 'ألف طن'],
        '5142' => ['food', 'المستهلك غذاءً من', 'food use', 'ألف طن'],
        '645' => ['kgcap', 'استهلاك الفرد من', 'food supply per capita', 'كغم/سنة'],
    ];

    /** إجمالي النظام الغذائي (البند 2901): السعرات والبروتين والدهون المتاحة للفرد يوميًا. */
    private const TOTALS = [
        '664' => ['fao.fbs.kcal', 'السعرات الحرارية المتاحة للفرد', 'Food supply (kcal/capita/day)', 'سعرة/يوم'],
        '674' => ['fao.fbs.protein', 'البروتين المتاح للفرد', 'Protein supply (g/capita/day)', 'غرام/يوم'],
        '684' => ['fao.fbs.fat', 'الدهون المتاحة للفرد', 'Fat supply (g/capita/day)', 'غرام/يوم'],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function revision(): int
    {
        return 2; // استبعاد الإنتاج المُسنَد (العلامة I)
    }

    public static function key(string $item, string $metric): string
    {
        return 'fao.fbs.'.self::ITEMS[$item][0].'.'.$metric;
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::ITEMS as $code => [, $ar, $en]) {
            foreach (self::ELEMENTS as [$metric, $mAr, $mEn, $unit]) {
                $indicators[] = new IndicatorDefinition(self::key($code, $metric), "{$mAr} {$ar}", "{$en} — {$mEn}", $unit, 'A', [new Range(0, $metric === 'kgcap' ? 2000 : 1e6, 'reject')]);
            }
            $indicators[] = new IndicatorDefinition(self::key($code, 'ssr'), "نسبة الاكتفاء الذاتي من {$ar}", "{$en} — self-sufficiency ratio", '%', 'A', [new Range(0, 1e5, 'reject')]);
        }
        foreach (self::TOTALS as [$key, $ar, $en, $unit]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, 10000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'fao.food_balance',
            source: new SourceDefinition(
                key: 'fao',
                nameAr: 'منظمة الأغذية والزراعة (FAO)',
                nameEn: 'Food and Agriculture Organization (FAOSTAT)',
                publisher: 'FAO',
                url: 'https://www.fao.org/faostat',
                license: 'CC BY-NC-SA 3.0 IGO',
            ),
            nameAr: 'الموازين الغذائية: الإنتاج والاستهلاك والاكتفاء الذاتي (FAO)',
            nameEn: 'Food balance sheets: production, consumption and self-sufficiency (FAO)',
            section: 'society',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        $res = $this->http()->head(self::ZIP);
        $modified = $res->header('Last-Modified') ?: $res->header('ETag');
        if (! $res->successful() || ! $modified) {
            return new ProbeResult(token: null);
        }

        return new ProbeResult(token: $modified, version: 'FAOSTAT FBS', publishedAt: $res->header('Last-Modified') ? CarbonImmutable::parse($res->header('Last-Modified'))->utc() : null);
    }

    public function fetch(): RawPayload
    {
        $iso = $this->isoByM49();
        $zipPath = tempnam(sys_get_temp_dir(), 'faofbs');
        try {
            $this->http()->timeout(900)->sink($zipPath)->get(self::ZIP)->throw();
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('FAO FBS: تعذّر فتح ملف ZIP.');
            }
            $name = null;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = (string) $zip->getNameIndex($i);
                if (str_ends_with($entry, '(Normalized).csv')) {
                    $name = $entry;
                }
            }
            $stream = $name === null ? false : $zip->getStream($name);
            if ($stream === false) {
                throw new RuntimeException('FAO FBS: ملف CSV غير موجود داخل ZIP.');
            }
            $header = fgetcsv($stream, 0, ',', '"', '') ?: [];
            $col = array_flip(array_map(fn ($h) => ltrim((string) $h, "\u{FEFF}"), $header));
            foreach (['Area Code (M49)', 'Item Code', 'Element Code', 'Year', 'Value', 'Flag'] as $needed) {
                if (! isset($col[$needed])) {
                    throw new RuntimeException("FAO FBS: تغيّر شكل الملف (العمود {$needed}).");
                }
            }
            $rows = [];
            while (($r = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $item = (string) ($r[$col['Item Code']] ?? '');
                $element = (string) ($r[$col['Element Code']] ?? '');
                $wanted = (isset(self::ITEMS[$item]) && isset(self::ELEMENTS[$element])) || ($item === '2901' && isset(self::TOTALS[$element]));
                if (! $wanted || ! is_numeric($r[$col['Value']] ?? null)) {
                    continue;
                }
                $country = $iso[(int) ltrim((string) $r[$col['Area Code (M49)']], "'")] ?? null;
                if ($country !== null && preg_match('/^\d{4}$/', (string) $r[$col['Year']])) {
                    $rows[] = [$item, $element, $country, $r[$col['Year']], $r[$col['Value']], (string) ($r[$col['Flag']] ?? '')];
                }
            }
            fclose($stream);
            $zip->close();
        } finally {
            @unlink($zipPath);
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        // الإنتاج والمتاح لكل (سلعة، دولة، سنة) لحساب نسبة الاكتفاء الذاتي
        $balance = [];
        $rows = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        // سلع وسنوات إنتاجها مُسنَد من المنظمة لا من الدولة: يُستبعد الإنتاج وكل ما بُني عليه (المتاح والمستهلك واستهلاك الفرد)،
        // وتبقى الواردات والصادرات لأنها من بيانات التجارة الفعلية
        $imputed = [];
        foreach ($rows as $row) {
            if ($row[1] === '5511' && ($row[5] ?? '') === 'I') {
                $imputed[$row[0].'|'.$row[2].'|'.$row[3]] = true;
            }
        }
        foreach ($rows as $row) {
            [$item, $element, $country, $year, $value] = $row;
            $value = (float) $value;
            if (isset($imputed[$item.'|'.$country.'|'.$year]) && in_array($element, ['5511', '5301', '5142', '645'], true)) {
                continue;
            }
            if ($item === '2901') {
                if (isset(self::TOTALS[$element])) {
                    yield new ParsedRecord(self::TOTALS[$element][0], $country, (string) $year, $value);
                }

                continue;
            }
            if (! isset(self::ITEMS[$item], self::ELEMENTS[$element])) {
                continue;
            }
            yield new ParsedRecord(self::key($item, self::ELEMENTS[$element][0]), $country, (string) $year, $value);
            if ($element === '5511' || $element === '5301') {
                $balance[$item.'|'.$country.'|'.$year][$element] = $value;
            }
        }

        foreach ($balance as $k => $b) {
            [$item, $country, $year] = explode('|', $k);
            if (isset($b['5511'], $b['5301']) && $b['5301'] > 0) {
                yield new ParsedRecord(self::key($item, 'ssr'), $country, $year, round($b['5511'] / $b['5301'] * 100, 2));
            }
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
            throw new RuntimeException('FAO FBS: تعذّر تحميل جدول رموز الدول.');
        }

        return $map;
    }
}
