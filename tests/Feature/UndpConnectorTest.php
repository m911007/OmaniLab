<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Undp\Connectors\UndpHdrConnector;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class UndpConnectorTest extends TestCase
{
    private function csv(string $header = 'iso3,country,hdicode,region,hdi_rank_2023,hdi_2022,hdi_2023,le_2023,gnipc_2023,unknown_2023'): string
    {
        return implode("\n", [
            $header,
            'OMN,Oman,Very High,ECS,59,0.85,0.858,80.03,36096.04,9',
            "CIV,C\xF4te d'Ivoire,Low,SSA,170,0.5,,59.2,5000,9",   // ترميز Windows-1252
            'ZZA.VHHD,Very high human development,,,,0.9,0.91,79,40000,9',  // صف تجميعي
            'BAD,Bad,,,,0.1,7.5,500,0,9',                                    // قيم خارج النطاق
        ])."\n";
    }

    private function fakeRelease(int $year, string $body, string $modified = 'Mon, 05 May 2025 22:39:12 GMT'): void
    {
        $this->freshHttp();
        $path = sprintf('%d_HDR/HDR%02d_Composite_indices_complete_time_series.csv', $year, $year % 100);
        Http::fake(fn (Request $r) => str_contains($r->url(), $path)
            ? Http::response($r->method() === 'HEAD' ? '' : $body, 200, ['Last-Modified' => $modified])
            : Http::response('', 404));
    }

    public function test_probe_finds_the_latest_published_release_and_uses_last_modified(): void
    {
        $this->fakeRelease((int) date('Y') - 1, $this->csv());

        $p = (new UndpHdrConnector)->probe();

        $this->assertStringContainsString('Mon, 05 May 2025', $p->token);
        $this->assertSame('HDR '.((int) date('Y') - 1), $p->version);
        $this->assertSame('2025-05-05', $p->publishedAt->toDateString());
    }

    public function test_probe_fails_when_no_recent_release_exists(): void
    {
        $this->freshHttp();
        Http::fake(fn () => Http::response('', 404));

        $this->expectException(RuntimeException::class);
        (new UndpHdrConnector)->probe();
    }

    public function test_parse_reads_year_columns_converts_encoding_and_skips_aggregates_and_unknown_columns(): void
    {
        $this->fakeRelease((int) date('Y') - 1, $this->csv());
        $c = new UndpHdrConnector;

        $records = collect(iterator_to_array($c->parse($c->fetch()), false))->filter(fn (mixed $i) => $i instanceof ParsedRecord)
            ->map(fn (ParsedRecord $r) => "{$r->indicatorKey}|{$r->entityCode}|{$r->period}|{$r->value}")->sort()->values()->all();

        $this->assertContains('undp.hdi|OMN|2023|0.858', $records);
        $this->assertContains('undp.hdi|OMN|2022|0.85', $records);
        $this->assertContains('undp.le|CIV|2023|59.2', $records, 'السطر المُشكَّل بترميز Windows-1252 قُرئ');
        $this->assertNotContains('undp.hdi|CIV|2023|0', $records, 'الخلية الفارغة تُتخطى');
        $this->assertEmpty(array_filter($records, fn ($r) => str_contains($r, 'ZZA') || str_contains($r, 'unknown')), 'المجموعات والأعمدة غير المعرَّفة تُتخطى');
    }

    public function test_parse_fails_loudly_on_a_changed_file_layout(): void
    {
        $this->fakeRelease((int) date('Y') - 1, "country,code\nOman,OMN\n");
        $c = new UndpHdrConnector;
        $payload = $c->fetch();

        $this->expectExceptionMessage('تغيّر شكل الملف');
        iterator_to_array($c->parse($payload));
    }

    public function test_parse_fails_when_none_of_the_expected_columns_exist(): void
    {
        $this->fakeRelease((int) date('Y') - 1, "iso3,country,foo_2023\nOMN,Oman,1\n");
        $c = new UndpHdrConnector;
        $payload = $c->fetch();

        $this->expectExceptionMessage('لم تُوجد أعمدة المؤشرات');
        iterator_to_array($c->parse($payload));
    }

    public function test_every_indicator_has_validation_rules(): void
    {
        $def = (new UndpHdrConnector)->definition();

        $this->assertCount(12, $def->indicators);
        foreach ($def->indicators as $i) {
            $this->assertStringStartsWith('undp.', $i->key);
            $this->assertNotEmpty($i->rules);
        }
    }
}
