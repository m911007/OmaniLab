<?php

namespace App\Modules\Macro\Connectors;

use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Range;

/**
 * التجارة السلعية الشهرية (IMF IMTS): إجمالي صادرات ووارداتها لدول الخليج، وصادرات وواردات عُمان شهريًا حسب الشريك.
 * أحدث من النشرات السنوية بأشهر، فيلتقط الصدمات (توقف ممر، تغير سعر) قبل أن تصل إلى الحسابات القومية.
 * الصادرات «فوب» والواردات «سيف» بالدولار. الرصيد التجاري مشتق = الصادرات − الواردات.
 */
final class ImfMonthlyTradeConnector extends ImfSdmxConnector
{
    private const WORLD = 'G001';

    protected function flow(): string
    {
        return 'IMF.STA,IMTS';
    }

    protected function key(): string
    {
        return 'OMN+SAU+ARE+QAT+KWT+BHR.XG_FOB_USD+MG_CIF_USD..M?startPeriod=2022-01';
    }

    /** @return list<string> */
    protected function codeColumns(): array
    {
        return ['INDICATOR', 'COUNTERPART_COUNTRY'];
    }

    protected function datasetKey(): string
    {
        return 'imf.imts';
    }

    protected function datasetNameAr(): string
    {
        return 'التجارة السلعية الشهرية (IMF IMTS)';
    }

    protected function datasetNameEn(): string
    {
        return 'Monthly merchandise trade (IMF IMTS)';
    }

    protected function section(): string
    {
        return 'trade';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function indicators(): array
    {
        return [
            'exports' => new IndicatorDefinition('imf.imts_exports', 'الصادرات السلعية الشهرية (فوب)', 'Monthly goods exports (FOB)', 'دولار', 'M', [new Range(0, 5e11, 'reject')]),
            'imports' => new IndicatorDefinition('imf.imts_imports', 'الواردات السلعية الشهرية (سيف)', 'Monthly goods imports (CIF)', 'دولار', 'M', [new Range(0, 5e11, 'reject')]),
            'balance' => new IndicatorDefinition('imf.imts_balance', 'الرصيد التجاري السلعي الشهري', 'Monthly goods trade balance', 'دولار', 'M', [new Range(-5e11, 5e11, 'reject')]),
            'exports_partner' => new IndicatorDefinition('imf.imts_exports_partner', 'صادرات عُمان الشهرية إلى الشريك', 'Oman monthly exports to partner', 'دولار', 'M', [new Range(0, 5e11, 'reject')]),
            'imports_partner' => new IndicatorDefinition('imf.imts_imports_partner', 'واردات عُمان الشهرية من الشريك', 'Oman monthly imports from partner', 'دولار', 'M', [new Range(0, 5e11, 'reject')]),
        ];
    }

    public function parse(RawPayload $payload): iterable
    {
        $totals = [];
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$iso, $code, $period, $value]) {
            if (! preg_match('/^[A-Z]{3}$/', $iso) || ! preg_match('/^(\d{4})-M(\d{2})$/', $period, $m) || ! is_numeric($value) || (float) $value < 0) {
                continue;
            }
            $parts = explode('.', $code);
            $indicator = $parts[0];
            $partner = $parts[1] ?? null;
            $flow = $indicator === 'XG_FOB_USD' ? 'exports' : ($indicator === 'MG_CIF_USD' ? 'imports' : null);
            $month = "{$m[1]}-{$m[2]}";
            if ($flow === null || $partner === null) {
                continue;
            }
            if ($partner === self::WORLD) {
                $totals[$iso][$month][$flow] = (float) $value;
                yield new ParsedRecord("imf.imts_{$flow}", $iso, $month, (float) $value);
            } elseif ($iso === 'OMN' && preg_match('/^[A-Z]{3}$/', $partner)) {
                yield new ParsedRelation("imf.imts_{$flow}_partner", 'OMN', $partner, $month, (float) $value);
            }
        }
        foreach ($totals as $iso => $months) {
            foreach ($months as $month => $v) {
                if (isset($v['exports'], $v['imports'])) {
                    yield new ParsedRecord('imf.imts_balance', $iso, $month, $v['exports'] - $v['imports']);
                }
            }
        }
    }
}
