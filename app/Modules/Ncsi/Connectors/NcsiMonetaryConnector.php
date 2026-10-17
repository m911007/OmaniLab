<?php

namespace App\Modules\Ncsi\Connectors;

use App\Core\Validation\Rules\Completeness;
use App\Core\Validation\Rules\Range;

/**
 * القطاع النقدي في عُمان (البنك المركزي العُماني عبر NCSI) — شهري حتى آخر شهر منشور: أسعار الفائدة (الإقراض والإيداع والريبو)،
 * عرض النقد والائتمان للقطاع الخاص وصافي الأصول الأجنبية، والاحتياطيات الرسمية وسعر الصرف الفعلي. يملأ الفجوة التي تركها منع الوصول الآلي لموقع البنك المركزي.
 * لا تُجلب الميزانية المجمّعة للبنوك ولا السلامة المالية (CAR/NPL): توقفت عند 2018.
 */
final class NcsiMonetaryConnector extends KnoemaConnector
{
    private const INTEREST = 1000000;

    private const CBO_BALANCE_SHEET = 1000010;

    private const MONETARY_SURVEY = 1000020;

    private const EXCHANGE_RATES = 1000050;

    private const RESERVES = 1000060;

    protected function knoemaDatasetId(): string
    {
        return 'gsngkvb';
    }

    protected function datasetKey(): string
    {
        return 'ncsi.monetary';
    }

    protected function datasetNameAr(): string
    {
        return 'عُمان: القطاع النقدي (الفائدة والنقد والاحتياطيات)';
    }

    protected function datasetNameEn(): string
    {
        return 'Oman monetary sector (rates, money, reserves)';
    }

    protected function section(): string
    {
        return 'finance';
    }

    protected function intervalMinutes(): int
    {
        return 24 * 60;
    }

    protected function series(): array
    {
        $rate = fn () => [new Range(-5, 60, 'reject'), new Completeness(0.1)];
        $level = fn () => [new Range(-1e8, 1e9, 'reject'), new Completeness(0.1)];
        $rateSeries = fn (string $code, string $ar, string $en, int $member) => new KnoemaSeries($code, $ar, $en, '%', 'M', $member, $rate(), ['domain' => self::INTEREST]);
        $omr = fn (string $code, string $ar, string $en, int $member, int $domain) => new KnoemaSeries($code, $ar, $en, 'مليون ريال عُماني', 'M', $member, $level(), ['domain' => $domain]);
        $usd = fn (string $code, string $ar, string $en, int $member) => new KnoemaSeries($code, $ar, $en, 'مليون دولار', 'M', $member, $level(), ['domain' => self::RESERVES]);

        return [
            $rateSeries('MON_RATE_LENDING_RO', 'سعر الإقراض بالريال (البنوك التقليدية)', 'Rial Omani lending rate', 1000130),
            $rateSeries('MON_RATE_DEPOSIT_TIME_RO', 'سعر الودائع الزمنية بالريال', 'Rial Omani time-deposit rate', 1000070),
            $rateSeries('MON_RATE_DEPOSIT_TOTAL_RO', 'سعر إجمالي الودائع بالريال', 'Rial Omani total-deposit rate', 1000110),
            $rateSeries('MON_RATE_REPO', 'متوسط سعر الريبو مع البنك المركزي', 'Average repo rate with CBO', 1000030),
            $rateSeries('MON_RATE_OVERNIGHT', 'سعر الإقراض بين البنوك لليلة', 'Overnight interbank rate', 1000170),
            $omr('MON_M2', 'عرض النقد الواسع (M2)', 'Broad money (M2)', 1000500, self::MONETARY_SURVEY),
            $omr('MON_M1', 'عرض النقد الضيق (M1)', 'Narrow money (M1)', 1000510, self::MONETARY_SURVEY),
            $omr('MON_QUASI_MONEY', 'شبه النقد', 'Quasi money', 1000540, self::MONETARY_SURVEY),
            $omr('MON_NET_FOREIGN_ASSETS', 'صافي الأصول الأجنبية', 'Net foreign assets', 1000560, self::MONETARY_SURVEY),
            $omr('MON_CREDIT_PRIVATE', 'المطالبات على القطاع الخاص (الائتمان)', 'Claims on the private sector (credit)', 1000690, self::MONETARY_SURVEY),
            $omr('MON_NET_CLAIMS_GOV', 'صافي المطالبات على الحكومة', 'Net claims on government', 1000600, self::MONETARY_SURVEY),
            $omr('MON_CBO_FOREIGN_ASSETS', 'الأصول الأجنبية للبنك المركزي', 'CBO foreign assets', 1000210, self::CBO_BALANCE_SHEET),
            $usd('MON_RESERVES_EX_GOLD', 'الاحتياطيات الرسمية دون الذهب', 'Official reserves excluding gold', 1001280),
            $usd('MON_RESERVES_FX', 'احتياطيات النقد الأجنبي', 'Foreign exchange reserves', 1001330),
            $usd('MON_GOLD_VALUE', 'قيمة الذهب (تقييم وطني)', 'Gold (national valuation)', 1001320),
            new KnoemaSeries('MON_NEER', 'سعر الصرف الفعلي الاسمي (1999=100)', 'Nominal effective exchange rate (1999=100)', 'مؤشر', 'M', 1001250, [new Range(0, 1000, 'reject'), new Completeness(0.1)], ['domain' => self::EXCHANGE_RATES]),
        ];
    }
}
