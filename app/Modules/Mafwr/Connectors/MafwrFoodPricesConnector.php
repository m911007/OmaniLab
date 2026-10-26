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

/**
 * أسعار السلع الغذائية الأساسية (البيانات المفتوحة للوزارة: «جدول أسعار السلع الغذائية لشهر …»): سعر البيع للمستهلك بالريال لكل عبوة
 * من الأرز (أصناف باكستانية وهندية وتايلندية) والسكر والعدس. الملف جدول شهري يصدره الوزارة باسم الشهر؛ تؤخذ أحدث نسخة،
 * وتُنسب أسعارها إلى شهرها (من عنوان الملف). تُحسب كذلك كلفة الكيلو (الوسيط بين العبوات) لتتيح المقارنة بين الأصناف والأحجام.
 */
final class MafwrFoodPricesConnector extends MafwrConnector
{
    /** @var array<string, array{0: string, 1: string, 2: string, 3: list<int>}> بداية الاسم (بلا مسافات) => [مقطع المفتاح، عربي، إنجليزي، العبوات المنشورة بالكيلو] */
    public const ITEMS = [
        'أرزبسمتيباكستانيفاخر' => ['rice_pk_basmati_premium', 'أرز بسمتي باكستاني فاخر (حريري)', 'Pakistani premium basmati rice', [20, 35]],
        'أرزبسمتيباكستاني1121' => ['rice_pk_basmati_1121', 'أرز بسمتي باكستاني 1121', 'Pakistani basmati 1121 rice', [5, 20, 35]],
        'أرز(ب.ك386)' => ['rice_pk_386', 'أرز (ب.ك 386) باكستاني', 'Pakistani PK-386 rice', [20, 35]],
        'أرزإيري' => ['rice_pk_irri6', 'أرز إيري-6 باكستاني', 'Pakistani IRRI-6 rice', [20, 35]],
        'أرزتايلنديأبيض' => ['rice_thai_white', 'أرز تايلندي أبيض', 'Thai white rice', [5, 10, 20, 35]],
        'أرزتايلنديبابوريل' => ['rice_thai_paburil', 'أرز تايلندي بابوريل', 'Thai paburil rice', [5, 10, 20, 35]],
        'أرزبسمتيهندي1121مبخر' => ['rice_in_basmati_1121_steamed', 'أرز بسمتي هندي 1121 مبخر', 'Indian steamed basmati 1121 rice', [20, 35]],
        'أرزبسمتيهنديسيلا' => ['rice_in_basmati_sella_creamy', 'أرز بسمتي هندي سيلا 1121 كريمي', 'Indian sella creamy basmati 1121 rice', [20, 35]],
        'أرزبسمتيهنديب' => ['rice_in_basmati_b', 'أرز بسمتي هندي (ب)', 'Indian basmati B rice', [20, 25, 35]],
        'سكر' => ['sugar', 'السكر', 'Sugar', [50]],
        'عدسأجمر' => ['lentils_red', 'العدس الأحمر المفصول', 'Split red lentils', [10]],
    ];

    private const MONTHS = [
        'يناير' => 1, 'كانونالثاني' => 1, 'فبراير' => 2, 'شباط' => 2, 'مارس' => 3, 'آذار' => 3, 'أبريل' => 4, 'ابريل' => 4, 'نيسان' => 4, 'مايو' => 5, 'أيار' => 5,
        'يونيو' => 6, 'حزيران' => 6, 'يوليو' => 7, 'تموز' => 7, 'أغسطس' => 8, 'اغسطس' => 8, 'آب' => 8, 'سبتمبر' => 9, 'أيلول' => 9, 'أكتوبر' => 10, 'اكتوبر' => 10,
        'تشرينالأول' => 10, 'نوفمبر' => 11, 'تشرينالثاني' => 11, 'ديسمبر' => 12, 'كانونالأول' => 12,
    ];

    public function revision(): int
    {
        return 1;
    }

    public function definition(): DatasetDefinition
    {
        $ind = [];
        foreach (self::ITEMS as [$slug, $ar, $en, $packs]) {
            foreach ($packs as $kg) {
                $ind[] = new IndicatorDefinition("mafwr.food.{$slug}.p{$kg}", "{$ar}: عبوة {$kg} كغم", "{$en}: {$kg} kg pack", 'ريال', 'M', [new Range(0, 1000, 'reject')]);
            }
            $ind[] = new IndicatorDefinition("mafwr.food.{$slug}.perkg", "{$ar}: سعر الكيلو", "{$en}: price per kg", 'ريال للكيلو', 'M', [new Range(0, 100, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'mafwr.food_prices',
            source: $this->source(),
            nameAr: 'أسعار السلع الغذائية الأساسية (وزارة الثروة الزراعية والسمكية)',
            nameEn: 'Staple food prices (MAFWR)',
            section: 'society',
            intervalMinutes: 24 * 60,
            indicators: $ind,
        );
    }

    public function probe(): ProbeResult
    {
        $item = $this->newest();

        return new ProbeResult(token: $item ? md5($item['id'].'|'.$item['updated']) : null, version: $item['title_ar'] ?? null, publishedAt: $item ? CarbonImmutable::parse($item['updated'])->utc() : null);
    }

    public function fetch(): RawPayload
    {
        $out = $this->latestFile('FoodSecurity', 'أسعارالسلعالغذائية', function (string $path, array $item) {
            $period = self::periodFromTitle((string) $item['title_ar']);
            if ($period === null) {
                throw new RuntimeException("تعذّر استخراج الشهر من عنوان «{$item['title_ar']}».");
            }
            $x = new XlsxReader($path);
            $rows = self::prices($x->sheet($x->sheetNames()[0]));
            if ($rows === []) {
                throw new RuntimeException('جدول الأسعار فارغ.');
            }

            return ['period' => $period, 'title' => $item['title_ar'], 'rows' => $rows];
        });

        return new RawPayload(json_encode($out, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $perKg = [];
        foreach ($data['rows'] as [$slug, $kg, $price]) {
            yield new ParsedRecord("mafwr.food.{$slug}.p{$kg}", 'OMN', $data['period'], (float) $price);
            $perKg[$slug][] = $price / $kg;
        }
        foreach ($perKg as $slug => $values) {
            sort($values);
            $n = count($values);
            $median = $n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2;
            yield new ParsedRecord("mafwr.food.{$slug}.perkg", 'OMN', $data['period'], round($median, 4));
        }
    }

    /**
     * «جدول أسعار السلع الغذائية لشهر أكتوبر 2025» → «2025-10».
     */
    public static function periodFromTitle(string $title): ?string
    {
        $squashed = self::squashText($title);
        if (! preg_match('/(20\d{2})/', $squashed, $y)) {
            return null;
        }
        foreach (self::MONTHS as $name => $n) {
            if (str_contains($squashed, $name)) {
                return sprintf('%s-%02d', $y[1], $n);
            }
        }

        return null;
    }

    /**
     * صفوف الجدول: يبدأ الصنف بصف فيه رقم («1-») واسمه، وتليه صفوف العبوات الأخرى بلا اسم. أعمدة الجدول: A رقم، B الاسم، C العبوة (كغم)، D السعر.
     * تُتجاهل الأعمدة الأخرى (جدول تصنيف السرية الملصق بالورقة).
     *
     * @param  array<int, array<string, string|null>>  $sheet
     * @return list<array{0: string, 1: int, 2: float}> [مقطع الصنف، العبوة، السعر]
     */
    public static function prices(array $sheet): array
    {
        $out = [];
        $current = null;
        foreach ($sheet as $cells) {
            $name = trim((string) ($cells['B'] ?? ''));
            if ($name !== '' && ! str_starts_with(self::squashText($name), 'أنواع')) {
                $squashed = self::squashText($name);
                $current = null;
                foreach (self::ITEMS as $prefix => [$slug, , , $packs]) {
                    if (str_starts_with($squashed, $prefix)) {
                        $current = [$slug, $packs];
                        break;
                    }
                }
                if ($current === null) {
                    logger()->warning("[mafwr] صنف غذائي غير معروف: {$name}");
                }
            }
            $kg = $cells['C'] ?? null;
            $price = $cells['D'] ?? null;
            if ($current !== null && is_numeric($kg) && is_numeric($price) && in_array((int) $kg, $current[1], true) && (float) $price > 0) {
                $out[] = [$current[0], (int) $kg, (float) $price];
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function newest(): ?array
    {
        foreach ($this->datasets() as $i) {
            if ($i['type'] === 'FoodSecurity' && str_contains($this->squash($i['title_ar']), 'أسعارالسلعالغذائية')) {
                return $i;
            }
        }

        return null;
    }
}
