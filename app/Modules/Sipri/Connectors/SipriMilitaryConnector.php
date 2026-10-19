<?php

namespace App\Modules\Sipri\Connectors;

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
 * الإنفاق العسكري عالميًا من معهد ستوكهولم لأبحاث السلام (SIPRI Military Expenditure Database): بالدولار الثابت، ونسبة من الناتج،
 * ونصيب الفرد، ونسبة من الإنفاق الحكومي، لنحو 170 دولة منذ 1949 (والنسب منذ 1988). رابط الملف يتغير مع كل إصدار فيُستخرج من صفحة القاعدة.
 * الدول تُعرَّف في الملف بأسمائها الإنجليزية فتُحوَّل إلى ISO3 بجدول ثابت؛ ما لا يُعرف (يوغوسلافيا، الاتحاد السوفييتي…) يُتخطى.
 */
final class SipriMilitaryConnector extends ApiConnector
{
    private const PAGE = 'https://www.sipri.org/databases/milex';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float, 5: float}> مفتاح => [ورقة، عربي، إنجليزي، وحدة، مضاعف، حد أعلى] */
    private const SHEETS = [
        'sipri.milex_usd' => ['Constant (2024) US$', 'الإنفاق العسكري (دولار ثابت 2024)', 'Military expenditure (constant 2024 US$ m)', 'مليون دولار', 1.0, 2e6],
        'sipri.milex_gdp' => ['Share of GDP', 'الإنفاق العسكري (% من الناتج)', 'Military expenditure (% of GDP)', '%', 100.0, 100],
        'sipri.milex_per_capita' => ['Per capita', 'الإنفاق العسكري للفرد (دولار)', 'Military expenditure per capita (US$)', 'دولار', 1.0, 20000],
        'sipri.milex_gov_share' => ['Share of Govt. spending', 'الإنفاق العسكري (% من الإنفاق الحكومي)', 'Military expenditure (% of government spending)', '%', 100.0, 100],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SHEETS as $key => [, $ar, $en, $unit, , $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, 'A', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'sipri.military_expenditure',
            source: new SourceDefinition(key: 'sipri', nameAr: 'معهد ستوكهولم لأبحاث السلام (SIPRI)', nameEn: 'Stockholm International Peace Research Institute', publisher: 'SIPRI', url: 'https://www.sipri.org/databases/milex', license: 'SIPRI — مع الإشارة إلى المصدر'),
            nameAr: 'الإنفاق العسكري عالميًا (SIPRI)',
            nameEn: 'World military expenditure (SIPRI)',
            section: 'governance',
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
        if (! preg_match('#href="((?:https:)?//www\.sipri\.org/sites/default/files/SIPRI-Milex-data[^"?]*\.xlsx)"#', $page, $m)) {
            throw new RuntimeException('SIPRI: لم يُعثر على رابط ملف الإنفاق العسكري في الصفحة.');
        }
        $url = str_starts_with($m[1], '//') ? 'https:'.$m[1] : $m[1];
        $path = tempnam(sys_get_temp_dir(), 'sipri');
        $out = [];
        $names = [];
        try {
            $this->http()->timeout(300)->sink($path)->get($url)->throw();
            $book = new XlsxReader($path);
            foreach (self::SHEETS as $key => [$sheet, , , , $mult]) {
                $rows = $book->sheet($sheet);
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
                if ($years === []) {
                    throw new RuntimeException("SIPRI: لا أعمدة سنوات في الورقة {$sheet}.");
                }
                foreach ($rows as $row) {
                    $iso = CountryNames::iso($row['A'] ?? null) ?? null;
                    if ($iso === null) {
                        continue;
                    }
                    $names[$iso] = (string) $row['A'];
                    foreach ($years as $col => $year) {
                        if (isset($row[$col]) && is_numeric($row[$col])) {
                            $out[] = [$key, $iso, $year, round((float) $row[$col] * $mult, 4)];
                        }
                    }
                }
            }
        } finally {
            @unlink($path);
        }
        if ($out === []) {
            throw new RuntimeException('SIPRI: لا بيانات.');
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
