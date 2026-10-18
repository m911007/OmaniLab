<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/** المنشآت الاقتصادية وعدد العاملين حسب النشاط الاقتصادي (NCSI). */
final class NcsiEstablishmentsConnector extends KnoemaConnector
{
    protected function knoemaDatasetId(): string
    {
        return 'qxpcuo';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.establishments';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: المنشآت والعاملون حسب النشاط';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman establishments and employees by activity';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    protected function skipEmptySeries(): bool
    {
        return true;
    }

    protected function series(): array
    {
        $r = fn () => [new Range(0, 1e13, 'reject'), new Completeness(0.1)];

        return [
            new KnoemaSeries('EST_TOTAL', 'عدد المنشآت — الإجمالي', 'Establishments — total', 'منشأة', 'A', 1000000, $r(), ['economic-activity' => 1000000, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_EMPLOYEES', 'عدد العاملين — الإجمالي', 'Employees — total', 'عامل', 'A', 1000010, $r(), ['economic-activity' => 1000000, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_AGRI', 'منشآت الزراعة وصيد الأسماك', 'Agriculture & fishing establishments', 'منشأة', 'A', 1000000, $r(), ['economic-activity' => 1000010, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_MINING', 'منشآت التعدين واستغلال المحاجر', 'Mining & quarrying establishments', 'منشأة', 'A', 1000000, $r(), ['economic-activity' => 1000020, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_MANUFACTURING', 'منشآت الصناعة التحويلية', 'Manufacturing establishments', 'منشأة', 'A', 1000000, $r(), ['economic-activity' => 1000030, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_CONSTRUCTION', 'منشآت التشييد', 'Construction establishments', 'منشأة', 'A', 1000000, $r(), ['economic-activity' => 1000060, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_TRADE', 'منشآت تجارة الجملة والتجزئة', 'Trade establishments', 'منشأة', 'A', 1000000, $r(), ['economic-activity' => 1000070, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_MANUF_WORKERS', 'العاملون في الصناعة التحويلية', 'Manufacturing employees', 'عامل', 'A', 1000010, $r(), ['economic-activity' => 1000030, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_MINING_WORKERS', 'العاملون في التعدين والمحاجر', 'Mining employees', 'عامل', 'A', 1000010, $r(), ['economic-activity' => 1000020, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
            new KnoemaSeries('EST_AGRI_WORKERS', 'العاملون في الزراعة وصيد الأسماك', 'Agriculture & fishing employees', 'عامل', 'A', 1000010, $r(), ['economic-activity' => 1000010, 'nationality' => 1000000, 'type-of-establishment' => 1000000]),
        ];
    }
}
