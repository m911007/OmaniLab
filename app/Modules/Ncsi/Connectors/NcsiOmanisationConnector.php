<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * التعمين حسب النشاط الاقتصادي في القطاع الخاص (المركز الوطني للإحصاء، سوق العمل): عدد العُمانيين والوافدين في كل نشاط (صناعة، تعدين، تشييد، تجارة،
 * سياحة، نقل، اتصالات، مال، تعليم…) ونسبة العُمانيين المشتقة (عُماني ÷ إجمالي). أساس المحتوى المحلي في القوى العاملة: أين تتركز الوظائف الوطنية وأين تبقى الفجوة.
 */
final class NcsiOmanisationConnector extends KnoemaConnector
{
    private const WORKERS = 1000010;

    private const TOTAL = 1000000;

    private const PRIVATE = 1000020;

    private const OMANI = 1000010;

    private const EXPAT = 1000020;

    /** @var array<string, array{0: string, 1: string}> */
    private const ACTIVITIES = [
        'AGRI' => ['الزراعة والحراجة وصيد الأسماك', 'Agriculture, forestry and fishing'],
        'MINING' => ['التعدين واستغلال المحاجر', 'Mining and quarrying'],
        'MANUF' => ['الصناعات التحويلية', 'Manufacturing'],
        'UTIL' => ['إمدادات الكهرباء والغاز', 'Electricity and gas supply'],
        'CONSTR' => ['التشييد', 'Construction'],
        'TRADE' => ['تجارة الجملة والتجزئة', 'Wholesale and retail trade'],
        'HOSP' => ['الإقامة والخدمات الغذائية', 'Accommodation and food'],
        'TRANSP' => ['النقل والتخزين', 'Transport and storage'],
        'ICT' => ['المعلومات والاتصالات', 'Information and communication'],
        'FIN' => ['الوساطة المالية', 'Financial intermediation'],
        'REALEST' => ['الأنشطة العقارية', 'Real estate'],
        'EDU' => ['التعليم', 'Education'],
        'PROF' => ['الأنشطة المهنية والعلمية', 'Professional and scientific'],
        'ADMIN' => ['الخدمات الإدارية والدعم', 'Administrative and support'],
        'ALL' => ['كل الأنشطة', 'All activities'],
    ];

    protected function knoemaDatasetId(): string
    {
        return 'byvmwhe';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.omanisation';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: التعمين حسب النشاط الاقتصادي في القطاع الخاص';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman: Omanisation by economic activity (private sector)';
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
        $r = fn () => [new Range(0, 1e8, 'reject'), new Completeness(0.1)];
        $f = fn (int $activity, int $nationality) => ['sector' => self::PRIVATE, 'nationality' => $nationality, 'economic-activity' => $activity];
        $s = fn (string $code, string $ar, string $en, array $filters) => new KnoemaSeries($code, $ar, $en, 'عامل', 'A', self::WORKERS, $r(), $filters);

        return [
            $s('OMNS_ALL_OMANI', 'العُمانيون في القطاع الخاص (كل الأنشطة)', 'Omani private-sector workers (all)', $f(self::TOTAL, self::OMANI)),
            $s('OMNS_ALL_EXPAT', 'الوافدون في القطاع الخاص (كل الأنشطة)', 'Expat private-sector workers (all)', $f(self::TOTAL, self::EXPAT)),
            $s('OMNS_AGRI_OMANI', 'العُمانيون في القطاع الخاص — الزراعة والحراجة وصيد الأسماك', 'Omani private workers — Agriculture, forestry and fishing', $f(1000220, self::OMANI)),
            $s('OMNS_AGRI_EXPAT', 'الوافدون في القطاع الخاص — الزراعة والحراجة وصيد الأسماك', 'Expat private workers — Agriculture, forestry and fishing', $f(1000220, self::EXPAT)),
            $s('OMNS_MINING_OMANI', 'العُمانيون في القطاع الخاص — التعدين واستغلال المحاجر', 'Omani private workers — Mining and quarrying', $f(1000030, self::OMANI)),
            $s('OMNS_MINING_EXPAT', 'الوافدون في القطاع الخاص — التعدين واستغلال المحاجر', 'Expat private workers — Mining and quarrying', $f(1000030, self::EXPAT)),
            $s('OMNS_MANUF_OMANI', 'العُمانيون في القطاع الخاص — الصناعات التحويلية', 'Omani private workers — Manufacturing', $f(1000040, self::OMANI)),
            $s('OMNS_MANUF_EXPAT', 'الوافدون في القطاع الخاص — الصناعات التحويلية', 'Expat private workers — Manufacturing', $f(1000040, self::EXPAT)),
            $s('OMNS_UTIL_OMANI', 'العُمانيون في القطاع الخاص — إمدادات الكهرباء والغاز', 'Omani private workers — Electricity and gas supply', $f(1000230, self::OMANI)),
            $s('OMNS_UTIL_EXPAT', 'الوافدون في القطاع الخاص — إمدادات الكهرباء والغاز', 'Expat private workers — Electricity and gas supply', $f(1000230, self::EXPAT)),
            $s('OMNS_CONSTR_OMANI', 'العُمانيون في القطاع الخاص — التشييد', 'Omani private workers — Construction', $f(1000060, self::OMANI)),
            $s('OMNS_CONSTR_EXPAT', 'الوافدون في القطاع الخاص — التشييد', 'Expat private workers — Construction', $f(1000060, self::EXPAT)),
            $s('OMNS_TRADE_OMANI', 'العُمانيون في القطاع الخاص — تجارة الجملة والتجزئة', 'Omani private workers — Wholesale and retail trade', $f(1000070, self::OMANI)),
            $s('OMNS_TRADE_EXPAT', 'الوافدون في القطاع الخاص — تجارة الجملة والتجزئة', 'Expat private workers — Wholesale and retail trade', $f(1000070, self::EXPAT)),
            $s('OMNS_HOSP_OMANI', 'العُمانيون في القطاع الخاص — الإقامة والخدمات الغذائية', 'Omani private workers — Accommodation and food', $f(1000080, self::OMANI)),
            $s('OMNS_HOSP_EXPAT', 'الوافدون في القطاع الخاص — الإقامة والخدمات الغذائية', 'Expat private workers — Accommodation and food', $f(1000080, self::EXPAT)),
            $s('OMNS_TRANSP_OMANI', 'العُمانيون في القطاع الخاص — النقل والتخزين', 'Omani private workers — Transport and storage', $f(1000250, self::OMANI)),
            $s('OMNS_TRANSP_EXPAT', 'الوافدون في القطاع الخاص — النقل والتخزين', 'Expat private workers — Transport and storage', $f(1000250, self::EXPAT)),
            $s('OMNS_ICT_OMANI', 'العُمانيون في القطاع الخاص — المعلومات والاتصالات', 'Omani private workers — Information and communication', $f(1000260, self::OMANI)),
            $s('OMNS_ICT_EXPAT', 'الوافدون في القطاع الخاص — المعلومات والاتصالات', 'Expat private workers — Information and communication', $f(1000260, self::EXPAT)),
            $s('OMNS_FIN_OMANI', 'العُمانيون في القطاع الخاص — الوساطة المالية', 'Omani private workers — Financial intermediation', $f(1000100, self::OMANI)),
            $s('OMNS_FIN_EXPAT', 'الوافدون في القطاع الخاص — الوساطة المالية', 'Expat private workers — Financial intermediation', $f(1000100, self::EXPAT)),
            $s('OMNS_REALEST_OMANI', 'العُمانيون في القطاع الخاص — الأنشطة العقارية', 'Omani private workers — Real estate', $f(1000110, self::OMANI)),
            $s('OMNS_REALEST_EXPAT', 'الوافدون في القطاع الخاص — الأنشطة العقارية', 'Expat private workers — Real estate', $f(1000110, self::EXPAT)),
            $s('OMNS_EDU_OMANI', 'العُمانيون في القطاع الخاص — التعليم', 'Omani private workers — Education', $f(1000120, self::OMANI)),
            $s('OMNS_EDU_EXPAT', 'الوافدون في القطاع الخاص — التعليم', 'Expat private workers — Education', $f(1000120, self::EXPAT)),
            $s('OMNS_PROF_OMANI', 'العُمانيون في القطاع الخاص — الأنشطة المهنية والعلمية', 'Omani private workers — Professional and scientific', $f(1000360, self::OMANI)),
            $s('OMNS_PROF_EXPAT', 'الوافدون في القطاع الخاص — الأنشطة المهنية والعلمية', 'Expat private workers — Professional and scientific', $f(1000360, self::EXPAT)),
            $s('OMNS_ADMIN_OMANI', 'العُمانيون في القطاع الخاص — الخدمات الإدارية والدعم', 'Omani private workers — Administrative and support', $f(1000370, self::OMANI)),
            $s('OMNS_ADMIN_EXPAT', 'الوافدون في القطاع الخاص — الخدمات الإدارية والدعم', 'Expat private workers — Administrative and support', $f(1000370, self::EXPAT)),
        ];
    }

    public function definition(): DatasetDefinition
    {
        $d = parent::definition();
        $derived = [];
        foreach (self::ACTIVITIES as $code => [$ar, $en]) {
            $derived[] = new IndicatorDefinition("ncsi.OMNS_{$code}_PCT", "نسبة العُمانيين في القطاع الخاص — {$ar}", "Omani share of private workers — {$en}", '%', 'A', [new Range(0, 100, 'reject')]);
        }

        return new DatasetDefinition(
            key: $d->key, source: $d->source, nameAr: $d->nameAr, nameEn: $d->nameEn, section: $d->section, intervalMinutes: $d->intervalMinutes, reliability: $d->reliability,
            indicators: [...$d->indicators, ...$derived],
        );
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');
        $by = [];
        foreach ($data['series'] as $code => $points) {
            foreach ($points as [$period, $value]) {
                $by[$code][$period] = (float) $value;
                yield new ParsedRecord("ncsi.{$code}", 'OMN', $period, (float) $value);
            }
        }
        foreach (array_keys(self::ACTIVITIES) as $act) {
            foreach ($by["OMNS_{$act}_OMANI"] ?? [] as $period => $omani) {
                $expat = $by["OMNS_{$act}_EXPAT"][$period] ?? null;
                if ($expat !== null && $omani + $expat > 0) {
                    yield new ParsedRecord("ncsi.OMNS_{$act}_PCT", 'OMN', $period, round($omani / ($omani + $expat) * 100, 2));
                }
            }
        }
    }
}
