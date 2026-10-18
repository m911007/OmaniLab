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
 * الإنتاج الزراعي والحيواني (FAOSTAT QCL) لكل الدول: الخضار والفاكهة والتمور والحبوب والحليب واللحوم والبيض بالطن، ومساحة وغلة التمور، وأعداد الأغنام والماعز والإبل والأبقار.
 * الملف الرسمي ZIP (نحو 34 ميغابايت، 545 ميغابايت CSV) يُقرأ تدفقيًا. الدول برمز M49 تُحوَّل إلى ISO3 بمرجع الأمم المتحدة.
 */
final class FaoProductionConnector extends ApiConnector
{
    private const ZIP = 'https://bulks-faostat.fao.org/production/Production_Crops_Livestock_E_All_Data_(Normalized).zip';

    private const UN_REPORTERS = 'https://comtradeapi.un.org/files/v1/app/reference/Reporters.json';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: float}> "بند|عنصر" => [مفتاح، عربي، إنجليزي، وحدة، (محجوز)، (محجوز)، حد أعلى] */
    private const SERIES = [
        '1735|5510' => ['fao.prod_vegetables', 'إنتاج الخضروات', 'Vegetables production', 'طن', '', '', 1e10],
        '1738|5510' => ['fao.prod_fruit', 'إنتاج الفاكهة', 'Fruit production', 'طن', '', '', 1e10],
        '577|5510' => ['fao.prod_dates', 'إنتاج التمور', 'Dates production', 'طن', '', '', 1e9],
        '577|5312' => ['fao.area_dates', 'مساحة حصاد التمور', 'Dates area harvested', 'هكتار', '', '', 1e9],
        '577|5412' => ['fao.yield_dates', 'غلة نخيل التمر', 'Dates yield', 'كغم/هكتار', '', '', 1e8],
        '388|5510' => ['fao.prod_tomatoes', 'إنتاج الطماطم', 'Tomatoes production', 'طن', '', '', 1e10],
        '1717|5510' => ['fao.prod_cereals', 'إنتاج الحبوب', 'Cereals production', 'طن', '', '', 1e10],
        '1780|5510' => ['fao.prod_milk', 'إنتاج الحليب', 'Milk production', 'طن', '', '', 1e10],
        '1765|5510' => ['fao.prod_meat', 'إنتاج اللحوم', 'Meat production', 'طن', '', '', 1e10],
        '1783|5510' => ['fao.prod_eggs', 'إنتاج البيض', 'Eggs production', 'طن', '', '', 1e10],
        '976|5111' => ['fao.stock_sheep', 'عدد الأغنام', 'Sheep stocks', 'رأس', '', '', 1e10],
        '1016|5111' => ['fao.stock_goats', 'عدد الماعز', 'Goat stocks', 'رأس', '', '', 1e10],
        '1126|5111' => ['fao.stock_camels', 'عدد الإبل', 'Camel stocks', 'رأس', '', '', 1e10],
        '866|5111' => ['fao.stock_cattle', 'عدد الأبقار', 'Cattle stocks', 'رأس', '', '', 1e10],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as [$key, $ar, $en, $unit, , , $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'fao.production',
            source: new SourceDefinition(
                key: 'fao',
                nameAr: 'منظمة الأغذية والزراعة (FAO)',
                nameEn: 'Food and Agriculture Organization (FAOSTAT)',
                publisher: 'FAO',
                url: 'https://www.fao.org/faostat',
                license: 'CC BY-NC-SA 3.0 IGO',
            ),
            nameAr: 'الإنتاج الزراعي والحيواني (FAO)',
            nameEn: 'Crop and livestock production (FAO)',
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

        return new ProbeResult(token: $modified, version: 'FAOSTAT QCL', publishedAt: $res->header('Last-Modified') ? CarbonImmutable::parse($res->header('Last-Modified'))->utc() : null);
    }

    public function fetch(): RawPayload
    {
        $iso = $this->isoByM49();
        $zipPath = tempnam(sys_get_temp_dir(), 'faoqcl');
        try {
            $this->http()->timeout(600)->sink($zipPath)->get(self::ZIP)->throw();
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('FAO QCL: تعذّر فتح ملف ZIP.');
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
                throw new RuntimeException('FAO QCL: ملف CSV غير موجود داخل ZIP.');
            }
            $header = fgetcsv($stream, 0, ',', '"', '') ?: [];
            $col = array_flip(array_map(fn ($h) => ltrim((string) $h, "\u{FEFF}"), $header));
            foreach (['Area Code (M49)', 'Item Code', 'Element Code', 'Year', 'Value'] as $needed) {
                if (! isset($col[$needed])) {
                    throw new RuntimeException("FAO QCL: تغيّر شكل الملف (العمود {$needed}).");
                }
            }
            $rows = [];
            while (($r = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $key = ($r[$col['Item Code']] ?? '').'|'.($r[$col['Element Code']] ?? '');
                if (! isset(self::SERIES[$key]) || ! is_numeric($r[$col['Value']] ?? null)) {
                    continue;
                }
                $country = $iso[(int) ltrim((string) $r[$col['Area Code (M49)']], "'")] ?? null;
                if ($country !== null && preg_match('/^\d{4}$/', (string) $r[$col['Year']])) {
                    $rows[] = [$key, $country, $r[$col['Year']], $r[$col['Value']]];
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
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $country, $year, $value]) {
            if (isset(self::SERIES[$key])) {
                yield new ParsedRecord(self::SERIES[$key][0], $country, (string) $year, (float) $value);
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
        if (count($map) < 150) {
            throw new RuntimeException('مرجع رموز الدول لدى الأمم المتحدة غير مكتمل: '.count($map));
        }

        return $map;
    }
}
