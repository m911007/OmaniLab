<?php

namespace App\Modules\Owid\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Range;

/**
 * النفط والغاز والفحم لكل الدول من «المراجعة الإحصائية للطاقة العالمية» (Energy Institute) عبر OWID: الإنتاج والاستهلاك بالتيراواط ساعة
 * والاحتياطيات المؤكدة وحصة الوقود الأحفوري. وتُشتق منها وحدات مألوفة: ألف برميل/يوم (1 برميل ≈ 1.7 ميغاواط ساعة)،
 * ومليار برميل احتياطي، وتريليون متر مكعب غاز. مصدر ثانٍ مستقل عن المركز الوطني للإحصاء ووزارة الطاقة.
 */
final class OwidFossilFuelsConnector extends OwidCsvConnector
{
    private const MWH_PER_BARREL = 1.7;

    private const M3_PER_BARREL = 0.158987;

    protected function series(): array
    {
        return [
            'oil-production-by-country' => ['owid.oil_production_twh', 'إنتاج النفط (طاقة)', 'Oil production (energy)', 'تيراواط ساعة', 1e5],
            'gas-production-by-country' => ['owid.gas_production_twh', 'إنتاج الغاز الطبيعي (طاقة)', 'Gas production (energy)', 'تيراواط ساعة', 1e5],
            'oil-consumption-by-country' => ['owid.oil_consumption_twh', 'استهلاك النفط (طاقة)', 'Oil consumption (energy)', 'تيراواط ساعة', 1e5],
            'gas-consumption-by-country' => ['owid.gas_consumption_twh', 'استهلاك الغاز الطبيعي (طاقة)', 'Gas consumption (energy)', 'تيراواط ساعة', 1e5],
            'coal-production-by-country' => ['owid.coal_production_twh', 'إنتاج الفحم (طاقة)', 'Coal production (energy)', 'تيراواط ساعة', 1e5],
            'oil-proved-reserves' => ['owid.oil_reserves_m3', 'احتياطي النفط المؤكد (حجم)', 'Proved oil reserves (volume)', 'متر مكعب', 1e13],
            'natural-gas-proved-reserves' => ['owid.gas_reserves_m3', 'احتياطي الغاز المؤكد (حجم)', 'Proved gas reserves (volume)', 'متر مكعب', 1e15],
            'fossil-fuels-share-energy' => ['owid.fossil_share', 'حصة الوقود الأحفوري من الطاقة الأولية', 'Fossil fuels share of primary energy', '%', 100.5],
        ];
    }

    protected function datasetKey(): string
    {
        return 'owid.fossil_fuels';
    }

    protected function datasetNameAr(): string
    {
        return 'النفط والغاز والفحم (Energy Institute عبر OWID)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oil, gas and coal (Energy Institute via OWID)';
    }

    protected function section(): string
    {
        return 'energy';
    }

    public function definition(): DatasetDefinition
    {
        $base = parent::definition();
        $extra = [
            new IndicatorDefinition('owid.oil_production_kbd', 'إنتاج النفط (ألف برميل/يوم)', 'Oil production (thousand barrels/day)', 'ألف برميل/يوم', 'A', [new Range(0, 1e5, 'reject')]),
            new IndicatorDefinition('owid.oil_reserves_bn_bbl', 'احتياطي النفط المؤكد (مليار برميل)', 'Proved oil reserves (billion barrels)', 'مليار برميل', 'A', [new Range(0, 1e4, 'reject')]),
            new IndicatorDefinition('owid.gas_reserves_tcm', 'احتياطي الغاز المؤكد (تريليون م³)', 'Proved gas reserves (trillion m³)', 'تريليون م³', 'A', [new Range(0, 1e3, 'reject')]),
        ];

        return new DatasetDefinition($base->key, $base->source, $base->nameAr, $base->nameEn, $base->section, $base->intervalMinutes, [...$base->indicators, ...$extra], $base->reliability);
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (parent::parse($payload) as $r) {
            yield $r;
            if (! $r instanceof ParsedRecord) {
                continue;
            }
            if ($r->indicatorKey === 'owid.oil_production_twh') {
                yield new ParsedRecord('owid.oil_production_kbd', $r->entityCode, $r->period, round($r->value * 1e6 / self::MWH_PER_BARREL / 365 / 1000, 1));
            } elseif ($r->indicatorKey === 'owid.oil_reserves_m3') {
                yield new ParsedRecord('owid.oil_reserves_bn_bbl', $r->entityCode, $r->period, round($r->value / self::M3_PER_BARREL / 1e9, 2));
            } elseif ($r->indicatorKey === 'owid.gas_reserves_m3') {
                yield new ParsedRecord('owid.gas_reserves_tcm', $r->entityCode, $r->period, round($r->value / 1e12, 3));
            }
        }
    }
}
