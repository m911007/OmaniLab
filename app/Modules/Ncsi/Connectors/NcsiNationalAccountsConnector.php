<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesSpike;

/** الحسابات القومية لعُمان — سنوي. */
final class NcsiNationalAccountsConnector extends KnoemaConnector
{
    private const TOTAL = 1000000;

    private const CURRENT = 1000010;

    private const CONSTANT = 1000020;

    /** أنشطة القطاع في تصنيف النشاط الاقتصادي غير النفطي: الزراعة والغابات والصيد، والزراعة والغابات، والصيد */
    private const AGRI_FISH = 1000050;

    private const AGRI = 1000060;

    private const FISH = 1000070;

    /** مؤشرا «القيمة المضافة الإجمالية» و«الحصة النسبية %» (من الناتج) في بُعد المؤشرات */
    private const VALUE_ADDED = 1000250;

    private const SHARE = 1000270;

    protected function knoemaDatasetId(): string
    {
        return 'zoangf';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.national_accounts';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: الحسابات القومية';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman national accounts';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(0, 1e9), new SeriesSpike(50), new RelativeRevision(0.5, 0.25), new Completeness(0.1)];
        $total = ['economic-activity-of-non-petrol' => self::TOTAL, 'institutional-sector' => self::TOTAL];

        return [
            new KnoemaSeries('GDP_CURRENT', 'الناتج المحلي الإجمالي بالأسعار الجارية', 'GDP at market prices, current', 'مليون ريال عُماني', 'A', 1000010, $rules(), $total + ['price-type' => self::CURRENT]),
            new KnoemaSeries('GDP_CONSTANT', 'الناتج المحلي الإجمالي بالأسعار الثابتة', 'GDP at market prices, constant', 'مليون ريال عُماني', 'A', 1000010, $rules(), $total + ['price-type' => self::CONSTANT]),
            new KnoemaSeries('GDP_PER_CAPITA_USD', 'نصيب الفرد من الناتج المحلي', 'GDP per capita', 'دولار', 'A', 1000060, $rules(), $total + ['price-type' => self::CURRENT]),
            new KnoemaSeries('EXPORTS_GS', 'صادرات السلع والخدمات', 'Exports of goods and services', 'مليون ريال عُماني', 'A', 1000120, $rules(), $total + ['price-type' => self::CURRENT]),
            new KnoemaSeries('IMPORTS_GS', 'واردات السلع والخدمات', 'Imports of goods and services', 'مليون ريال عُماني', 'A', 1000130, $rules(), $total + ['price-type' => self::CURRENT]),
            ...$this->sectorSeries(),
        ];
    }

    /**
     * مساهمة الزراعة والصيد في الناتج المحلي: الحصة % بالأسعار الجارية والثابتة (2010 فصاعدًا)، والقيمة المضافة بمليون ريال (منشورة من 2018).
     * مجموع «الزراعة والغابات» و«الصيد» يساوي نشاط «الزراعة والغابات والصيد».
     *
     * @return list<KnoemaSeries>
     */
    private function sectorSeries(): array
    {
        $rules = fn (float $max) => [new Range(0, $max), new Completeness(0.1)];
        $activities = [
            'AGRI_FISH' => [self::AGRI_FISH, 'الزراعة والغابات والصيد', 'Agriculture, forestry and fishing'],
            'AGRI' => [self::AGRI, 'الزراعة والغابات', 'Agriculture and forestry'],
            'FISH' => [self::FISH, 'الصيد', 'Fishing'],
        ];
        $series = [];
        foreach ($activities as $code => [$member, $ar, $en]) {
            foreach ([['', self::CURRENT, 'بالأسعار الجارية', 'current'], ['_CONST', self::CONSTANT, 'بالأسعار الثابتة', 'constant']] as [$suffix, $price, $arPrice, $enPrice]) {
                $filters = ['economic-activity-of-non-petrol' => $member, 'institutional-sector' => self::TOTAL, 'price-type' => $price];
                $series[] = new KnoemaSeries("GDP_SHARE_{$code}{$suffix}", "مساهمة {$ar} في الناتج المحلي {$arPrice}", "{$en}: share of GDP, {$enPrice}", '% من الناتج', 'A', self::SHARE, $rules(100), $filters);
                if ($code !== 'AGRI_FISH') {
                    $series[] = new KnoemaSeries("VA_{$code}{$suffix}", "القيمة المضافة: {$ar} {$arPrice}", "{$en}: gross value added, {$enPrice}", 'مليون ريال عُماني', 'A', self::VALUE_ADDED, $rules(1e9), $filters);
                }
            }
        }

        return $series;
    }
}
