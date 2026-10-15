<?php

namespace App\Modules\Wits\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesSpike;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * التجارة السلعية بين كل دولتين، من WITS (البنك الدولي) المبنية على UN Comtrade — كما تبلّغ بها الدولة المُبلِّغة.
 *   - إجماليات الدولة: wits.EXPORTS_TOTAL / wits.IMPORTS_TOTAL (قياسات).
 *   - الشركاء: wits.EXPORTS_TO (المُبلِّغ ← الشريك) و wits.IMPORTS_FROM (المُبلِّغ ← الشريك، أي ما يستورده منه) (علاقات ثنائية).
 * القيم بآلاف الدولارات. نافذة السنتين الأحدث المتاحتين؛ ما قبلهما يبقى تاريخًا. الفحص: آخر سنة بها بيانات لعدد كافٍ من الدول.
 */
final class WitsTradeConnector extends ApiConnector
{
    private const BASE = 'https://wits.worldbank.org/API/V1/SDMX/V21/datasource/tradestats-trade';

    private const YEARS = 2;

    private const MIN_REPORTERS = 100;

    private const BATCH = 5;

    private const FLOWS = ['EXPORTS' => 'XPRT-TRD-VL', 'IMPORTS' => 'MPRT-TRD-VL'];

    public function revision(): int
    {
        return 1;
    }

    /** مئات الاستدعاءات أو ملفات ثقيلة: طابورها المستقل كي لا تحجب بقية المصادر. */
    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $level = fn (float $max) => [new Range(0, $max), new SeriesSpike(100), new RelativeRevision(0.5, 0.25), new Completeness(0.2)];
        $pair = [new Range(0, 5e9), new RelativeRevision(0.5, 0.25), new Completeness(0.2)];

        return new DatasetDefinition(
            key: 'wits.trade',
            source: new SourceDefinition(
                key: 'wits',
                nameAr: 'WITS — الحل التجاري المتكامل (البنك الدولي)',
                nameEn: 'World Integrated Trade Solution (World Bank / UN Comtrade)',
                publisher: 'World Bank',
                url: 'https://wits.worldbank.org',
                license: 'World Bank Open Data (CC BY 4.0)',
            ),
            nameAr: 'التجارة السلعية بين الدول (WITS)',
            nameEn: 'Bilateral merchandise trade (WITS)',
            section: 'trade',
            intervalMinutes: 14 * 24 * 60,
            indicators: [
                new IndicatorDefinition('wits.EXPORTS_TOTAL', 'إجمالي الصادرات السلعية', 'Total merchandise exports', 'ألف دولار', 'A', $level(1e10)),
                new IndicatorDefinition('wits.IMPORTS_TOTAL', 'إجمالي الواردات السلعية', 'Total merchandise imports', 'ألف دولار', 'A', $level(1e10)),
                new IndicatorDefinition('wits.EXPORTS_TO', 'الصادرات إلى الشريك', 'Exports to partner', 'ألف دولار', 'A', $pair),
                new IndicatorDefinition('wits.IMPORTS_FROM', 'الواردات من الشريك', 'Imports from partner', 'ألف دولار', 'A', $pair),
            ],
        );
    }

    /**
     * آخر سنة لدى ≥ MIN_REPORTERS مُبلِّغًا، مع عددهم.
     *
     * @return array{year: int, reporters: int}
     */
    private function latestYear(): array
    {
        for ($year = (int) date('Y') - 1; $year >= (int) date('Y') - 5; $year--) {
            $res = $this->http()->get($this->url('ALL', $year, 'WLD', 'XPRT-TRD-VL'));
            if ($res->status() === 404) {
                continue;
            }
            $count = count($res->throw()->json('dataSets.0.series') ?? []);
            if ($count >= self::MIN_REPORTERS) {
                return ['year' => $year, 'reporters' => $count];
            }
        }

        throw new RuntimeException('WITS: لم تُوجد سنة حديثة ببيانات كافية.');
    }

    public function probe(): ProbeResult
    {
        ['year' => $year, 'reporters' => $n] = $this->latestYear();

        return new ProbeResult("{$year}:{$n}", "WITS / UN Comtrade — {$year}");
    }

    public function fetch(): RawPayload
    {
        ['year' => $latest] = $this->latestYear();
        $years = range($latest - self::YEARS + 1, $latest);

        $totals = [];
        $reporters = [];
        foreach ($years as $year) {
            foreach (self::FLOWS as $flow => $indicator) {
                $doc = $this->get($this->url('ALL', $year, 'WLD', $indicator));
                foreach ($this->series($doc) as $reporter => $partners) {
                    $reporters[$year][$reporter] = true;
                    $totals[$flow][$year][$reporter] = $partners['WLD'] ?? null;
                }
            }
        }

        $pairs = [];
        foreach ($years as $year) {
            $codes = array_keys($reporters[$year] ?? []);
            foreach (self::FLOWS as $flow => $indicator) {
                foreach (array_chunk($codes, self::BATCH) as $batch) {
                    $responses = $this->http()->timeout(90)->pool(fn (Pool $pool) => array_map(
                        fn (string $r) => $pool->as($r)->get($this->url($r, $year, 'ALL', $indicator)),
                        $batch,
                    ));
                    foreach ($batch as $r) {
                        $res = $responses[$r];
                        if ($res instanceof \Throwable || $res->status() === 404) {
                            continue; // دولة بلا بيانات شركاء لتلك السنة
                        }
                        foreach ($this->series($res->throw()->json()) as $reporter => $partners) {
                            foreach ($partners as $partner => $value) {
                                $pairs[$flow][$year][] = [$reporter, $partner, $value];
                            }
                        }
                    }
                }
            }
        }

        return new RawPayload(json_encode(['years' => $years, 'totals' => $totals, 'pairs' => $pairs], JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);

        $countries = array_flip(DB::table('core.entities')->where('type', 'country')->pluck('code')->all());
        if (count($countries) < 150) {
            throw new RuntimeException('جدول الدول غير جاهز بعد (ينتظر البنك الدولي): '.count($countries));
        }

        foreach ($data['totals'] as $flow => $byYear) {
            foreach ($byYear as $year => $byReporter) {
                foreach ($byReporter as $reporter => $value) {
                    if ($value !== null && isset($countries[$reporter])) {
                        yield new ParsedRecord("wits.{$flow}_TOTAL", $reporter, (string) $year, (float) $value);
                    }
                }
            }
        }

        foreach ($data['pairs'] as $flow => $byYear) {
            $indicator = $flow === 'EXPORTS' ? 'wits.EXPORTS_TO' : 'wits.IMPORTS_FROM';
            foreach ($byYear as $year => $rows) {
                foreach ($rows as [$reporter, $partner, $value]) {
                    // المجموعات والأقاليم (WLD، EUN…) وما ليس دولة معروفة يُتخطى؛ العلاقة بين دولتين فقط.
                    if ($value !== null && $value > 0 && $reporter !== $partner && isset($countries[$reporter], $countries[$partner])) {
                        yield new ParsedRelation($indicator, $reporter, $partner, (string) $year, (float) $value);
                    }
                }
            }
        }
    }

    private function url(string $reporter, int $year, string $partner, string $indicator): string
    {
        return self::BASE."/reporter/{$reporter}/year/{$year}/partner/{$partner}/product/Total/indicator/{$indicator}?format=JSON";
    }

    /** @return array<string, mixed> */
    private function get(string $url): array
    {
        $res = $this->http()->timeout(90)->get($url);
        if ($res->status() === 404) {
            return [];
        }

        return $res->throw()->json() ?? [];
    }

    /**
     * يفكّ SDMX-JSON إلى [مُبلِّغ => [شريك => قيمة]].
     *
     * @param  array<string, mixed>  $doc
     * @return array<string, array<string, float>>
     */
    private function series(array $doc): array
    {
        $series = $doc['dataSets'][0]['series'] ?? [];
        if ($series === []) {
            return [];
        }
        $dims = array_column($doc['structure']['dimensions']['series'] ?? [], null, 'id');
        if (! isset($dims['REPORTER'], $dims['PARTNER'])) {
            throw new RuntimeException('WITS: تغيّر شكل أبعاد السلسلة (المتوقع REPORTER × PARTNER).');
        }
        $order = array_column($doc['structure']['dimensions']['series'], 'id');
        $reporterAt = array_search('REPORTER', $order, true);
        $partnerAt = array_search('PARTNER', $order, true);

        $out = [];
        foreach ($series as $key => $s) {
            $idx = explode(':', (string) $key);
            $reporter = $dims['REPORTER']['values'][(int) $idx[$reporterAt]]['id'] ?? null;
            $partner = $dims['PARTNER']['values'][(int) $idx[$partnerAt]]['id'] ?? null;
            $value = $s['observations']['0'][0] ?? null;
            if ($reporter !== null && $partner !== null && is_numeric($value)) {
                $out[(string) $reporter][(string) $partner] = (float) $value;
            }
        }

        return $out;
    }
}
