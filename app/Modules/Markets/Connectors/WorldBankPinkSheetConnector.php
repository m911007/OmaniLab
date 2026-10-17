<?php

namespace App\Modules\Markets\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use Carbon\CarbonImmutable;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * الورقة الوردية للبنك الدولي (Commodity Price Data): أسعار السلع الشهرية بالدولار منذ 1960. ملف Excel يتغير رابطه كل إصدار شهري
 * (مسار فيه بصمة)، فيُكتشف الرابط الحالي من صفحة أسواق السلع نفسها. تُقرأ ورقة «Monthly Prices» مباشرة من XML الملف.
 * مصدر مستقل عن صندوق النقد لسعر النفط والغاز، فيتيح مطابقة الأسعار.
 */
final class WorldBankPinkSheetConnector extends ApiConnector
{
    private const PAGE = 'https://www.worldbank.org/en/research/commodity-markets';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: int|float}> عنوان العمود في الملف => [مفتاح، عربي، إنجليزي، وحدة، حد أعلى] */
    private const COLUMNS = [
        'Crude oil, average' => ['wbpink.crude_avg', 'النفط الخام (متوسط برنت ودبي وغرب تكساس)', 'Crude oil, average', 'دولار/برميل', 500],
        'Crude oil, Brent' => ['wbpink.crude_brent', 'خام برنت', 'Crude oil, Brent', 'دولار/برميل', 500],
        'Crude oil, Dubai' => ['wbpink.crude_dubai', 'خام دبي', 'Crude oil, Dubai', 'دولار/برميل', 500],
        'Crude oil, WTI' => ['wbpink.crude_wti', 'خام غرب تكساس', 'Crude oil, WTI', 'دولار/برميل', 500],
        'Natural gas, US' => ['wbpink.ng_us', 'الغاز الطبيعي (أمريكا)', 'Natural gas, US', 'دولار/مليون وحدة حرارية', 100],
        'Natural gas, Europe' => ['wbpink.ng_europe', 'الغاز الطبيعي (أوروبا)', 'Natural gas, Europe', 'دولار/مليون وحدة حرارية', 300],
        'Liquefied natural gas, Japan' => ['wbpink.lng_japan', 'الغاز المسال (اليابان)', 'Liquefied natural gas, Japan', 'دولار/مليون وحدة حرارية', 300],
        'Coal, Australian' => ['wbpink.coal_australia', 'الفحم (أستراليا)', 'Coal, Australian', 'دولار/طن', 1000],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::COLUMNS as [$key, $ar, $en, $unit, $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'M', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'worldbank.pink_sheet',
            source: new SourceDefinition(
                key: 'worldbank',
                nameAr: 'البنك الدولي',
                nameEn: 'World Bank',
                publisher: 'World Bank Commodities Price Data (The Pink Sheet)',
                url: 'https://www.worldbank.org/en/research/commodity-markets',
                license: 'CC BY 4.0',
            ),
            nameAr: 'أسعار السلع الشهرية — الورقة الوردية (البنك الدولي)',
            nameEn: 'Commodity prices — Pink Sheet (World Bank)',
            section: 'markets',
            intervalMinutes: 24 * 60,
            indicators: $indicators,
        );
    }

    /** رابط ملف الأسعار الشهرية الحالي من صفحة أسواق السلع. */
    private function fileUrl(): string
    {
        $html = $this->http()->accept('text/html')->timeout(60)->get(self::PAGE)->throw()->body();
        if (! preg_match('#https://thedocs\.worldbank\.org/[^"\s]*CMO-Historical-Data-Monthly\.xlsx#', $html, $m)) {
            throw new RuntimeException('Pink Sheet: لم يُعثر على رابط ملف الأسعار الشهرية في الصفحة.');
        }

        return $m[0];
    }

    public function probe(): ProbeResult
    {
        $url = $this->fileUrl();
        $head = $this->http()->head($url);
        $modified = $head->header('Last-Modified') ?: $head->header('ETag');

        return new ProbeResult(token: $url.'|'.$modified, version: 'Pink Sheet '.basename(dirname(dirname($url))), publishedAt: $head->header('Last-Modified') ? CarbonImmutable::parse($head->header('Last-Modified'))->utc() : null);
    }

    public function fetch(): RawPayload
    {
        $path = tempnam(sys_get_temp_dir(), 'pink');
        try {
            $this->http()->timeout(120)->sink($path)->get($this->fileUrl())->throw();
            $zip = new ZipArchive;
            if ($zip->open($path) !== true) {
                throw new RuntimeException('Pink Sheet: تعذّر فتح ملف Excel.');
            }
            $workbook = (string) $zip->getFromName('xl/workbook.xml');
            $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
            $strings = $this->sharedStrings((string) $zip->getFromName('xl/sharedStrings.xml'));
            $sheetPath = $this->sheetPath($workbook, $rels, 'Monthly Prices');
            $sheet = (string) $zip->getFromName($sheetPath);
            $zip->close();
        } finally {
            @unlink($path);
        }

        return new RawPayload(json_encode($this->table($sheet, $strings), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    private function sharedStrings(string $xml): array
    {
        $out = [];
        foreach ((new SimpleXMLElement($xml))->si as $si) {
            $out[] = trim(strip_tags((string) $si->asXML()));
        }

        return $out;
    }

    private function sheetPath(string $workbook, string $rels, string $name): string
    {
        $wb = new SimpleXMLElement($workbook);
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $rid = null;
        foreach ($wb->sheets->sheet as $sheet) {
            if ((string) $sheet['name'] === $name) {
                $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            }
        }
        if ($rid === null) {
            throw new RuntimeException("Pink Sheet: لا توجد ورقة باسم {$name}.");
        }
        foreach ((new SimpleXMLElement($rels))->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                return 'xl/'.ltrim((string) $rel['Target'], '/');
            }
        }

        throw new RuntimeException('Pink Sheet: تعذّر تحديد مسار الورقة.');
    }

    /**
     * يقرأ الورقة إلى [عنوان العمود => [[فترة، قيمة]]] للأعمدة المطلوبة.
     *
     * @param  list<string>  $strings
     * @return array<string, list<array{0: string, 1: float}>>
     */
    private function table(string $sheetXml, array $strings): array
    {
        $headers = [];
        $out = [];
        $xml = new SimpleXMLElement($sheetXml);
        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                if (! isset($c->v)) {
                    continue;
                }
                $col = preg_replace('/\d+/', '', (string) $c['r']);
                $cells[$col] = ((string) $c['t'] === 's') ? ($strings[(int) $c->v] ?? '') : (string) $c->v;
            }
            if ($headers === [] && isset($cells['B']) && array_key_exists($cells['B'], self::COLUMNS)) {
                $headers = array_filter($cells, fn (string $v) => isset(self::COLUMNS[$v]));
                $out = array_fill_keys($headers, []);

                continue;
            }
            if ($headers !== [] && isset($cells['A']) && preg_match('/^(\d{4})M(\d{2})$/', $cells['A'], $m)) {
                foreach ($headers as $col => $name) {
                    if (isset($cells[$col]) && is_numeric($cells[$col])) {
                        $out[$name][] = ["{$m[1]}-{$m[2]}", (float) $cells[$col]];
                    }
                }
            }
        }
        if ($headers === []) {
            throw new RuntimeException('Pink Sheet: لم تُوجد أعمدة الأسعار المتوقعة (تغيّر الملف؟).');
        }

        return $out;
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('WLD', 'World', 'group', 'العالم');

        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $name => $points) {
            foreach ($points as [$period, $value]) {
                yield new ParsedRecord(self::COLUMNS[$name][0], 'WLD', $period, $value);
            }
        }
    }
}
