<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Mafwr\Connectors\MafwrFishPricesConnector;
use App\Modules\Mafwr\Connectors\MafwrFoodPricesConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class MafwrPricesConnectorsTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function xlsx(array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mafwr').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $xml = '';
        foreach (array_values($rows) as $r => $cells) {
            $xml .= '<row r="'.($r + 1).'">';
            foreach (array_values($cells) as $c => $v) {
                $ref = chr(65 + $c).($r + 1);
                $xml .= $v === null ? '' : (is_numeric($v) ? "<c r=\"{$ref}\"><v>{$v}</v></c>" : "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>{$v}</t></is></c>");
            }
            $xml .= '</row>';
        }
        $zip->addFromString('xl/worksheets/sheet1.xml', "<worksheet><sheetData>{$xml}</sheetData></worksheet>");
        $zip->addFromString('xl/workbook.xml', '<workbook><sheets><sheet name="S" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships><Relationship Id="rId1" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->close();
        $this->files[] = $path;

        return $path;
    }

    private function fakeMinistry(string $type, string $title, string $file): void
    {
        $item = ['id' => 'f1', 'type' => $type, 'title_ar' => $title, 'updated' => '2025-10-26 03:00:00.000Z', 'expand' => ['metadata' => ['file_name' => 'f.xlsx', 'url' => 'https://apis.mafwr.gov.om/meerastorage/files/f1.xlsx']]];
        Http::fake(function ($request) use ($item, $file) {
            $url = $request->url();
            if (str_contains($url, 'data_sets')) {
                return Http::response(['items' => [$item]]);
            }
            if (str_contains($url, 'PublicFileToken')) {
                return Http::response("\x00\x00\x00\x00\x05\x0a\x03tok", 200);
            }

            return str_contains($url, 'f1.xlsx') && str_contains($url, 'token=tok') ? Http::response(file_get_contents($file)) : Http::response('', 404);
        });
    }

    /** @return array<string, float> «مفتاح|كيان|فترة» => قيمة */
    private function records(iterable $items): array
    {
        $out = [];
        foreach ($items as $r) {
            if ($r instanceof ParsedRecord) {
                $out["{$r->indicatorKey}|{$r->entityCode}|{$r->period}"] = $r->value;
            }
        }

        return $out;
    }

    public function test_fish_prices_drop_input_errors_and_summarise_by_day_and_governorate(): void
    {
        $head = ['المحافظة', 'السوق', 'الولاية', 'نوع السمك', 'سعر الكيلو  بالريال العماني', 'وحدة الوزن', 'نوع البيع', 'تاريخ الادخال'];
        $d = 45852.4; // 2025-07-14
        $rows = [$head];
        foreach ([['مسقط', 'سوق أ', 3.0], ['مسقط', 'سوق ب', 3.4], ['ظفار', 'سوق ج', 3.2], ['ظفار', 'سوق د', 3.6]] as [$gov, $market, $p]) {
            $rows[] = ["محافظة {$gov}", $market, 'x', 'هامور', $p, 'بالكيلو', 'بالتجزئة', $d];
        }
        $rows[] = ['محافظة مسقط', 'سوق أ', 'x', 'هامور', 3.2, 'بالكيلو', 'بالتجزئة', $d];      // مكرر في السوق نفسه: وسيط السوق 3.1
        $rows[] = ['محافظة مسقط', 'سوق هـ', 'x', 'هامور', 3300, 'بالكيلو', 'بالتجزئة', $d];    // خطأ إدخال (بيسة)
        $rows[] = ['محافظة مسقط', 'سوق و', 'x', 'هامور', -3, 'بالكيلو', 'بالتجزئة', $d];       // سالب
        $rows[] = ['محافظة شمال الباطنة', 'سوق ز', 'x', 'كنعد', 4.5, 'بالكيلو', 'بالتجزئة', $d]; // سوق واحد فقط: لا وسيط وطني
        $rows[] = ['محافظة شمال الباطنة', 'سوق ز', 'x', 'كنعد', 4.5, 'بالكيلو', 'بالجملة', $d]; // كنعد بلا مؤشر جملة معرَّف: يُتجاهل
        $file = $this->xlsx($rows);
        $this->fakeMinistry('FishWealth', 'أسعار أسواق الاسماك للتجزئة 2025', $file);

        $c = new MafwrFishPricesConnector;
        $r = $this->records($c->parse($c->fetch()));

        // أسواق أ (3.1) وب (3.4) وج (3.2) ود (3.6): الوسيط الوطني (3.2+3.4)/2 = 3.3، ولا أثر للقيمتين الخاطئتين
        $this->assertEqualsWithDelta(3.3, $r['mafwr.fish.hamour.retail|OMN|2025-07-14'], 0.001);
        $this->assertEqualsWithDelta(3.25, $r['mafwr.fish.hamour.retail|OM-MA|2025-07-14'], 0.001);
        $this->assertEqualsWithDelta(3.4, $r['mafwr.fish.hamour.retail|OM-ZU|2025-07-14'], 0.001);
        // سوق واحد لا يكفي للوسيط الوطني، لكنه يظهر في محافظته
        $this->assertArrayNotHasKey('mafwr.fish.kanad.retail|OMN|2025-07-14', $r);
        $this->assertSame(4.5, $r['mafwr.fish.kanad.retail|OM-BS|2025-07-14']);
        $this->assertArrayNotHasKey('mafwr.fish.kanad.wholesale|OMN|2025-07-14', $r);
        // الأسواق المبلغة: 5 أسواق (أ ب ج د ز) — السوقان الخاطئان استُبعدا قبل العدّ
        $this->assertSame(5.0, $r['mafwr.fish.markets|OMN|2025-07-14']);
    }

    public function test_the_cleaner_is_relative_to_each_species(): void
    {
        $row = fn (string $sp, float $p) => ['2025-07-14', 'مسقط', 'سوق', $sp, $p, 'R'];
        [$kept, $dropped] = MafwrFishPricesConnector::clean([$row('عومة', 0.6), $row('عومة', 0.5), $row('عومة', 0.7), $row('عومة', 300.0), $row('هامور', 9.0), $row('هامور', 3.0), $row('هامور', 3.5), $row('هامور', 3.2), $row('نوع مجهول', 5.0)]);

        $this->assertCount(7, $kept); // 9.0 للهامور ضمن 5 أضعاف الوسيط (3.35 → 16.7)، ويُهمل النوع المجهول
        $this->assertSame([300.0], array_map(fn (array $r) => $r[4], $dropped));
    }

    public function test_food_prices_read_the_item_table_and_ignore_the_classification_legend(): void
    {
        $file = $this->xlsx([
            ['م', 'أنواع السلع الغذائية', 'وزن العبوة (كجم)', 'سعر البيع للمستهلك (ر.ع)', 'السرية', 'المصداقية', 'التواجد', null, 'Arabic'],
            [null, null, null, null, 'مفتوح', 1, 'نعم', null, 'سري للغاية'],
            ['1-', 'أرز بسمتي باكستاني فاخر (حريري)', 20, 8.8, null, null, null, null, 'سري'],
            [null, null, 35, 16.6, null, null, null, null, 'محدود'],
            ['2-', 'أرزبسمتي باكستاني 1121', 5, 2.5],
            [null, null, 20, 10.8],
            ['10-', 'سكر', 50, 14.15],
            ['11-', 'صنف جديد غير معروف', 10, 99],
            [null, null, 50, 77],
        ]);
        $this->fakeMinistry('FoodSecurity', 'جدول أسعار السلع الغذائية لشهر أكتوبر 2025', $file);

        $c = new MafwrFoodPricesConnector;
        $r = $this->records($c->parse($c->fetch()));

        $this->assertSame(8.8, $r['mafwr.food.rice_pk_basmati_premium.p20|OMN|2025-10']);
        $this->assertSame(16.6, $r['mafwr.food.rice_pk_basmati_premium.p35|OMN|2025-10']);
        // كلفة الكيلو = وسيط كلفة الكيلو بين العبوات: (0.44 و0.474) → 0.457
        $this->assertEqualsWithDelta(0.457, $r['mafwr.food.rice_pk_basmati_premium.perkg|OMN|2025-10'], 0.001);
        $this->assertEqualsWithDelta(0.283, $r['mafwr.food.sugar.perkg|OMN|2025-10'], 0.001);
        // الصنف المجهول وعبواته لا تدخل
        $this->assertCount(8, array_filter(array_keys($r), fn ($k) => str_starts_with($k, 'mafwr.food.')));
    }

    public function test_the_month_comes_from_the_file_title(): void
    {
        $this->assertSame('2025-10', MafwrFoodPricesConnector::periodFromTitle('جدول أسعار السلع الغذائية لشهر أكتوبر 2025'));
        $this->assertSame('2026-01', MafwrFoodPricesConnector::periodFromTitle('أسعار السلع الغذائية يناير 2026'));
        $this->assertNull(MafwrFoodPricesConnector::periodFromTitle('أسعار السلع الغذائية'));
    }

    public function test_decomposed_arabic_letters_in_titles_still_match(): void
    {
        // «أسعار السلع الغذائية» بصيغة ألف + علامة همزة منفصلة، كما تصل أحيانًا من الموقع
        $decomposed = "جدول \u{0627}\u{0654}سعار السلع الغذا\u{064A}\u{0654}ية لشهر \u{0627}\u{0654}كتوبر 2025";

        $this->assertSame('2025-10', MafwrFoodPricesConnector::periodFromTitle($decomposed));
        $this->assertSame(MafwrFoodPricesConnector::squashText('أسعار السلع الغذائية'), MafwrFoodPricesConnector::squashText("\u{0627}\u{0654}سعار السلع الغذا\u{064A}\u{0654}ية"));
    }
}
