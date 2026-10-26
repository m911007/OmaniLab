<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Mafwr\Connectors\MafwrOpenDataConnector;
use App\Modules\Sdg\Connectors\SdgWaterEfficiencyConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use ZipArchive;

class MafwrOpenDataConnectorTest extends TestCase
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

    /** ملف xlsx بسيط بنصوص مضمّنة: [اسم الورقة => صفوف] */
    private function xlsx(array $sheets): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mafwr').'.xlsx';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $wb = '';
        $rels = '';
        $i = 0;
        foreach ($sheets as $name => $rows) {
            $i++;
            $wb .= "<sheet name=\"{$name}\" sheetId=\"{$i}\" r:id=\"rId{$i}\"/>";
            $rels .= "<Relationship Id=\"rId{$i}\" Type=\"x\" Target=\"worksheets/sheet{$i}.xml\"/>";
            $xml = '';
            foreach (array_values($rows) as $r => $cells) {
                $xml .= '<row r="'.($r + 1).'">';
                foreach (array_values($cells) as $c => $v) {
                    $ref = chr(65 + $c).($r + 1);
                    $xml .= $v === null ? '' : (is_numeric($v) ? "<c r=\"{$ref}\"><v>{$v}</v></c>" : "<c r=\"{$ref}\" t=\"inlineStr\"><is><t>{$v}</t></is></c>");
                }
                $xml .= '</row>';
            }
            $zip->addFromString("xl/worksheets/sheet{$i}.xml", "<worksheet><sheetData>{$xml}</sheetData></worksheet>");
        }
        $zip->addFromString('xl/workbook.xml', "<workbook><sheets>{$wb}</sheets></workbook>");
        $zip->addFromString('xl/_rels/workbook.xml.rels', "<Relationships>{$rels}</Relationships>");
        $zip->close();
        $this->files[] = $path;

        return $path;
    }

    private function item(string $id, string $type, string $title): array
    {
        return ['id' => $id, 'type' => $type, 'title_ar' => $title, 'updated' => '2025-10-26 03:42:40.113Z', 'expand' => ['metadata' => ['file_name' => "{$id}.xlsx", 'url' => "https://apis.mafwr.gov.om/meerastorage/files/{$id}.xlsx"]]];
    }

    public function test_it_downloads_the_open_data_files_and_avoids_double_counting(): void
    {
        $files = [
            'crops' => $this->xlsx(['البيانات الزراعية' => [
                ['السنة', 'المحافظة', 'المجموعة المحصولية', 'المحصول', 'المساحة(فدان)', 'الإنتاج (طن)'],
                [2024, 'مسقط', 'الخضروات', 'طماطم', 10, 300],
                [2024, 'ظفار', 'محاصيل علفية', 'حشيشة الرودس', 20, 1000],
                [2024, 'الإجمالي', 'الإجمالي', 'الإجمالي', 30, 1300],
            ]]),
            'dates' => $this->xlsx(['بيانات التمور' => [
                ['السنة', 'المحافظة', 'الولاية', 'عدد النخيل المساهمة في الإنتاج', 'متوسط انتاج النخلة (كجم)', 'كمية انتاج التمر (طن)'],
                [2024, 'الداخلية', 'نزوى', 1000, 50, 50],
                [2024, 'الظاهرة', 'عبري', 1000, 50, 50],
                [2024, 'جميع المحافظات', 'جميع المحافظات', 2000, 50, 100],
            ]]),
            'animal' => $this->xlsx(['الحليب' => [
                ['السنة', 'المحافظة', 'نوع الإنتاج', 'نوع الحليب', 'الإنتاج المحلي من حليب الابقار (كجم)'],
                [2024, 'جميع المحافظات', 'تقليدي', 'ابقار', 100000],
                [2024, 'جميع المحافظات', ' حديث ', 'ابقار', 80000],
                [2024, 'جميع المحافظات', 'تقليدي  / حديث ', 'ابقار', 180000],
                [2024, 'جميع المحافظات', 'تقليدي', 'ابل', 40000],
                [2024, 'جميع المحافظات', 'تقليدي  / حديث ', 'ابقار/ ابل', 220000],
            ]]),
            'dams' => $this->xlsx(['ورقة1' => [
                [null, null, 'عدد السدود المنفذة'],
                [null, 'م', 'إسم السد', 'المحافظة', 'الولاية', 'نوع السد', 'السعة التخزينية', 'الطول', 'أقصى إرتفاع', 'سنة التنفيذ'],
                [null, 1, 'الخوض', 'مسقط', 'السيب', 'تغذية', 11.6, 5100, 11, 1985],
                [null, 2, 'وادي ضيقة', 'مسقط', 'قريات', 'تخزيني', 100, 300, 75, 2012],
            ]]),
            'aflaj' => $this->xlsx(['ورقة1' => [
                ['المحافظة', 'الولاية', 'داؤودي', 'غيلي', 'عيني', 'حي', 'ميت', 'المجموع'],
                ['الداخلية', 'نزوى', 33, 12, 24, 45, 24, 69],
                ['المجموع', null, 33, 12, 24, 45, 24, 69],
            ]]),
        ];
        $list = ['items' => [
            $this->item('crops', 'AgricultureWealth', 'بيانات الانتاج الزراعي'),
            $this->item('dates', 'AgricultureWealth', 'بيانات انتاج التمور'),
            $this->item('animal', 'Livestock', 'بيانات الانتاج الحيواني'),
            $this->item('dams', 'WaterResources', 'بيانات السدود'),
            $this->item('aflaj', 'WaterResources', 'إحصائيات الافلاج'),
        ]];
        Http::fake(function ($request) use ($list, $files) {
            $url = $request->url();
            if (str_contains($url, 'data_sets')) {
                return Http::response($list);
            }
            if (str_contains($url, 'PublicFileToken')) {
                // إطار gRPC-web: رسالة فيها الحقل 1 = «tok»
                return Http::response("\x00\x00\x00\x00\x05\x0a\x03tok", 200, ['Content-Type' => 'application/grpc-web+proto']);
            }
            foreach ($files as $id => $path) {
                if (str_contains($url, "/{$id}.xlsx") && str_contains($url, 'token=tok')) {
                    return Http::response(file_get_contents($path));
                }
            }

            return Http::response('missing', 404);
        });

        $c = new MafwrOpenDataConnector;
        $records = [];
        foreach ($c->parse($c->fetch()) as $r) {
            if ($r instanceof ParsedRecord) {
                $records["{$r->indicatorKey}|{$r->period}"] = $r->value;
            }
        }

        // صف «الإجمالي» لا يُضاف إلى المجموع، والمحصول يُصنَّف بمجموعته واسمه
        $this->assertSame(1300.0, $records['mafwr.crop.total.prod|2024']);
        $this->assertSame(1000.0, $records['mafwr.crop.rhodes.prod|2024']);
        $this->assertSame(50.0, $records['mafwr.crop.rhodes.yield|2024']);
        // التمور: صف «جميع المحافظات» يُعتمد بدل جمعه مع الولايات
        $this->assertSame(100.0, $records['mafwr.dates.prod|2024']);
        $this->assertSame(50.0, $records['mafwr.dates.yield|2024']);
        // الحليب: الإجمالي من صفه لا من جمع المجاميع المتداخلة (طن)
        $this->assertSame(220.0, $records['mafwr.milk.total|2024']);
        $this->assertSame(180.0, $records['mafwr.milk.cows|2024']);
        $this->assertSame(40.0, $records['mafwr.milk.camels|2024']);
        // السدود تراكمية بسنة التنفيذ
        $this->assertSame(1.0, $records['mafwr.dams.count|1985']);
        $this->assertSame(111.6, $records['mafwr.dams.capacity|2012']);
        $this->assertSame(100.0, $records['mafwr.dams.storage.capacity|2012']);
        // الأفلاج لقطة تُنسب إلى سنة آخر تحديث، وصف «المجموع» لا يُعدّ
        $this->assertSame(69.0, $records['mafwr.aflaj.total|2025']);
        $this->assertSame(24.0, $records['mafwr.aflaj.dead|2025']);
    }

    public function test_sdg_water_efficiency_maps_activities_and_m49_codes(): void
    {
        Http::fake([
            'comtradeapi.un.org/*' => Http::response(['results' => [['reporterCode' => 512, 'reporterCodeIsoAlpha3' => 'OMN', 'isGroup' => false]]]),
            'unstats.un.org/*' => Http::response(['totalPages' => 1, 'data' => [
                ['geoAreaCode' => '512', 'timePeriodStart' => 2021.0, 'value' => '0.62', 'dimensions' => ['Reporting Type' => 'G', 'Activity' => 'ISIC4_A01_A0210_A0322']],
                ['geoAreaCode' => '512', 'timePeriodStart' => 2021.0, 'value' => '845.1', 'dimensions' => ['Reporting Type' => 'G', 'Activity' => 'INDUSTRIES']],
                ['geoAreaCode' => '512', 'timePeriodStart' => 2021.0, 'value' => '1', 'dimensions' => ['Reporting Type' => 'G', 'Activity' => 'UNKNOWN']],
                ['geoAreaCode' => '999', 'timePeriodStart' => 2021.0, 'value' => '1', 'dimensions' => ['Reporting Type' => 'G', 'Activity' => 'TOTAL']],
            ]]),
        ]);
        $c = new SdgWaterEfficiencyConnector;
        $records = array_map(fn ($r) => "{$r->indicatorKey}|{$r->entityCode}|{$r->period}|{$r->value}", iterator_to_array($c->parse($c->fetch()), false));

        $this->assertSame(['sdg.water_eff.agriculture|OMN|2021|0.62', 'sdg.water_eff.industry|OMN|2021|845.1'], $records);
    }
}
