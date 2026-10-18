<?php

namespace Tests\Feature;

use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Modules\Firms\Connectors\WikidataFirmsConnector;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WikidataFirmsTest extends TestCase
{
    private function claim(mixed $value, string $rank = 'normal', ?string $point = null): array
    {
        return ['rank' => $rank, 'mainsnak' => ['datavalue' => ['value' => $value]], 'qualifiers' => $point ? ['P585' => [['datavalue' => ['value' => ['time' => $point]]]]] : []];
    }

    public function test_it_extracts_the_latest_preferred_values_and_links_labels_and_currency(): void
    {
        $this->freshHttp();
        $firm = [
            'labels' => ['en' => ['value' => 'Acme Corp'], 'ar' => ['value' => 'شركة أكمي']],
            'sitelinks' => ['enwiki' => ['title' => 'Acme Corp']],
            'claims' => [
                'P1128' => [$this->claim(['amount' => '+100'], 'normal', '+2019-00-00T00:00:00Z'), $this->claim(['amount' => '+250'], 'normal', '+2023-00-00T00:00:00Z'), $this->claim(['amount' => '+999'], 'deprecated')],
                'P2139' => [$this->claim(['amount' => '+5000000', 'unit' => 'http://www.wikidata.org/entity/Q4917'], 'normal', '+2023-00-00T00:00:00Z')],
                'P571' => [$this->claim(['time' => '+1950-01-01T00:00:00Z'])],
                'P856' => [$this->claim('https://acme.example')],
                'P452' => [$this->claim(['id' => 'Q1'])],
                'P159' => [$this->claim(['id' => 'Q2'])],
            ],
        ];
        $countries = ['results' => ['bindings' => array_map(fn ($i) => ['c' => ['value' => "http://www.wikidata.org/entity/Q{$i}"], 'iso' => ['value' => 'C'.str_pad((string) $i, 2, '0', STR_PAD_LEFT)]], range(1, 48))]];
        Http::fake(function ($request) use ($countries, $firm) {
            $url = $request->url();
            if (str_contains($url, 'query.wikidata.org')) {
                return str_contains((string) $request['query'], 'P298') ? Http::response($countries) : Http::response(['results' => ['bindings' => [['c' => ['value' => 'http://www.wikidata.org/entity/Q100']]]]]);
            }
            $ids = (string) $request['ids'];
            if ($ids === 'Q100') {
                return Http::response(['entities' => ['Q100' => $firm]]);
            }
            if (str_contains($ids, 'Q4917')) {
                return Http::response(['entities' => ['Q4917' => ['claims' => ['P498' => [['mainsnak' => ['datavalue' => ['value' => 'USD']]]]]]]]);
            }

            return Http::response(['entities' => ['Q1' => ['labels' => ['en' => ['value' => 'Software'], 'ar' => ['value' => 'برمجيات']]], 'Q2' => ['labels' => ['en' => ['value' => 'Springfield']]]]]);
        });
        $c = new WikidataFirmsConnector;

        $items = iterator_to_array($c->parse($c->fetch()), false);
        $attrs = [];
        foreach ($items as $i) {
            if ($i instanceof ParsedAttribute) {
                $attrs[$i->key] = $i->value;
            }
        }

        $this->assertCount(1, array_filter($items, fn ($i) => $i instanceof ParsedEntity && $i->code === 'wd:Q100' && $i->type === 'firm' && $i->nameAr === 'شركة أكمي'));
        $this->assertCount(1, array_filter($items, fn ($i) => $i instanceof ParsedRecord && $i->indicatorKey === 'firms.count' && $i->value === 1.0));
        $this->assertSame(250, $attrs['employees']);
        $this->assertEquals(['amount' => 5000000, 'currency' => 'USD', 'year' => '2023'], $attrs['revenue']);
        $this->assertSame('1950', $attrs['inception']);
        $this->assertSame(['Software'], $attrs['industry']);
        $this->assertSame(['برمجيات'], $attrs['industry_ar']);
        $this->assertSame('Springfield', $attrs['hq']);
        $this->assertSame('https://en.wikipedia.org/wiki/Acme_Corp', $attrs['wikipedia_en']);
    }
}
