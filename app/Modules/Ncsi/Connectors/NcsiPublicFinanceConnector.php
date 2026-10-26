<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Validation\Rules\Range;

/**
 * المالية العامة لسلطنة عُمان (المركز الوطني للإحصاء، OMPBFNC2016): إيرادات الحكومة حسب المصدر (نفط وغاز وغير نفطية بتفصيل الضرائب
 * والرسوم والخدمات والاستثمار) والمصروفات حتى 2024 — مصدر «من أين تتمول الدولة».
 * وحدة المصدر تتبدل بين سنواته (ريال ثم ألف ثم مليون)، فتُوحَّد إلى مليون ريال عُماني بالاستمرارية مع القيمة السابقة (انظر normalise).
 */
final class NcsiPublicFinanceConnector extends KnoemaConnector
{
    private const NON_OIL = 'sources-of-non-oil-revenue';

    private const EXPENDITURE = 'major-expenditure-items';

    protected function knoemaDatasetId(): string
    {
        return 'OMPBFNC2016';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.public_finance';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: إيرادات الحكومة ومصروفاتها حسب المصدر';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman: government revenue and expenditure by source';
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
        $r = fn () => [new Range(0, 100_000, 'reject')];
        $s = fn (string $code, string $ar, string $en, int $indicator, array $filters = []) => new KnoemaSeries($code, $ar, $en, 'مليون ريال عُماني', 'A', $indicator, $r(), $filters);
        $non = fn (string $code, string $ar, string $en, int $member) => $s("PF_{$code}", $ar, $en, 1000050, [self::NON_OIL => $member]);

        return [
            $s('REV_TOTAL', 'إجمالي إيرادات الحكومة', 'Total government revenue', 1000010),
            $s('REV_OIL', 'الإيرادات النفطية', 'Oil revenue', 1000020),
            $s('REV_OIL_NET', 'صافي الإيرادات النفطية', 'Net oil revenue', 1000030),
            $s('REV_GAS', 'إيرادات الغاز الطبيعي', 'Natural gas revenue', 1000040),
            $s('REV_NON_OIL', 'الإيرادات غير النفطية', 'Non-oil revenue', 1000050),
            $s('EXP_TOTAL', 'إجمالي المصروفات الحكومية', 'Total government expenditure', 1000100),
            $s('EXP_CURRENT', 'المصروفات الجارية', 'Current expenditure', 1000120),
            $s('EXP_DEFENCE', 'مصروفات الدفاع والأمن', 'Defence and security expenditure', 1000150, [self::EXPENDITURE => 1000000]),
            $s('SUPPORT_TOTAL', 'إجمالي الدعم والمساهمات', 'Total support and contributions', 1000360, [self::EXPENDITURE => 1000000]),
            $s('SUPPORT_FUEL', 'دعم المنتجات النفطية', 'Petroleum products support', 1000400, [self::EXPENDITURE => 1000000]),
            $s('SUPPORT_ELECTRICITY', 'دعم قطاع الكهرباء', 'Electricity sector support', 1000390, [self::EXPENDITURE => 1000000]),
            $s('SUPPORT_FOOD', 'دعم السلع الغذائية', 'Food commodities support', 1000420, [self::EXPENDITURE => 1000000]),
            $non('TAXES', 'إجمالي الإيرادات الضريبية', 'Total tax revenue', 1000020),
            $non('CORPORATE_TAX', 'ضريبة دخل الشركات', 'Corporate income tax', 1000030),
            $non('PROPERTY_TAX', 'ضرائب الملكية العقارية', 'Property taxes', 1000080),
            $non('LOCAL_GOODS_TAX', 'الضرائب المحلية على السلع والخدمات', 'Local taxes on goods and services', 1000110),
            $non('CUSTOMS', 'الرسوم الجمركية', 'Customs fees', 1000210),
            $non('SERVICES', 'إيرادات الخدمات', 'Services revenue', 1000230),
            $non('AIRPORTS', 'إيرادات المطارات', 'Airport revenue', 1000310),
            $non('TELECOM', 'إيرادات المرافق الاتصالية', 'Telecommunication facility revenue', 1000330),
            $non('INVESTMENT', 'إيرادات الاستثمار', 'Investment revenue', 1000350),
            $non('DIVIDENDS', 'أرباح وتوزيعات الاستثمارات الحكومية', 'Profits and dividends from government investments', 1000380),
            $non('FEES', 'إيرادات الرسوم', 'Fees revenue', 1000430),
            $non('IMMIGRATION_FEES', 'رسوم الهجرة والجوازات', 'Immigration and passport fees', 1000440),
            $non('MISC', 'إيرادات متنوعة', 'Miscellaneous revenue', 1000540),
            $non('WORK_PERMITS', 'رسوم استقدام العمالة غير العُمانية', 'Work permit fees for non-Omani workers', 1000550),
            $non('FINES', 'التعويضات والغرامات والمصادرات', 'Compensations, fines and forfeits', 1000560),
            $non('MINING', 'إيرادات التعدين', 'Mining revenue', 1000570),
            $non('CAPITAL', 'الإيرادات الرأسمالية', 'Capital revenue', 1000650),
        ];
    }

    /**
     * يوحّد وحدة السلسلة إلى مليون ريال: أول قيمة غير صفرية تُعدّ بالريال، وكل لاحقة تُضرب في 1000^k (k = 0..3) لتكون الأقرب لسابقتها.
     *
     * @param  list<array{0: string, 1: float}>  $points  مرتبة زمنيًا
     * @return list<array{0: string, 1: float}>
     */
    public static function normalise(array $points): array
    {
        $out = [];
        $prev = null;
        foreach ($points as [$period, $value]) {
            if ($value == 0.0) {
                $out[] = [$period, 0.0];

                continue;
            }
            $best = $value;
            if ($prev !== null) {
                $bestDistance = INF;
                foreach ([1, 1e3, 1e6, 1e9] as $scale) {
                    $candidate = $value * $scale;
                    $distance = abs(log($candidate / $prev));
                    if ($distance < $bestDistance) {
                        [$best, $bestDistance] = [$candidate, $distance];
                    }
                }
            }
            $prev = $best;
            $out[] = [$period, round($best / 1e6, 3)];
        }

        return $out;
    }

    public function definition(): DatasetDefinition
    {
        $d = parent::definition();

        return new DatasetDefinition(
            key: $d->key, source: $d->source, nameAr: $d->nameAr, nameEn: $d->nameEn, section: $d->section, intervalMinutes: $d->intervalMinutes, reliability: $d->reliability,
            indicators: [...$d->indicators, new IndicatorDefinition('ncsi.BUDGET_BALANCE', 'الفائض/العجز الفعلي للميزانية (الإيرادات − المصروفات)', 'Actual budget balance (revenue − expenditure)', 'مليون ريال عُماني', 'A', [new Range(-1e8, 1e8, 'reject')])],
        );
    }

    public function parse(RawPayload $payload): iterable
    {
        $data = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);

        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');

        $byCode = [];
        foreach ($data['series'] as $code => $points) {
            foreach (self::normalise($points) as [$period, $value]) {
                $byCode[$code][$period] = $value;
                yield new ParsedRecord("ncsi.{$code}", 'OMN', $period, $value);
            }
        }
        // الفائض/العجز الفعلي = إجمالي الإيرادات − إجمالي المصروفات للسنوات التي يتوفر فيها الاثنان.
        foreach ($byCode['REV_TOTAL'] ?? [] as $period => $revenue) {
            if (isset($byCode['EXP_TOTAL'][$period])) {
                yield new ParsedRecord('ncsi.BUDGET_BALANCE', 'OMN', $period, round($revenue - $byCode['EXP_TOTAL'][$period], 3));
            }
        }
    }
}
