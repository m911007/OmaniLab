<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * السياحة في عُمان (وزارة التراث والسياحة عبر NCSI): نزلاء الفنادق وإشغال الغرف وإيراداتها والعاملون فيها شهريًا حتى آخر شهر منشور،
 * وحصة السياحة من الناتج وعدد الفنادق ونسبة الإشغال السنوية. أعداد الزوار حسب الجنسية متوقفة عند 2014 فلا تُجلب.
 */
final class NcsiTourismConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'dedblxg';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.tourism';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: السياحة والفنادق';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman tourism and hotels';
    }

    protected function section(): string
    {
        return 'tourism';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function series(): array
    {
        $count = fn () => [new Range(0, 1e9, 'reject'), new Completeness(0.1)];
        $pct = fn () => [new Range(0, 100, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('TOUR_HOTEL_GUESTS', 'نزلاء الفنادق (شهري)', 'Hotel guests (monthly)', 'نزيل', 'M', 1000210, $count()),
            new KnoemaSeries('TOUR_ROOM_OCCUPANCY', 'نسبة إشغال الغرف (شهري)', 'Room occupancy ratio (monthly)', '%', 'M', 1000310, $pct()),
            new KnoemaSeries('TOUR_HOTEL_REVENUE', 'إيرادات الفنادق (شهري)', 'Hotel revenues (monthly)', 'ريال عُماني', 'M', 1000430, $count()),
            new KnoemaSeries('TOUR_HOTEL_EMPLOYEES', 'العاملون في الفنادق (شهري)', 'Hotel employees (monthly)', 'عامل', 'M', 1000220, $count()),
            new KnoemaSeries('TOUR_GDP_SHARE', 'حصة السياحة من الناتج المحلي', 'Share of tourism in GDP', '%', 'A', 1000170, $pct()),
            new KnoemaSeries('TOUR_HOTELS', 'عدد الفنادق', 'Number of hotels', 'فندق', 'A', 1000200, $count()),
            new KnoemaSeries('TOUR_OCCUPANCY_ANNUAL', 'نسبة الإشغال السنوية', 'Annual occupancy rate', '%', 'A', 1000400, $pct()),
        ];
    }
}
