<?php

namespace App\Modules\Manafeth\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;
use App\Core\Validation\Rules\RelativeRevision;
use App\Core\Validation\Rules\SeriesSpike;

/**
 * إجماليات التجارة الخارجية لعُمان 1998→: الاستيراد والتصدير وإعادة التصدير، وتوزيعها بحسب وسيلة النقل (بحري/جوي/بري).
 * السنة الجارية غير المكتملة تُنشر بمؤشرات *_YTD منفصلة (قيمة منذ بداية العام حتى آخر شهر منشور) كي لا تُقرأ كسنة كاملة.
 * ملاحظة: حقل by_post في منافذ هو المنافذ البرية فعليًا (بري) وليس البريد.
 */
final class ManafethTotalsConnector extends ManafethConnector
{
    /** المؤشر => [حقل المصدر, الاسم العربي, الاسم الإنجليزي] */
    private const ANNUAL = [
        'IMPORTS' => ['import_value', 'الواردات السلعية', 'Merchandise imports'],
        'EXPORTS' => ['export_value', 'الصادرات السلعية (دون إعادة التصدير)', 'Merchandise exports (excl. re-exports)'],
        'REEXPORTS' => ['reexport_value', 'إعادة التصدير', 'Re-exports'],
        'IMPORTS_SEA' => ['totalimport_by_sea', 'الواردات عبر المنافذ البحرية', 'Imports by sea'],
        'IMPORTS_AIR' => ['totalimport_by_air', 'الواردات عبر المنافذ الجوية', 'Imports by air'],
        'IMPORTS_LAND' => ['totalimport_by_post', 'الواردات عبر المنافذ البرية', 'Imports by land'],
        'EXPORTS_SEA' => ['totalexport_by_sea', 'الصادرات عبر المنافذ البحرية', 'Exports by sea'],
        'EXPORTS_AIR' => ['totalexport_by_air', 'الصادرات عبر المنافذ الجوية', 'Exports by air'],
        'EXPORTS_LAND' => ['totalexport_by_post', 'الصادرات عبر المنافذ البرية', 'Exports by land'],
        'REEXPORTS_SEA' => ['totalreexport_by_sea', 'إعادة التصدير عبر المنافذ البحرية', 'Re-exports by sea'],
        'REEXPORTS_AIR' => ['totalreexport_by_air', 'إعادة التصدير عبر المنافذ الجوية', 'Re-exports by air'],
        'REEXPORTS_LAND' => ['totalreexport_by_post', 'إعادة التصدير عبر المنافذ البرية', 'Re-exports by land'],
    ];

    private const YTD = ['IMPORTS', 'EXPORTS', 'REEXPORTS'];

    public function definition(): DatasetDefinition
    {
        $rules = fn () => [new Range(0, 1e13), new SeriesSpike(50), new RelativeRevision(0.5, 0.25), new Completeness(0.1)];
        $indicators = [];
        foreach (self::ANNUAL as $code => [, $ar, $en]) {
            $indicators[] = new IndicatorDefinition("manafeth.{$code}", $ar, $en, 'ريال عُماني', 'A', $rules());
        }
        foreach (self::YTD as $code) {
            [, $ar, $en] = self::ANNUAL[$code];
            $indicators[] = new IndicatorDefinition("manafeth.{$code}_YTD", "{$ar} منذ بداية العام", "{$en}, year to date", 'ريال عُماني', 'A', [new Range(0, 1e13)]);
        }

        return new DatasetDefinition(
            key: 'manafeth.totals',
            source: $this->source(),
            nameAr: 'عُمان: التجارة الخارجية السلعية — الإجماليات ووسائل النقل (منافذ)',
            nameEn: 'Oman merchandise trade totals and transport modes (Manafeth)',
            section: 'trade',
            intervalMinutes: 24 * 60,
            indicators: $indicators,
        );
    }

    public function fetch(): RawPayload
    {
        $rows = $this->statistics('');
        ['year' => $year, 'month' => $month] = $this->latestPeriod();

        return new RawPayload(json_encode([
            'latest_year' => $year, 'latest_month' => $month,
            'years' => array_map(fn ($r) => array_intersect_key($r, array_flip(['year', ...array_column(self::ANNUAL, 0)])), $rows),
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        $partialYear = $data['latest_month'] < 12 ? $data['latest_year'] : null;

        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');

        foreach ($data['years'] as $row) {
            $year = (int) $row['year'];
            foreach (self::ANNUAL as $code => [$field]) {
                $value = $row[$field] ?? null;
                if ($value === null || ! is_numeric($value)) {
                    continue;
                }
                if ($year === $partialYear) {
                    if (in_array($code, self::YTD, true)) {
                        yield new ParsedRecord("manafeth.{$code}_YTD", 'OMN', (string) $year, (float) $value);
                    }

                    continue;
                }
                yield new ParsedRecord("manafeth.{$code}", 'OMN', (string) $year, (float) $value);
            }
        }
    }
}
