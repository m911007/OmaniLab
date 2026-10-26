<?php

namespace App\Modules\Mafwr\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Quality\Exclusions;
use App\Core\Support\XlsxReader;
use App\Core\Validation\Rules\Range;
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * أسعار الأسماك في أسواق السلطنة (البيانات المفتوحة للوزارة: «أسعار أسواق الأسماك للتجزئة»): أسعار يومية يدخلها مراقبو الأسواق
 * (ريال للكيلو، بالتجزئة وقليل منها بالجملة) في 35 سوقًا و10 محافظات.
 *
 * التلخيص: وسيط يومي لكل نوع على مستوى السلطنة (متى أبلغ 3 أسواق فأكثر) ولكل محافظة، ومؤشر سلة يقيس الغلاء العام للأسماك.
 * التنظيف: قيم الإدخال الخاطئة (سالبة أو صفرية، أو بالبيسة بدل الريال مثل 300 لسمك سعره 0.6) تُستبعد، ولا تُصحَّح بالتخمين؛
 * وكل ما استُبعد يُسجَّل في سجل التصحيحات بقيمته الأصلية وسببه.
 */
final class MafwrFishPricesConnector extends MafwrConnector
{
    /** أقصى سعر معقول للكيلو، وحدّا الانحراف عن وسيط النوع (انحراف أكبر يعني خطأ إدخال لا تغيّرًا حقيقيًا) */
    private const MAX_PRICE = 50.0;

    private const LOW = 0.2;

    private const HIGH = 5.0;

    /** أقل عدد أسواق لحساب الوسيط الوطني اليومي */
    private const MIN_MARKETS = 3;

    /** @var array<string, array{0: string, 1: string}> الاسم في الملف => [مقطع المفتاح، الاسم الإنجليزي] */
    public const SPECIES = [
        'سهوة' => ['sahwa', 'Sahwa'], 'شعري' => ['shaari', 'Shaari (emperor)'], 'جيذر' => ['jidhar', 'Jidhar'], 'صال' => ['saal', 'Saal'],
        'قشران' => ['qishran', 'Qishran'], 'ضلعة' => ['dhilaa', 'Dhilaa'], 'عنفلوص' => ['anfaloos', 'Anfaloos'], 'كوفر' => ['kofar', 'Kofar (trevally)'],
        'صيمة' => ['saima', 'Saima'], 'جرجور' => ['jarjoor', 'Jarjoor'], 'هامور' => ['hamour', 'Hamour (grouper)'], 'قد' => ['qad', 'Qad'],
        'عقام' => ['uqam', 'Uqam'], 'حبار' => ['hubar', 'Squid'], 'أشخلي' => ['ashkhali', 'Ashkhali'], 'سلطان براهيم' => ['sultan_ibrahim', 'Red mullet'],
        'صدة' => ['sadda', 'Sadda'], 'سكن' => ['sakan', 'Sakan'], 'عومة' => ['ooma', 'Oil sardine'], 'حبسة' => ['habsa', 'Habsa'],
        'نجرور' => ['najroor', 'Najroor'], 'عندق' => ['andaq', 'Andaq'], 'طباق' => ['tabaq', 'Tabaq'], 'سنسول' => ['sansool', 'Sansool'],
        'حمام' => ['hamam', 'Hamam'], 'غزال' => ['ghazal', 'Ghazal'], 'كنعد' => ['kanad', 'Kanad (kingfish)'], 'دبس' => ['dibs', 'Dibs'],
        'كعك' => ['kaak', 'Kaak'], 'بياح' => ['biyah', 'Biyah (mullet)'], 'كبش البحر' => ['kabsh_albahr', 'Kabsh al-bahr'], 'روبيان' => ['shrimp', 'Shrimp'],
        'صافي' => ['safi', 'Safi (rabbitfish)'], 'طلاح' => ['talah', 'Talah'], 'كفدار' => ['kafdar', 'Kafdar'],
    ];

    /** الأنواع التي لها أسعار جملة كافية (8 أسعار فأكثر) */
    private const WHOLESALE = ['sahwa', 'shaari', 'jidhar', 'saal', 'qishran', 'dhilaa', 'anfaloos', 'kofar', 'saima', 'jarjoor', 'uqam', 'ashkhali', 'sadda', 'ooma', 'andaq', 'sansool'];

    /** @var array<string, array{0: string, 1: string, 2: string}> المحافظة في الملف (بعد حذف «محافظة») => [رمز ISO 3166-2، عربي، إنجليزي] */
    public const GOVERNORATES = [
        'مسقط' => ['OM-MA', 'محافظة مسقط', 'Muscat Governorate'], 'ظفار' => ['OM-ZU', 'محافظة ظفار', 'Dhofar Governorate'], 'مسندم' => ['OM-MU', 'محافظة مسندم', 'Musandam Governorate'],
        'البريمي' => ['OM-BU', 'محافظة البريمي', 'Al Buraimi Governorate'], 'الداخلية' => ['OM-DA', 'محافظة الداخلية', 'Ad Dakhiliyah Governorate'], 'شمال الباطنة' => ['OM-BS', 'محافظة شمال الباطنة', 'North Al Batinah Governorate'],
        'جنوب الباطنة' => ['OM-BJ', 'محافظة جنوب الباطنة', 'South Al Batinah Governorate'], 'جنوب الشرقية' => ['OM-SJ', 'محافظة جنوب الشرقية', 'South Ash Sharqiyah Governorate'], 'شمال الشرقية' => ['OM-SS', 'محافظة شمال الشرقية', 'North Ash Sharqiyah Governorate'],
        'الظاهرة' => ['OM-ZA', 'محافظة الظاهرة', 'Ad Dhahirah Governorate'], 'الوسطى' => ['OM-WU', 'محافظة الوسطى', 'Al Wusta Governorate'],
    ];

    public function revision(): int
    {
        return 1;
    }

    public function definition(): DatasetDefinition
    {
        $ind = [];
        $rule = fn () => [new Range(0, 100, 'reject')];
        foreach (self::SPECIES as $ar => [$slug, $en]) {
            $ind[] = new IndicatorDefinition("mafwr.fish.{$slug}.retail", "سعر {$ar} بالتجزئة", "{$en}: retail price", 'ريال للكيلو', 'D', $rule());
            if (in_array($slug, self::WHOLESALE, true)) {
                $ind[] = new IndicatorDefinition("mafwr.fish.{$slug}.wholesale", "سعر {$ar} بالجملة", "{$en}: wholesale price", 'ريال للكيلو', 'D', $rule());
            }
        }
        $ind[] = new IndicatorDefinition('mafwr.fish.index', 'مؤشر أسعار الأسماك بالتجزئة (وسيط الفترة = 100)', 'Retail fish price index (period median = 100)', 'مؤشر', 'D', [new Range(0, 1000, 'reject')]);
        $ind[] = new IndicatorDefinition('mafwr.fish.markets', 'عدد أسواق الأسماك المُبلِغة يوميًا', 'Fish markets reporting per day', 'سوق', 'D', [new Range(0, 1000, 'reject')]);

        return new DatasetDefinition(
            key: 'mafwr.fish_prices',
            source: $this->source(),
            nameAr: 'أسعار الأسماك في الأسواق (وزارة الثروة الزراعية والسمكية)',
            nameEn: 'Fish market prices (MAFWR)',
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
        $rows = $this->latestFile('FishWealth', 'أسعارأسواقالاسماك', function (string $path, array $item) {
            $x = new XlsxReader($path);
            $out = [];
            foreach ($this->table($x, 'نوعالسمك') as $r) {
                $date = $this->excelDate($r['تاريخالادخال'] ?? null);
                $price = $this->num($r['سعرالكيلوبالريالالعماني'] ?? null);
                $species = trim(self::compose((string) ($r['نوعالسمك'] ?? '')));
                if ($date === null || $price === null || $species === '' || ! str_contains($this->squash((string) ($r['وحدةالوزن'] ?? 'بالكيلو')), 'كيلو')) {
                    continue;
                }
                $out[] = [
                    $date,
                    $this->governorateKey((string) ($r['المحافظة'] ?? '')),
                    $this->squash((string) ($r['السوق'] ?? '')),
                    $species,
                    $price,
                    str_contains($this->squash((string) ($r['نوعالبيع'] ?? '')), 'جملة') ? 'W' : 'R',
                ];
            }

            return $out;
        });
        if (count($rows) < 5) {
            throw new RuntimeException('MAFWR أسعار الأسماك: عدد الصفوف غير معقول ('.count($rows).').');
        }

        return new RawPayload(json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');
        foreach (self::GOVERNORATES as [$code, $ar, $en]) {
            yield new ParsedEntity($code, $en, 'region', $ar);
        }
        $rows = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        [$kept, $dropped] = self::clean($rows);
        $this->recordDropped($dropped);

        foreach (self::summarise($kept) as [$key, $entity, $day, $value]) {
            yield new ParsedRecord($key, $entity, $day, $value);
        }
    }

    /**
     * يستبعد قيم الإدخال الخاطئة: غير موجبة، أو فوق السقف، أو منحرفة عن وسيط نوعها بأكثر من 5 أضعاف أو أقل من خُمسه.
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: float, 5: string}>  $rows
     * @return array{0: list<array>, 1: list<array>} [المقبول، المستبعد]
     */
    public static function clean(array $rows): array
    {
        $valid = [];
        foreach ($rows as $r) {
            if ($r[4] > 0 && $r[4] <= self::MAX_PRICE && isset(self::SPECIES[$r[3]])) {
                $valid[$r[3].'|'.$r[5]][] = $r[4];
            }
        }
        $median = fn (array $v): float => self::median($v);
        $medians = array_map($median, $valid);

        $kept = [];
        $dropped = [];
        foreach ($rows as $r) {
            if (! isset(self::SPECIES[$r[3]])) {
                continue;
            }
            $m = $medians[$r[3].'|'.$r[5]] ?? null;
            $ok = $r[4] > 0 && $r[4] <= self::MAX_PRICE && $m !== null && $r[4] >= self::LOW * $m && $r[4] <= self::HIGH * $m;
            $ok ? $kept[] = $r : $dropped[] = $r;
        }

        return [$kept, $dropped];
    }

    /**
     * سلاسل يومية: وسيط السوق-اليوم أولًا (لا يُعدّ السوق مرتين)، ثم وسيط الأسواق على مستوى السلطنة (≥ 3 أسواق) ولكل محافظة،
     * ومؤشر السلة = متوسط نسبة سعر كل نوع إلى وسيطه في الفترة (يُحسب حين يتوفر 8 أنواع فأكثر).
     *
     * @param  list<array>  $rows
     * @return list<array{0: string, 1: string, 2: string, 3: float}>
     */
    public static function summarise(array $rows): array
    {
        // [مفتاح المؤشر][يوم][سوق] => قيم، و[مفتاح][يوم][محافظة][سوق] => قيم
        $byMarket = [];
        foreach ($rows as [$day, $gov, $market, $species, $price, $sale]) {
            $slug = self::SPECIES[$species][0];
            if ($sale === 'W' && ! in_array($slug, self::WHOLESALE, true)) {
                continue;
            }
            $key = "mafwr.fish.{$slug}.".($sale === 'W' ? 'wholesale' : 'retail');
            $byMarket[$key][$day][$gov][$market][] = $price;
        }

        $out = [];
        $retailNational = [];
        foreach ($byMarket as $key => $days) {
            $isRetail = str_ends_with($key, '.retail');
            foreach ($days as $day => $govs) {
                $all = [];
                foreach ($govs as $gov => $markets) {
                    $vals = array_map(fn (array $v) => self::median($v), $markets);
                    $all = array_merge($all, $vals);
                    if ($isRetail && isset(self::GOVERNORATES[$gov])) {
                        $out[] = [$key, self::GOVERNORATES[$gov][0], $day, round(self::median($vals), 3)];
                    }
                }
                if (count($all) >= ($isRetail ? self::MIN_MARKETS : 1)) {
                    $out[] = [$key, 'OMN', $day, round(self::median($all), 3)];
                    if ($isRetail) {
                        $retailNational[$key][$day] = self::median($all);
                    }
                }
            }
        }

        // مؤشر السلة ومؤشر الأسواق المبلِّغة
        $base = array_map(fn (array $d) => self::median(array_values($d)), $retailNational);
        $index = [];
        foreach ($retailNational as $key => $days) {
            foreach ($days as $day => $v) {
                $index[$day][] = $v / $base[$key] * 100;
            }
        }
        foreach ($index as $day => $ratios) {
            if (count($ratios) >= 8) {
                $out[] = ['mafwr.fish.index', 'OMN', $day, round(array_sum($ratios) / count($ratios), 2)];
            }
        }
        $markets = [];
        foreach ($rows as [$day, , $market, , , $sale]) {
            if ($sale === 'R') {
                $markets[$day][$market] = true;
            }
        }
        foreach ($markets as $day => $m) {
            $out[] = ['mafwr.fish.markets', 'OMN', $day, (float) count($m)];
        }

        return $out;
    }

    /** @param  list<float>  $values */
    private static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $m = intdiv($n, 2);

        return $n % 2 ? $values[$m] : ($values[$m - 1] + $values[$m]) / 2;
    }

    /** تسجّل المستبعد في سجل التصحيحات: مجموعة (نوع، يوم) بقيمتها الأعلى انحرافًا وعدد القيم المستبعدة. */
    private function recordDropped(array $dropped): void
    {
        $groups = [];
        foreach ($dropped as [$day, , , $species, $price, $sale]) {
            $slug = self::SPECIES[$species][0];
            $key = "mafwr.fish.{$slug}.".($sale === 'W' ? 'wholesale' : 'retail');
            $groups[$key."\x1f".$day][] = $price;
        }
        $exclusions = new Exclusions;
        foreach ($groups as $k => $prices) {
            [$key, $day] = explode("\x1f", $k);
            $exclusions->record($key, 'OMN', $day, max($prices), 'قيمة إدخال خاطئة في ملف الوزارة (غير موجبة أو بعيدة جدًا عن أسعار النوع، كإدخال البيسة بدل الريال): استُبعدت ولم تُصحَّح بالتخمين. عدد القيم المستبعدة في اليوم: '.count($prices).'.');
        }
        if ($dropped !== []) {
            logger()->info('[mafwr] أسعار الأسماك: استُبعدت '.count($dropped).' قيمة إدخال خاطئة.');
        }
    }

    /** @return array<string, mixed>|null */
    private function newest(): ?array
    {
        foreach ($this->datasets() as $i) {
            if ($i['type'] === 'FishWealth' && str_contains($this->squash($i['title_ar']), 'أسعارأسواقالاسماك')) {
                return $i;
            }
        }

        return null;
    }

    /** اسم المحافظة في الملف («محافظة شمال الباطنة») إلى مفتاحها في GOVERNORATES («شمال الباطنة»)، أو النص كما هو إن لم تُعرف. */
    private function governorateKey(string $name): string
    {
        $plain = str_replace('محافظة', '', $this->squash($name));
        foreach (array_keys(self::GOVERNORATES) as $key) {
            if ($this->squash($key) === $plain) {
                return $key;
            }
        }

        return $plain;
    }

    /** تاريخ Excel (رقم تسلسلي) أو نص ISO إلى «YYYY-MM-DD». */
    private function excelDate(mixed $v): ?string
    {
        if (is_numeric($v) && (float) $v > 30000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) floor((float) $v))->format('Y-m-d');
        }
        if (is_string($v) && preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return null;
    }
}
