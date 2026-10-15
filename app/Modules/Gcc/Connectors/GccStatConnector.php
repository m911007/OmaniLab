<?php

namespace App\Modules\Gcc\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use RuntimeException;

/**
 * المركز الإحصائي لدول مجلس التعاون (GCC-Stat) عبر Fusion Registry / SDMX-JSON.
 * كل التدفقات MOBILE_DF_* ببُعد سلسلة واحد (GCC_COUNTRY) وبُعد زمن (TIME_PERIOD).
 * لا يوفر المصدر تاريخ تعديل رخيصًا، فالفحص بلا بصمة (token = null) والتغيّر يُكشف ببصمة الحمولة.
 * الأبعاد تُتحقق بصرامة: أي تغيير في شكل التدفق يوقف الجلب بدل أن يُقرأ خطأً.
 */
abstract class GccStatConnector extends ApiConnector
{
    private const BASE = 'https://sdmx.marsa.gccstat.org/FusionRegistry/ws/public/sdmxapi/rest/data';

    private const ENTITIES = [
        'OMN' => ['Oman', 'سلطنة عُمان', 'country'],
        'SAU' => ['Saudi Arabia', 'المملكة العربية السعودية', 'country'],
        'ARE' => ['United Arab Emirates', 'الإمارات العربية المتحدة', 'country'],
        'QAT' => ['Qatar', 'قطر', 'country'],
        'KWT' => ['Kuwait', 'الكويت', 'country'],
        'BHR' => ['Bahrain', 'البحرين', 'country'],
        'GCC' => ['GCC', 'دول مجلس التعاون', 'group'],
    ];

    /** @return list<GccFlow> */
    abstract protected function flows(): array;

    abstract protected function datasetKey(): string;

    abstract protected function datasetNameAr(): string;

    abstract protected function datasetNameEn(): string;

    abstract protected function section(): string;

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    public function definition(): DatasetDefinition
    {
        return new DatasetDefinition(
            key: $this->datasetKey(),
            source: new SourceDefinition(
                key: 'gccstat',
                nameAr: 'المركز الإحصائي لدول مجلس التعاون',
                nameEn: 'GCC Statistical Centre (GCC-Stat)',
                publisher: 'GCC-Stat',
                url: 'https://gccstat.org',
                license: 'GCC-Stat open data — attribution required',
            ),
            nameAr: $this->datasetNameAr(),
            nameEn: $this->datasetNameEn(),
            section: $this->section(),
            intervalMinutes: $this->intervalMinutes(),
            indicators: array_map(fn (GccFlow $f) => new IndicatorDefinition(
                key: $this->indicatorKey($f), nameAr: $f->nameAr, nameEn: $f->nameEn, unit: $f->unit, frequency: $f->frequency, rules: $f->rules,
            ), $this->flows()),
        );
    }

    protected function indicatorKey(GccFlow $flow): string
    {
        return 'gccstat.'.$flow->code;
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(null);
    }

    public function fetch(): RawPayload
    {
        $body = [];
        foreach ($this->flows() as $flow) {
            $url = self::BASE."/GCCSTAT.MOBILE,{$flow->flowId},1.0/all?format=sdmx-json&detail=full";
            $doc = $this->http()->replaceHeaders(['Accept' => 'application/vnd.sdmx.data+json'])->get($url)->throw()->json();
            // نحتفظ بجزء data فقط: meta يحمل وقت إعداد الرد ومعرّفه فيتغيران في كل طلب ويُفسدان مقارنة بصمة الحمولة.
            $body[$flow->flowId] = ['data' => $doc['data'] ?? null];
        }

        return new RawPayload(json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);

        foreach (self::ENTITIES as $code => [$en, $ar, $type]) {
            yield new ParsedEntity($code, $en, $type, $ar);
        }

        foreach ($this->flows() as $flow) {
            yield from $this->parseFlow($flow, $data[$flow->flowId] ?? throw new RuntimeException("التدفق {$flow->flowId} غائب عن الحمولة."));
        }
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return iterable<ParsedRecord>
     */
    private function parseFlow(GccFlow $flow, array $doc): iterable
    {
        $structure = $doc['data']['structure'] ?? throw new RuntimeException("{$flow->flowId}: لا بنية في الاستجابة.");
        $seriesDims = $structure['dimensions']['series'] ?? [];
        $obsDims = $structure['dimensions']['observation'] ?? [];

        if (count($seriesDims) !== 1 || $seriesDims[0]['id'] !== 'GCC_COUNTRY' || count($obsDims) !== 1 || $obsDims[0]['id'] !== 'TIME_PERIOD') {
            throw new RuntimeException("{$flow->flowId}: تغيّر شكل أبعاد التدفق (المتوقع GCC_COUNTRY × TIME_PERIOD).");
        }

        $countries = array_column($seriesDims[0]['values'], 'id');
        $periods = array_column($obsDims[0]['values'], 'id');

        foreach ($doc['data']['dataSets'][0]['series'] ?? [] as $seriesIndex => $series) {
            $country = $countries[(int) $seriesIndex] ?? null;
            if ($country === null) {
                continue;
            }
            foreach ($series['observations'] ?? [] as $periodIndex => $obs) {
                $raw = $obs[0] ?? null;
                $period = $periods[(int) $periodIndex] ?? null;
                if ($period === null || $raw === null || ! is_numeric($raw)) {
                    continue;
                }
                yield new ParsedRecord($this->indicatorKey($flow), (string) $country, $this->normalisePeriod((string) $period), (float) $raw);
            }
        }
    }

    /** يوحّد صيغ الفترات: 2024 | 2024-Q1 | 2024-01. */
    private function normalisePeriod(string $period): string
    {
        return preg_replace('/^(\d{4})-M(\d{2})$/', '$1-$2', $period) ?? $period;
    }
}
