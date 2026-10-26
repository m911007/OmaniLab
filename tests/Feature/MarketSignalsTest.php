<?php

namespace Tests\Feature;

use App\Modules\MarketIntel\IntelReference;
use App\Modules\MarketIntel\SignalEngine;
use Illuminate\Support\Facades\Cache;
use Tests\DatabaseTestCase;

class MarketSignalsTest extends DatabaseTestCase
{
    /** @param  array<string, mixed>  $over */
    private function analysis(string $mode, array $over = []): array
    {
        return array_replace_recursive([
            'summary' => ['mode' => $mode, 'oman_value' => 100.0, 'oman_unit_value' => 130.0, 'median_unit_value' => 100.0, 'saving_estimate' => 5e6, 'hhi_market' => 1000],
            'candidates' => [
                ['iso' => 'JPN', 'name_ar' => 'اليابان', 'oman_value' => 80.0, 'score' => 60, 'cagr' => 1.0, 'share' => 0.3, 'fta' => null, 'tariff' => ['tariff' => 12.0, 'year' => 2019]],
                ['iso' => 'KOR', 'name_ar' => 'كوريا', 'oman_value' => 0.0, 'score' => 80, 'cagr' => 9.0, 'share' => 0.2, 'fta' => null, 'tariff' => null],
                ['iso' => 'ARE', 'name_ar' => 'الإمارات', 'oman_value' => 20.0, 'score' => 50, 'cagr' => 2.0, 'share' => 0.1, 'fta' => 'السوق الخليجية المشتركة', 'tariff' => null],
            ],
            'commodity' => [['name_ar' => 'القمح', 'period' => '2026-09', 'change_12m' => 0.3]],
            'world_trend' => [], 'oman_trend' => [], 'rca' => 2.5, 'pci' => 1.2,
        ], $over);
    }

    public function test_import_signals_flag_dependency_overpaying_alternatives_and_prices(): void
    {
        $titles = array_column((new SignalEngine)->build($this->analysis('import')), 'title');

        $this->assertContains('اعتماد مرتفع على مورد واحد', $titles);
        $this->assertContains('عُمان تدفع أعلى من وسيط السوق', $titles);
        $this->assertContains('مورد بديل غير مستغل: كوريا', $titles);
        $this->assertContains('موردون بإعفاء جمركي باتفاقية', $titles);
        $this->assertContains('ارتفاع سعر السلعة عالميًا', $titles);
        $this->assertContains('منتج عالي التعقيد', $titles);
        $this->assertSame('اعتماد مرتفع على مورد واحد', $titles[0], 'الأعلى أثرًا أولًا');
    }

    public function test_export_signals_find_untapped_markets_tariffs_and_rca(): void
    {
        $signals = (new SignalEngine)->build($this->analysis('export'));
        $byTitle = collect($signals)->keyBy('title');

        $this->assertTrue($byTitle->has('سوق نامٍ لا تصل إليه عُمان: كوريا'));
        $this->assertTrue($byTitle->has('حواجز جمركية في أسواق كبيرة'));
        $this->assertSame('opportunity', $byTitle['لعُمان ميزة نسبية ظاهرة']['level']);
        $this->assertSame('opportunity', $byTitle['ارتفاع سعر السلعة عالميًا']['level'], 'ارتفاع السعر فرصة للمصدّر');
    }

    public function test_catalog_groups_products_by_section_and_chapter(): void
    {
        Cache::flush();
        Cache::put('mi:products:v2', [
            ['id' => 52709, 'hs4' => '2709', 'name_en' => 'Crude Petroleum', 'name_ar' => 'x'],
            ['id' => 11001, 'hs4' => '1001', 'name_en' => 'Wheat', 'name_ar' => 'y'],
            ['id' => 11006, 'hs4' => '1006', 'name_en' => 'Rice', 'name_ar' => 'z'],
        ]);

        $res = $this->getJson('/api/v1/market-intel/catalog')->assertOk();
        $this->assertSame([1, 5], array_column($res->json('sections'), 'id'));
        $this->assertSame(2, collect($res->json('chapters'))->firstWhere('hs2', '10')['count']);
        $this->assertTrue(collect($res->json('products'))->firstWhere('hs4', '1001')['commodity']);
        $this->assertSame(IntelReference::SECTIONS[5], $res->json('sections.1.name'));
    }
}
