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
 * الأمن الغذائي (FAO): اعتماد الدولة على استيراد الحبوب، وكلفة واردات الغذاء من الصادرات، وكفاية الطاقة والبروتين، وتقزّم الأطفال.
 * الملف الرسمي الكامل بصيغة ZIP (نحو 50 ميغابايت CSV) يُفكّ في ملف مؤقت ويُقرأ تدفقيًا. الدول برمز UN M49 فتُحوَّل إلى ISO3
 * بمرجع الأمم المتحدة (Reporters). المتوسطات الثلاثية «2021-2023» تُنسب إلى سنتها الأخيرة. الفحص: ترويسة Last-Modified.
 */
final class FaoFoodSecurityConnector extends ApiConnector
{
    private const ZIP = 'https://bulks-faostat.fao.org/production/Food_Security_Data_E_All_Data_(Normalized).zip';

    private const UN_REPORTERS = 'https://comtradeapi.un.org/files/v1/app/reference/Reporters.json';

    /** @var array<int, array{0: string, 1: string, 2: string, 3: string}> رمز بند FAO => [مفتاح، عربي، إنجليزي، وحدة] */
    private const ITEMS = [
        '21035' => ['fao.cereal_import_dependency', 'نسبة اعتماد الحبوب على الاستيراد (متوسط 3 سنوات)', 'Cereal import dependency ratio (3-yr avg)', '%'],
        '21033' => ['fao.food_imports_to_exports', 'واردات الغذاء إلى الصادرات السلعية (متوسط 3 سنوات)', 'Food imports in total merchandise exports (3-yr avg)', '%'],
        '21010' => ['fao.energy_supply_adequacy', 'كفاية الإمداد بالطاقة الغذائية (متوسط 3 سنوات)', 'Dietary energy supply adequacy (3-yr avg)', '%'],
        '21013' => ['fao.protein_supply', 'الإمداد بالبروتين للفرد (متوسط 3 سنوات)', 'Average protein supply (3-yr avg)', 'غرام/يوم'],
        '21025' => ['fao.child_stunting', 'تقزّم الأطفال دون الخامسة', 'Children under 5 who are stunted', '%'],
        '21043' => ['fao.anemia_women', 'فقر الدم بين النساء في سن الإنجاب', 'Anaemia among women of reproductive age', '%'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::ITEMS as [$key, $ar, $en, $unit]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(-2000, 5000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'fao.food_security',
            source: new SourceDefinition(
                key: 'fao',
                nameAr: 'منظمة الأغذية والزراعة (FAO)',
                nameEn: 'Food and Agriculture Organization (FAOSTAT)',
                publisher: 'FAO',
                url: 'https://www.fao.org/faostat',
                license: 'CC BY-NC-SA 3.0 IGO',
            ),
            nameAr: 'الأمن الغذائي (FAO)',
            nameEn: 'Food security (FAO)',
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

        return new ProbeResult(token: $modified, version: 'FAOSTAT Food Security', publishedAt: $res->header('Last-Modified') ? CarbonImmutable::parse($res->header('Last-Modified'))->utc() : null);
    }

    public function fetch(): RawPayload
    {
        $iso = $this->isoByM49();
        $zipPath = tempnam(sys_get_temp_dir(), 'fao');
        try {
            $this->http()->timeout(300)->sink($zipPath)->get(self::ZIP)->throw();
            $zip = new ZipArchive;
            if ($zip->open($zipPath) !== true) {
                throw new RuntimeException('FAO: تعذّر فتح ملف ZIP.');
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
                throw new RuntimeException('FAO: ملف CSV المطلوب غير موجود داخل ZIP.');
            }

            $header = fgetcsv($stream, 0, ',', '"', '') ?: [];
            $col = array_flip(array_map(fn ($h) => ltrim((string) $h, "\u{FEFF}"), $header));
            foreach (['Area Code (M49)', 'Item Code', 'Year', 'Value'] as $needed) {
                if (! isset($col[$needed])) {
                    throw new RuntimeException("FAO: تغيّر شكل الملف (العمود {$needed}).");
                }
            }
            $rows = [];
            while (($r = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $item = $r[$col['Item Code']] ?? '';
                if (! isset(self::ITEMS[$item]) || ! is_numeric($r[$col['Value']] ?? null)) {
                    continue;
                }
                $country = $iso[(int) ltrim((string) $r[$col['Area Code (M49)']], "'")] ?? null;
                if ($country !== null && preg_match('/^(\d{4})(?:-(\d{4}))?$/', (string) $r[$col['Year']], $m)) {
                    $rows[] = [$item, $country, $m[2] ?? $m[1], $r[$col['Value']]];
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
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$item, $country, $year, $value]) {
            yield new ParsedRecord(self::ITEMS[$item][0], $country, (string) $year, (float) $value);
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
