<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;

/**
 * سوق العمل العُماني (سجلات التأمينات الاجتماعية ووزارة العمل عبر NCSI): العاملون حسب القطاع (حكومي/خاص) والجنسية والنوع — سنوي حتى العام الأخير.
 * كسر في السلسلة: سلاسل العُمانيين قفزت عند 2020 (القطاع الخاص 262 ألفًا ← 403 آلاف) بسبب توسيع تغطية السجلات لا نمو التوظيف؛ فتُحفظ من 2020 فصاعدًا
 * فقط، ولا تُوصَل بما قبلها. الإجماليات ومجاميع القطاعات متسقة طوال السلسلة.
 * أحدث وأفصل من المصادر العالمية: يتيح قياس التعمين في القطاع الخاص (عُمانيون ووافدون) بالأرقام المسجَّلة لا بالتقديرات.
 */
final class NcsiLabourConnector extends KnoemaConnector
{
    private const TOTAL = 1000000;

    private const PUBLIC = 1000010;

    private const PRIVATE = 1000020;

    private const FEMALE = 1000020;

    private const OMANI = 1000010;

    private const EXPAT = 1000020;

    private const WORKERS = 1000010;

    protected function knoemaDatasetId(): string
    {
        return 'byvmwhe';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.labour';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: العاملون حسب القطاع والجنسية';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman: workers by sector and nationality';
    }

    protected function section(): string
    {
        return 'oman';
    }

    protected function intervalMinutes(): int
    {
        return 3 * 24 * 60;
    }

    /** أول سنة متّسقة التعريف لسلاسل العُمانيين (قبلها تغطية أضيق). */
    public const OMANI_SERIES_FROM = 2020;

    public function parse(RawPayload $payload): iterable
    {
        foreach (parent::parse($payload) as $item) {
            if ($item instanceof ParsedRecord && str_contains($item->indicatorKey, 'OMANI') && (int) $item->period < self::OMANI_SERIES_FROM) {
                continue;
            }

            yield $item;
        }
    }

    protected function series(): array
    {
        $rules = fn () => [new Range(0, 1e8, 'reject'), new RelativeRevision(0.5, 0.25), new Completeness(0.1)];
        $s = fn (string $code, string $ar, string $en, array $filters) => new KnoemaSeries($code, $ar, $en, 'عامل', 'A', self::WORKERS, $rules(), $filters);
        $f = fn (int $sector = self::TOTAL, int $nationality = self::TOTAL, int $gender = self::TOTAL) => ['sector' => $sector, 'nationality' => $nationality, 'gender' => $gender];

        return [
            $s('LAB_WORKERS_TOTAL', 'إجمالي العاملين المسجَّلين', 'Total registered workers', $f()),
            $s('LAB_WORKERS_OMANI', 'العاملون العُمانيون', 'Omani workers', $f(nationality: self::OMANI)),
            $s('LAB_WORKERS_EXPAT', 'العاملون الوافدون', 'Expatriate workers', $f(nationality: self::EXPAT)),
            $s('LAB_PRIVATE_TOTAL', 'العاملون في القطاع الخاص', 'Private-sector workers', $f(sector: self::PRIVATE)),
            $s('LAB_PRIVATE_OMANI', 'العُمانيون في القطاع الخاص', 'Omani private-sector workers', $f(sector: self::PRIVATE, nationality: self::OMANI)),
            $s('LAB_PRIVATE_EXPAT', 'الوافدون في القطاع الخاص', 'Expatriate private-sector workers', $f(sector: self::PRIVATE, nationality: self::EXPAT)),
            $s('LAB_PUBLIC_OMANI', 'العُمانيون في القطاع الحكومي', 'Omani public-sector workers', $f(sector: self::PUBLIC, nationality: self::OMANI)),
            $s('LAB_PUBLIC_EXPAT', 'الوافدون في القطاع الحكومي', 'Expatriate public-sector workers', $f(sector: self::PUBLIC, nationality: self::EXPAT)),
            $s('LAB_FEMALE_OMANI', 'العُمانيات العاملات', 'Omani female workers', $f(nationality: self::OMANI, gender: self::FEMALE)),
        ];
    }
}
