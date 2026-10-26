<?php

namespace App\Modules\Mafwr\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Support\XlsxReader;
use App\Core\Validation\Rules\Range;
use Carbon\CarbonImmutable;
use RuntimeException;
use Throwable;

/**
 * البيانات المفتوحة لوزارة الثروة الزراعية والسمكية وموارد المياه (mafwr.gov.om/public/open-data)، بترخيص البيانات الحكومية المفتوحة.
 * قائمة المجموعات من واجهة المحتوى العامة، والملفات بمفتاح تنزيل عام يصدره الموقع لكل زائر (ACTION_DOWNLOAD عبر gRPC-web).
 * تُلخَّص الجداول إلى سلاسل سنوية وطنية: الإنتاج الزراعي والمساحة حسب المجموعة والمحصول، والتمور، والماشية، والإنتاج الحيواني،
 * والسدود (عددًا وسعةً تراكميًا بسنة التنفيذ)، والأفلاج (حية وميتة)، ومحطات الاستمطار.
 */
final class MafwrOpenDataConnector extends MafwrConnector
{
    /** مجموعات المحاصيل: عنوانها في الملف => مقطع المفتاح، عربي */
    private const GROUPS = [
        'الخضروات' => ['veg', 'الخضروات'],
        'محاصيل فاكهة' => ['fruit', 'الفاكهة'],
        'محاصيل علفية' => ['fodder', 'الأعلاف'],
        'محاصيل حقلية' => ['field', 'المحاصيل الحقلية'],
    ];

    /** محاصيل رئيسية (بداية الاسم في الملف بعد حذف المسافات) => مقطع المفتاح، عربي */
    private const CROPS = [
        'حشيشةالرودس' => ['rhodes', 'حشيشة الرودس'],
        'برسيم' => ['alfalfa', 'البرسيم (القت)'],
        'نخيلالتمور' => ['dates', 'نخيل التمور'],
        'طماطم' => ['tomato', 'الطماطم'],
        'فلفل' => ['pepper', 'الفلفل'],
        'خيار' => ['cucumber', 'الخيار'],
        'بطيخ' => ['watermelon', 'البطيخ'],
        'شمام' => ['melon', 'الشمام'],
        'بطاطس' => ['potato', 'البطاطس'],
        'بصل' => ['onion', 'البصل'],
        'ملفوف' => ['cabbage', 'الملفوف'],
        'جزر' => ['carrot', 'الجزر'],
        'موز' => ['banana', 'الموز'],
        'مانجو' => ['mango', 'المانجو'],
        'ليمون' => ['lime', 'الليمون'],
        'مسيبلو' => ['sorghum_fodder', 'المسيبلو (علف)'],
        'ذرةشامية' => ['maize_fodder', 'الذرة الشامية (علف)'],
        'شعير' => ['barley_fodder', 'الشعير (علف)'],
        'قمح' => ['wheat', 'القمح'],
    ];

    private const LIVESTOCK = ['ابقار' => ['cattle', 'الأبقار'], 'جمال' => ['camels', 'الإبل'], 'ضأن' => ['sheep', 'الضأن'], 'ماعز' => ['goats', 'الماعز']];

    private const RED_MEAT = ['أبقار' => ['beef', 'لحوم الأبقار'], 'ضأن' => ['mutton', 'لحوم الضأن'], 'ماعز' => ['goat', 'لحوم الماعز'], 'جمال' => ['camel', 'لحوم الإبل']];

    private const DAM_TYPES = ['تخزيني' => ['storage', 'سدود التخزين السطحي'], 'تغذية' => ['recharge', 'سدود التغذية الجوفية'], 'حماية' => ['protection', 'سدود الحماية من الفيضانات']];

    public function definition(): DatasetDefinition
    {
        $ind = [];
        $add = function (string $key, string $ar, string $en, string $unit, float $max = 1e10) use (&$ind): void {
            $ind[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, $max, 'reject')]);
        };

        $add('mafwr.crop.total.prod', 'إجمالي الإنتاج الزراعي (الوزارة)', 'Total crop production (MAFWR)', 'طن');
        $add('mafwr.crop.total.area', 'إجمالي المساحة المحصولية (الوزارة)', 'Total cropped area (MAFWR)', 'فدان');
        foreach (self::GROUPS as [$slug, $ar]) {
            $add("mafwr.crop.{$slug}.prod", "إنتاج {$ar}", "Crop group production: {$slug}", 'طن');
            $add("mafwr.crop.{$slug}.area", "مساحة {$ar}", "Crop group area: {$slug}", 'فدان');
        }
        foreach (self::CROPS as [$slug, $ar]) {
            $add("mafwr.crop.{$slug}.prod", "إنتاج {$ar}", "Crop production: {$slug}", 'طن');
            $add("mafwr.crop.{$slug}.area", "مساحة {$ar}", "Crop area: {$slug}", 'فدان');
            $add("mafwr.crop.{$slug}.yield", "إنتاجية الفدان: {$ar}", "Crop yield: {$slug}", 'طن/فدان', 1000);
        }
        $add('mafwr.dates.palms', 'عدد النخيل المنتجة', 'Productive date palms', 'نخلة');
        $add('mafwr.dates.prod', 'إنتاج التمور (حسب الولايات)', 'Date production (by wilayat survey)', 'طن');
        $add('mafwr.dates.yield', 'متوسط إنتاج النخلة', 'Average yield per palm', 'كغم/نخلة', 1000);
        foreach (self::LIVESTOCK as [$slug, $ar]) {
            $add("mafwr.livestock.{$slug}", "أعداد {$ar}", "Livestock: {$slug}", 'رأس');
        }
        $add('mafwr.livestock.total', 'إجمالي أعداد الماشية', 'Total livestock', 'رأس');
        $add('mafwr.honey.hives', 'خلايا النحل', 'Beehives', 'خلية');
        $add('mafwr.honey.prod', 'إنتاج العسل', 'Honey production', 'طن', 1e6);
        $add('mafwr.milk.total', 'إنتاج الحليب', 'Milk production', 'طن');
        $add('mafwr.milk.cows', 'إنتاج حليب الأبقار', 'Cow milk production', 'طن');
        $add('mafwr.milk.camels', 'إنتاج حليب الإبل', 'Camel milk production', 'طن');
        $add('mafwr.meat.poultry', 'إنتاج لحوم الدواجن', 'Poultry meat production', 'طن');
        foreach (self::RED_MEAT as [$slug, $ar]) {
            $add("mafwr.meat.{$slug}", "إنتاج {$ar}", "Red meat production: {$slug}", 'طن');
        }
        $add('mafwr.meat.red', 'إنتاج اللحوم الحمراء', 'Red meat production', 'طن');
        $add('mafwr.eggs.count', 'إنتاج بيض المائدة (عدد)', 'Table eggs (count)', 'مليون بيضة');
        $add('mafwr.eggs.prod', 'إنتاج بيض المائدة', 'Table eggs production', 'طن');
        $add('mafwr.dams.count', 'عدد السدود المنفذة (تراكمي)', 'Dams built (cumulative)', 'سد', 1e5);
        $add('mafwr.dams.capacity', 'السعة التخزينية للسدود (تراكمي)', 'Dams storage capacity (cumulative)', 'مليون م³', 1e6);
        foreach (self::DAM_TYPES as [$slug, $ar]) {
            $add("mafwr.dams.{$slug}.capacity", "سعة {$ar} (تراكمي)", "Dam capacity: {$slug} (cumulative)", 'مليون م³', 1e6);
            $add("mafwr.dams.{$slug}.count", "عدد {$ar} (تراكمي)", "Dams: {$slug} (cumulative)", 'سد', 1e5);
        }
        $add('mafwr.aflaj.total', 'عدد الأفلاج', 'Aflaj (total)', 'فلج', 1e6);
        $add('mafwr.aflaj.alive', 'الأفلاج الحية', 'Aflaj (flowing)', 'فلج', 1e6);
        $add('mafwr.aflaj.dead', 'الأفلاج الميتة', 'Aflaj (dry)', 'فلج', 1e6);
        $add('mafwr.aflaj.daoudi', 'الأفلاج الداوودية', 'Daoudi aflaj', 'فلج', 1e6);
        $add('mafwr.aflaj.ghaili', 'الأفلاج الغيلية', 'Ghaili aflaj', 'فلج', 1e6);
        $add('mafwr.aflaj.aini', 'الأفلاج العينية', 'Aini aflaj', 'فلج', 1e6);
        $add('mafwr.seeding.stations', 'محطات الاستمطار الصناعي (تراكمي)', 'Cloud-seeding stations (cumulative)', 'محطة', 1e4);
        $add('mafwr.seeding.enhancement', 'نسبة تعزيز الهطول بالاستمطار', 'Rainfall enhancement from seeding', '%', 100);

        return new DatasetDefinition(
            key: 'mafwr.open_data',
            source: $this->source(),
            nameAr: 'البيانات المفتوحة للزراعة والثروة الحيوانية والمياه',
            nameEn: 'MAFWR open data: agriculture, livestock and water',
            section: 'society',
            intervalMinutes: 3 * 24 * 60,
            indicators: $ind,
        );
    }

    public function probe(): ProbeResult
    {
        $items = $this->datasets();
        $latest = collect($items)->max('updated');

        return new ProbeResult(token: $latest ? md5(json_encode(array_map(fn (array $i) => [$i['id'], $i['updated']], $items))) : null, version: 'MAFWR open data', publishedAt: $latest ? CarbonImmutable::parse($latest)->utc() : null);
    }

    public function fetch(): RawPayload
    {
        $items = $this->datasets();
        $token = $this->fileToken();
        $out = [];
        $pick = fn (string $type, string $needle) => array_values(array_filter($items, fn (array $i) => $i['type'] === $type && str_contains($this->squash($i['title_ar']), $this->squash($needle)) && str_ends_with((string) ($i['expand']['metadata']['file_name'] ?? ''), '.xlsx')));

        foreach ([
            'crops' => ['AgricultureWealth', 'الانتاج الزراعي'],
            'dates' => ['AgricultureWealth', 'التمور'],
            'livestock' => ['Livestock', 'الماشية'],
            'animal' => ['Livestock', 'الانتاج الحيواني'],
            'dams' => ['WaterResources', 'السدود'],
            'aflaj' => ['WaterResources', 'الافلاج'],
            'seeding' => ['WaterResources', 'الاستمطار'],
        ] as $kind => [$type, $needle]) {
            // الأحدث أولًا، وإن تعذّر تنزيله (ملفات كبيرة تنقطع أحيانًا) فالذي قبله
            foreach ($pick($type, $needle) as $item) {
                try {
                    $path = $this->download($item['expand']['metadata']['url'], $token);
                    try {
                        // لقطات بلا سنة (الأفلاج، نسبة التعزيز) تُنسب إلى سنة آخر تحديث للملف
                        $year = substr((string) $item['updated'], 0, 4);
                        $rows = array_map(fn (array $r) => $r[1] === '@snapshot' ? [$r[0], $year, $r[2]] : $r, $this->{$kind}(new XlsxReader($path)));
                        $out[$kind] = ['rows' => $rows, 'updated' => $item['updated'], 'title' => $item['title_ar']];
                    } finally {
                        @unlink($path);
                    }
                    break;
                } catch (Throwable $e) {
                    logger()->warning("[mafwr] {$item['title_ar']}: {$e->getMessage()}");
                }
            }
        }
        if (count($out) < 4) {
            throw new RuntimeException('MAFWR: تعذّر قراءة معظم ملفات البيانات المفتوحة ('.implode('، ', array_keys($out)).').');
        }

        return new RawPayload(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $part) {
            foreach ($part['rows'] as [$key, $period, $value]) {
                yield new ParsedRecord($key, 'OMN', (string) $period, (float) $value);
            }
        }
    }

    /* ——— الجداول ——— */

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function crops(XlsxReader $x): array
    {
        $sum = [];
        foreach ($this->table($x, 'المحصول') as $r) {
            $year = $this->year($r['السنة'] ?? null);
            $group = trim((string) ($r['المجموعةالمحصولية'] ?? ''));
            if ($year === null || $group === '' || $group === 'الإجمالي') {
                continue;
            }
            $prod = $this->num($r['الإنتاج(طن)'] ?? null);
            $area = $this->num($r['المساحة(فدان)'] ?? null);
            $keys = ['total'];
            if (isset(self::GROUPS[$group])) {
                $keys[] = self::GROUPS[$group][0];
            }
            $crop = $this->squash((string) ($r['المحصول'] ?? ''));
            foreach (self::CROPS as $prefix => [$slug]) {
                if (str_starts_with($crop, $prefix)) {
                    $keys[] = $slug;
                    break;
                }
            }
            foreach ($keys as $k) {
                $sum["mafwr.crop.{$k}.prod"][$year] = ($sum["mafwr.crop.{$k}.prod"][$year] ?? 0) + ($prod ?? 0);
                $sum["mafwr.crop.{$k}.area"][$year] = ($sum["mafwr.crop.{$k}.area"][$year] ?? 0) + ($area ?? 0);
            }
        }
        foreach (self::CROPS as [$slug]) {
            foreach ($sum["mafwr.crop.{$slug}.prod"] ?? [] as $year => $p) {
                $a = $sum["mafwr.crop.{$slug}.area"][$year] ?? 0;
                if ($a > 0) {
                    $sum["mafwr.crop.{$slug}.yield"][$year] = $p / $a;
                }
            }
        }

        return $this->flatten($sum);
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function dates(XlsxReader $x): array
    {
        $rows = array_values(array_filter($this->table($x, 'كميةانتاجالتمر(طن)'), fn (array $r) => $this->num($r['كميةانتاجالتمر(طن)'] ?? null) !== null));
        // الملف يضم صف «جميع المحافظات» مع صفوف الولايات في بعض السنوات: يُعتمد هو إن وُجد وإلا مجموع الولايات
        $prod = $this->nationalTotals($rows, [], fn (array $r) => $this->num($r['كميةانتاجالتمر(طن)']));
        $palms = $this->nationalTotals($rows, [], fn (array $r) => $this->num($r['عددالنخيلالمساهمةفيالإنتاج'] ?? null) ?? 0);
        $yield = [];
        foreach ($prod as $y => $p) {
            if (($palms[$y] ?? 0) > 0) {
                $yield[$y] = $p * 1000 / $palms[$y];
            }
        }

        return $this->flatten(['mafwr.dates.palms' => $palms, 'mafwr.dates.prod' => $prod, 'mafwr.dates.yield' => $yield]);
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function livestock(XlsxReader $x): array
    {
        $sum = [];
        foreach ($this->table($x, 'النوع') as $r) {
            $year = $this->year($r['السنة'] ?? null);
            $type = trim((string) ($r['النوع'] ?? ''));
            $n = $this->num($r['العدد'] ?? null);
            if ($year === null || $n === null || ! isset(self::LIVESTOCK[$type])) {
                continue;
            }
            $k = 'mafwr.livestock.'.self::LIVESTOCK[$type][0];
            $sum[$k][$year] = ($sum[$k][$year] ?? 0) + $n;
            $sum['mafwr.livestock.total'][$year] = ($sum['mafwr.livestock.total'][$year] ?? 0) + $n;
        }

        return $this->flatten($sum);
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function animal(XlsxReader $x): array
    {
        $sum = [];
        foreach ($x->sheetNames() as $sheet) {
            $name = $this->squash($sheet);
            if (str_contains($name, 'العسل')) {
                foreach ($this->table($x, 'عددالخلايا', $sheet) as $r) {
                    if (($y = $this->year($r['السنة'] ?? null)) !== null) {
                        $sum['mafwr.honey.hives'][$y] = ($sum['mafwr.honey.hives'][$y] ?? 0) + ($this->num($r['عددالخلايا'] ?? null) ?? 0);
                        $sum['mafwr.honey.prod'][$y] = ($sum['mafwr.honey.prod'][$y] ?? 0) + ($this->num($r['الإنتاج(كجم)'] ?? null) ?? 0) / 1000;
                    }
                }
            } elseif (str_contains($name, 'الحليب')) {
                // صفوف الحليب مجاميع متداخلة: (تقليدي/حديث، أبقار) = تقليدي + حديث، و(تقليدي/حديث، أبقار/إبل) = الأبقار + الإبل.
                // فيُؤخذ الإجمالي من صفه إن وُجد، وإلا أبقار (تقليدي/حديث أو تقليدي + حديث) + إبل
                $milk = [];
                foreach ($this->nationalCells($this->table($x, 'نوعالحليب', $sheet), ['نوعالإنتاج', 'نوعالحليب'], fn (array $r) => $this->firstNumber($r, 'الإنتاج') / 1000) as $y => $cells) {
                    $cows = $cells['تقليدي/حديث|ابقار'] ?? (($cells['تقليدي|ابقار'] ?? 0) + ($cells['حديث|ابقار'] ?? 0));
                    $camels = $cells['تقليدي|ابل'] ?? $cells['تقليدي/حديث|ابل'] ?? 0;
                    $milk['mafwr.milk.total'][$y] = $cells['تقليدي/حديث|ابقار/ابل'] ?? ($cows + $camels);
                    if ($cows > 0) {
                        $milk['mafwr.milk.cows'][$y] = $cows;
                    }
                    if ($camels > 0) {
                        $milk['mafwr.milk.camels'][$y] = $camels;
                    }
                }
                $sum = array_merge($sum, $milk);
            } elseif (str_contains($name, 'اللحومالبيضاء')) {
                foreach ($this->table($x, 'الإنتاج(طن)', $sheet) as $r) {
                    if (($y = $this->year($r['السنة'] ?? null)) !== null && ($v = $this->num($r['الإنتاج(طن)'] ?? null)) !== null) {
                        $sum['mafwr.meat.poultry'][$y] = ($sum['mafwr.meat.poultry'][$y] ?? 0) + $v;
                    }
                }
            } elseif (str_contains($name, 'اللحومالحمراء')) {
                $rows = $this->table($x, 'النوع', $sheet);
                foreach (self::RED_MEAT as $type => [$slug]) {
                    $sum["mafwr.meat.{$slug}"] = $this->nationalTotals(array_values(array_filter($rows, fn (array $r) => trim((string) ($r['النوع'] ?? '')) === $type)), [], fn (array $r) => $this->firstNumber($r, 'الإنتاج') / 1000);
                    foreach ($sum["mafwr.meat.{$slug}"] as $y => $v) {
                        $sum['mafwr.meat.red'][$y] = ($sum['mafwr.meat.red'][$y] ?? 0) + $v;
                    }
                }
            } elseif (str_contains($name, 'بيض')) {
                foreach ($this->table($x, 'الإنتاج(طن)', $sheet) as $r) {
                    if (($y = $this->year($r['السنة'] ?? null)) !== null) {
                        $sum['mafwr.eggs.prod'][$y] = ($sum['mafwr.eggs.prod'][$y] ?? 0) + ($this->num($r['الإنتاج(طن)'] ?? null) ?? 0);
                        $sum['mafwr.eggs.count'][$y] = ($sum['mafwr.eggs.count'][$y] ?? 0) + ($this->num($r['الإنتاج(عددبالمليون)'] ?? null) ?? 0);
                    }
                }
            }
        }

        return $this->flatten($sum);
    }

    /** @return list<array{0: string, 1: string, 2: float}> */
    private function dams(XlsxReader $x): array
    {
        $byYear = [];
        foreach ($this->table($x, 'إسمالسد') as $r) {
            $year = $this->year($r['سنةالتنفيذ'] ?? null);
            $cap = $this->num($r['السعةالتخزينية'] ?? null);
            if ($year === null || trim((string) ($r['إسمالسد'] ?? '')) === '') {
                continue;
            }
            $type = self::DAM_TYPES[trim((string) ($r['نوعالسد'] ?? ''))][0] ?? null;
            $byYear[$year][] = [$type, $cap ?? 0.0];
        }
        ksort($byYear);
        $sum = [];
        $count = 0;
        $capacity = 0.0;
        $typed = [];
        foreach ($byYear as $year => $dams) {
            foreach ($dams as [$type, $cap]) {
                $count++;
                $capacity += $cap;
                if ($type !== null) {
                    $typed[$type]['count'] = ($typed[$type]['count'] ?? 0) + 1;
                    $typed[$type]['capacity'] = ($typed[$type]['capacity'] ?? 0) + $cap;
                }
            }
            $sum['mafwr.dams.count'][$year] = $count;
            $sum['mafwr.dams.capacity'][$year] = $capacity;
            foreach ($typed as $type => $t) {
                $sum["mafwr.dams.{$type}.count"][$year] = $t['count'];
                $sum["mafwr.dams.{$type}.capacity"][$year] = $t['capacity'];
            }
        }

        return $this->flatten($sum);
    }

    /** الأفلاج لقطة بلا سنة: تُنسب إلى سنة آخر تحديث للملف (تُضاف السنة في fetch). */
    private function aflaj(XlsxReader $x): array
    {
        $t = ['total' => 0, 'alive' => 0, 'dead' => 0, 'daoudi' => 0, 'ghaili' => 0, 'aini' => 0];
        foreach ($this->table($x, 'الولاية') as $r) {
            if (trim((string) ($r['الولاية'] ?? '')) === '' || str_contains((string) ($r['المحافظة'] ?? ''), 'المجموع')) {
                continue;
            }
            $t['total'] += $this->num($r['المجموع'] ?? null) ?? 0;
            $t['alive'] += $this->num($r['حي'] ?? null) ?? 0;
            $t['dead'] += $this->num($r['ميت'] ?? null) ?? 0;
            $t['daoudi'] += $this->num($r['داؤودي'] ?? null) ?? 0;
            $t['ghaili'] += $this->num($r['غيلي'] ?? null) ?? 0;
            $t['aini'] += $this->num($r['عيني'] ?? null) ?? 0;
        }

        return array_map(fn (string $k) => ["mafwr.aflaj.{$k}", '@snapshot', (float) $t[$k]], array_keys($t));
    }

    private function seeding(XlsxReader $x): array
    {
        $out = [];
        $sheet = $x->sheetNames()[0];
        foreach ($x->sheet($sheet) as $cells) {
            foreach ($cells as $v) {
                if (is_string($v) && str_contains($v, 'نسبة التعزيز') && preg_match('/([\d.]+)\s*$/', $v, $m)) {
                    $out[] = ['mafwr.seeding.enhancement', '@snapshot', (float) $m[1]];
                }
            }
        }
        $years = [];
        foreach ($this->table($x, 'سنةالتشغيل') as $r) {
            if (($y = $this->year($r['سنةالتشغيل'] ?? null)) !== null) {
                $years[$y] = ($years[$y] ?? 0) + 1;
            }
        }
        ksort($years);
        $n = 0;
        foreach ($years as $y => $c) {
            $n += $c;
            $out[] = ['mafwr.seeding.stations', (string) $y, (float) $n];
        }

        return $out;
    }

    /* ——— أدوات ——— */

    /**
     * مجموع وطني لكل سنة: صفوف «جميع المحافظات» إن وُجدت لمجموعة (سنة + أعمدة التصنيف)، وإلا مجموع المحافظات — حتى لا تُعدّ مرتين.
     *
     * @param  list<array<string, string|null>>  $rows
     * @param  list<string>  $groupBy
     * @return array<string, float>
     */
    private function nationalTotals(array $rows, array $groupBy, callable $value): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $y = $this->year($r['السنة'] ?? null);
            if ($y === null) {
                continue;
            }
            $g = $y.'|'.implode('|', array_map(fn (string $c) => $this->squash((string) ($r[$c] ?? '')), $groupBy));
            $all = str_contains($this->squash((string) ($r['المحافظة'] ?? '')), 'جميعالمحافظات');
            $groups[$g][$all ? 'all' : 'gov'][] = (float) $value($r);
            $groups[$g]['year'] = $y;
        }
        $out = [];
        foreach ($groups as $g) {
            $out[$g['year']] = ($out[$g['year']] ?? 0) + array_sum($g['all'] ?? $g['gov'] ?? []);
        }

        return $out;
    }

    /**
     * مثل nationalTotals لكن يُبقي كل مجموعة تصنيف منفصلة: سنة => [«عمود1|عمود2» => قيمة وطنية].
     *
     * @param  list<array<string, string|null>>  $rows
     * @param  list<string>  $groupBy
     * @return array<string, array<string, float>>
     */
    private function nationalCells(array $rows, array $groupBy, callable $value): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $y = $this->year($r['السنة'] ?? null);
            if ($y === null) {
                continue;
            }
            $g = implode('|', array_map(fn (string $c) => $this->squash((string) ($r[$c] ?? '')), $groupBy));
            $all = str_contains($this->squash((string) ($r['المحافظة'] ?? '')), 'جميعالمحافظات');
            $groups[$y][$g][$all ? 'all' : 'gov'][] = (float) $value($r);
        }
        $out = [];
        foreach ($groups as $y => $gs) {
            foreach ($gs as $g => $v) {
                $out[$y][$g] = array_sum($v['all'] ?? $v['gov'] ?? []);
            }
        }

        return $out;
    }

    private function firstNumber(array $row, string $headerStartsWith): float
    {
        foreach ($row as $k => $v) {
            if (str_starts_with($k, $headerStartsWith) && is_numeric($v)) {
                return (float) $v;
            }
        }

        return 0.0;
    }

    /** @param  array<string, array<string, float>>  $sum @return list<array{0: string, 1: string, 2: float}> */
    private function flatten(array $sum): array
    {
        $out = [];
        foreach ($sum as $key => $years) {
            foreach ($years as $y => $v) {
                $out[] = [$key, (string) $y, round((float) $v, 4)];
            }
        }

        return $out;
    }
}
