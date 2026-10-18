<?php

namespace App\Modules\Markets\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Validation\Rules\Range;
use RuntimeException;

/**
 * مالية الحكومة الأمريكية (وزارة الخزانة، FiscalData): الدين العام اليومي، ومتوسط الفائدة على السندات حسب نوعها شهريًا، وإيرادات الحكومة
 * الفدرالية شهريًا حسب المصدر (ضرائب الأفراد والشركات والتأمينات الاجتماعية والسلع والجمارك…) من البيان الشهري للخزانة (MTS جدول 4).
 */
final class UsTreasuryFiscalConnector extends ApiConnector
{
    private const BASE = 'https://api.fiscaldata.treasury.gov/services/api/fiscal_service/';

    /** @var array<string, array{0: string, 1: string, 2: string}> وصف التصنيف في MTS => [مفتاح، عربي، إنجليزي] */
    private const RECEIPTS = [
        'Total -- Individual Income Taxes' => ['us.receipts_individual', 'إيرادات ضرائب دخل الأفراد', 'Individual income tax receipts'],
        'Corporation Income Taxes' => ['us.receipts_corporate', 'إيرادات ضرائب الشركات', 'Corporate income tax receipts'],
        'Total -- Social Insurance and Retirement Receipts' => ['us.receipts_social_insurance', 'إيرادات التأمينات الاجتماعية والتقاعد', 'Social insurance and retirement receipts'],
        'Total -- Excise Taxes' => ['us.receipts_excise', 'إيرادات الضرائب الانتقائية', 'Excise tax receipts'],
        'Customs Duties' => ['us.receipts_customs', 'إيرادات الرسوم الجمركية', 'Customs duties'],
        'Estate and Gift Taxes' => ['us.receipts_estate_gift', 'ضرائب التركات والهبات', 'Estate and gift taxes'],
        'Total -- Miscellaneous Receipts' => ['us.receipts_misc', 'إيرادات متنوعة', 'Miscellaneous receipts'],
        'Total -- Receipts' => ['us.receipts_total', 'إجمالي إيرادات الحكومة الفدرالية', 'Total federal receipts'],
    ];

    /** @var array<string, array{0: string, 1: string, 2: string}> */
    private const RATES = [
        'Treasury Bills' => ['us.avg_rate_bills', 'متوسط الفائدة على أذونات الخزانة', 'Average rate on Treasury bills'],
        'Treasury Notes' => ['us.avg_rate_notes', 'متوسط الفائدة على سندات الخزانة المتوسطة', 'Average rate on Treasury notes'],
        'Treasury Bonds' => ['us.avg_rate_bonds', 'متوسط الفائدة على سندات الخزانة الطويلة', 'Average rate on Treasury bonds'],
        'Total Marketable' => ['us.avg_rate_marketable', 'متوسط الفائدة على الدين القابل للتداول', 'Average rate on marketable debt'],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [
            new IndicatorDefinition('us.public_debt', 'الدين العام الأمريكي الإجمالي', 'Total public debt outstanding', 'دولار', 'D', [new Range(0, 1e15, 'reject')]),
            new IndicatorDefinition('us.debt_held_public', 'الدين المملوك للجمهور', 'Debt held by the public', 'دولار', 'D', [new Range(0, 1e15, 'reject')]),
            new IndicatorDefinition('us.debt_intragov', 'الدين بين الجهات الحكومية', 'Intragovernmental holdings', 'دولار', 'D', [new Range(0, 1e15, 'reject')]),
            new IndicatorDefinition('us.outlays_total', 'إجمالي إنفاق الحكومة الفدرالية الشهري', 'Total federal outlays (monthly)', 'دولار', 'M', [new Range(0, 1e13, 'reject')]),
            new IndicatorDefinition('us.budget_balance', 'الفائض/العجز الشهري للموازنة الفدرالية (السالب عجز)', 'Federal budget surplus/deficit (monthly)', 'دولار', 'M', [new Range(-1e13, 1e13, 'reject')]),
            new IndicatorDefinition('us.interest_expense_public', 'مصروف فوائد الدين العام المملوك للجمهور (شهري)', 'Interest expense on public issues (monthly)', 'دولار', 'M', [new Range(0, 1e13, 'reject')]),
        ];
        foreach (self::RATES as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, '%', 'M', [new Range(0, 30, 'reject')]);
        }
        foreach (self::RECEIPTS as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, 'دولار', 'M', [new Range(-1e12, 1e13, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'us.treasury_fiscal',
            source: new SourceDefinition(key: 'us_fiscaldata', nameAr: 'وزارة الخزانة الأمريكية (FiscalData)', nameEn: 'U.S. Treasury FiscalData', publisher: 'U.S. Treasury', url: 'https://fiscaldata.treasury.gov', license: 'عام (Public domain)'),
            nameAr: 'مالية الحكومة الأمريكية: الدين والفائدة والإيرادات',
            nameEn: 'US government finance: debt, interest, receipts',
            section: 'markets',
            intervalMinutes: 12 * 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    /** @return list<array<string, mixed>> */
    private function pages(string $endpoint, array $query): array
    {
        $rows = [];
        $page = 1;
        do {
            $res = $this->http()->timeout(120)->retry(3, 5000)->get(self::BASE.$endpoint, $query + ['page[size]' => 10000, 'page[number]' => $page])->throw()->json();
            if (! isset($res['data']) || ! is_array($res['data'])) {
                throw new RuntimeException("FiscalData {$endpoint}: تغيّر شكل الاستجابة.");
            }
            array_push($rows, ...$res['data']);
            $pages = (int) ($res['meta']['total-pages'] ?? 1);
        } while ($page++ < $pages);

        return $rows;
    }

    public function fetch(): RawPayload
    {
        $out = [];
        foreach ($this->pages('v2/accounting/od/debt_to_penny', ['filter' => 'record_date:gte:1993-04-01', 'sort' => 'record_date', 'fields' => 'record_date,tot_pub_debt_out_amt,debt_held_public_amt,intragov_hold_amt']) as $r) {
            foreach (['tot_pub_debt_out_amt' => 'us.public_debt', 'debt_held_public_amt' => 'us.debt_held_public', 'intragov_hold_amt' => 'us.debt_intragov'] as $field => $key) {
                if (is_numeric($r[$field] ?? null)) {
                    $out[] = [$key, $r['record_date'], (float) $r[$field]];
                }
            }
        }
        $latestMonthRow = [];
        foreach ($this->pages('v1/accounting/mts/mts_table_1', ['filter' => 'record_date:gte:2015-01-01', 'sort' => 'record_date', 'fields' => 'record_date,classification_desc,current_month_gross_outly_amt,current_month_dfct_sur_amt,print_order_nbr']) as $r) {
            $month = date('F', strtotime($r['record_date']));
            if (($r['classification_desc'] ?? '') === $month && (! isset($latestMonthRow[$r['record_date']]) || (int) $r['print_order_nbr'] > (int) $latestMonthRow[$r['record_date']]['print_order_nbr'])) {
                $latestMonthRow[$r['record_date']] = $r;
            }
        }
        foreach ($latestMonthRow as $date => $r) {
            if (is_numeric($r['current_month_gross_outly_amt'] ?? null)) {
                $out[] = ['us.outlays_total', substr($date, 0, 7), (float) $r['current_month_gross_outly_amt']];
            }
            if (is_numeric($r['current_month_dfct_sur_amt'] ?? null)) {
                $out[] = ['us.budget_balance', substr($date, 0, 7), (float) $r['current_month_dfct_sur_amt']];
            }
        }
        $interest = [];
        foreach ($this->pages('v2/accounting/od/interest_expense', ['filter' => 'record_date:gte:2001-01-01,expense_catg_desc:eq:INTEREST EXPENSE ON PUBLIC ISSUES', 'sort' => 'record_date', 'fields' => 'record_date,month_expense_amt']) as $r) {
            if (is_numeric($r['month_expense_amt'] ?? null)) {
                $interest[substr($r['record_date'], 0, 7)] = ($interest[substr($r['record_date'], 0, 7)] ?? 0.0) + (float) $r['month_expense_amt'];
            }
        }
        foreach ($interest as $period => $value) {
            $out[] = ['us.interest_expense_public', $period, $value];
        }
        foreach ($this->pages('v2/accounting/od/avg_interest_rates', ['filter' => 'record_date:gte:2015-01-01,security_type_desc:eq:Marketable', 'sort' => 'record_date', 'fields' => 'record_date,security_desc,avg_interest_rate_amt']) as $r) {
            if (isset(self::RATES[$r['security_desc'] ?? '']) && is_numeric($r['avg_interest_rate_amt'] ?? null)) {
                $out[] = [self::RATES[$r['security_desc']][0], substr($r['record_date'], 0, 7), (float) $r['avg_interest_rate_amt']];
            }
        }
        foreach ($this->pages('v1/accounting/mts/mts_table_4', ['filter' => 'record_date:gte:2015-01-01', 'sort' => 'record_date', 'fields' => 'record_date,classification_desc,current_month_gross_rcpt_amt']) as $r) {
            if (isset(self::RECEIPTS[$r['classification_desc'] ?? '']) && is_numeric($r['current_month_gross_rcpt_amt'] ?? null)) {
                $out[] = [self::RECEIPTS[$r['classification_desc']][0], substr($r['record_date'], 0, 7), (float) $r['current_month_gross_rcpt_amt']];
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $period, $value]) {
            yield new ParsedRecord($key, 'USA', $period, $value);
        }
    }
}
