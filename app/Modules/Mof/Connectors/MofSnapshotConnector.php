<?php

namespace App\Modules\Mof\Connectors;

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
 * لقطة يدوية موثّقة لبيانات مالية عُمانية لا واجهة آلية لها: الحساب الختامي للدولة (وزارة المالية، 2012–2025) حسب وجه الصرف،
 * وقسائم السندات السيادية الدولية. الملف `database/data/mof/final_account.json` يُحدَّث يدويًا كل سنة (الجلب يقرأ الملف فقط ويُعلَم بتغيّره).
 * القيم مأخوذة من مشروع MTCIT السابق الذي استخرجها من ملفات xlsx المنشورة في opendata.gov.om (انظر ملاحظة اللقطة).
 */
final class MofSnapshotConnector extends ApiConnector
{
    private const FILES = [
        'database/data/mof/final_account.json',
        'database/data/mof/sovereign_issues.json',
        'database/data/mof/by_entity.json',
    ];

    /** مؤشرات التفصيل حسب الجهة (الكيان وحدة حكومية) بالمليون ريال: جدول الملف ← [مفتاح، عربي، إنجليزي] */
    private const UNIT_TABLES = [
        'rev' => ['mof.unit_rev', 'الإيرادات الجارية حسب الجهة', 'Current revenue by government unit'],
        'cur' => ['mof.unit_exp_current', 'المصروفات الجارية حسب الجهة', 'Current expenditure by government unit'],
        'dev' => ['mof.unit_exp_dev', 'المصروفات الإنمائية حسب الجهة', 'Development expenditure by government unit'],
    ];

    private const NAMES = [
        'mof.exp_defense' => ['الإنفاق على الدفاع والأمن', 'Government spending: defence and security'],
        'mof.exp_civil' => ['الإنفاق الجاري للوزارات المدنية', 'Government spending: civil ministries (current)'],
        'mof.exp_other_current' => ['بقية المصروفات الجارية (خدمة الدين وغيرها)', 'Government spending: other current (incl. debt service)'],
        'mof.exp_investment' => ['المصروفات الاستثمارية والإنمائية', 'Government spending: investment and development'],
        'mof.exp_contributions' => ['المساهمات والدعم', 'Government spending: contributions and subsidies'],
        'mof.rev_oil' => ['إيرادات النفط', 'Government revenue: oil'],
        'mof.rev_gas' => ['إيرادات الغاز', 'Government revenue: gas'],
        'mof.rev_other' => ['إيرادات أخرى (جارية ورأسمالية)', 'Government revenue: other (current and capital)'],
        'mof.rev_total' => ['إجمالي الإيرادات العامة الفعلية', 'Total actual government revenue'],
        'mof.balance' => ['الفائض/العجز الفعلي', 'Actual budget surplus/(deficit)'],
        'mof.bud_rev_total' => ['الإيرادات المعتمدة في الميزانية', 'Approved budget revenue'],
        'mof.bud_exp_total' => ['الإنفاق المعتمد في الميزانية', 'Approved budget expenditure'],
        'mof.bud_balance' => ['الفائض/العجز المعتمد في الميزانية', 'Approved budget balance'],
        'mof.fin_external_net' => ['صافي الاقتراض الخارجي (تمويل العجز)', 'Net external borrowing (deficit financing)'],
        'mof.fin_domestic_net' => ['صافي الاقتراض المحلي (تمويل العجز)', 'Net domestic borrowing (deficit financing)'],
        'mof.fin_reserves' => ['التمويل من الاحتياطيات', 'Financing from reserves'],
        'mof.fin_other' => ['مصادر تمويل أخرى (معونات وحسابات حكومية وفائض سابق)', 'Other financing (aid, government accounts, prior surplus)'],
        'mof.exp_total' => ['إجمالي الإنفاق العام الفعلي', 'Total actual government spending'],
        'oman.eurobond_coupon' => ['قسيمة السند السيادي الدولي المُصدَر', 'Coupon of the sovereign international bond issued'],
    ];

    /** مؤشرات تقبل قيمًا سالبة (أرصدة وتمويل) */
    private const SIGNED = ['mof.balance', 'mof.bud_balance', 'mof.fin_external_net', 'mof.fin_domestic_net', 'mof.fin_reserves', 'mof.fin_other'];

    /** @return array<string, array<string, mixed>> */
    private function load(): array
    {
        $out = [];
        foreach (self::FILES as $f) {
            $path = base_path($f);
            if (! is_file($path)) {
                throw new RuntimeException("لقطة مفقودة: {$f}");
            }
            $out[$f] = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        }

        return $out;
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::NAMES as $key => [$ar, $en]) {
            $isRate = str_starts_with($key, 'oman.');
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $isRate ? '%' : 'مليار ريال عُماني', 'A', [new Range(in_array($key, self::SIGNED, true) ? -50 : 0, $isRate ? 30 : 100, 'reject')]);
        }

        foreach (self::UNIT_TABLES as [$key, $ar, $en]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, 'مليون ريال عُماني', 'A', [new Range(-1000, 20000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'oman.fiscal_snapshot',
            source: new SourceDefinition(key: 'mof_oman', nameAr: 'وزارة المالية — سلطنة عُمان (الحساب الختامي للدولة)', nameEn: 'Ministry of Finance, Oman (State Final Account)', publisher: 'Ministry of Finance, Oman', url: 'https://www.opendata.gov.om', license: 'بيانات حكومية منشورة (opendata.gov.om)', reliability: 'secondary'),
            nameAr: 'عُمان: الحساب الختامي للدولة (الإيرادات والإنفاق) وإصدارات السندات الدولية',
            nameEn: 'Oman: final account by spending category and sovereign issues',
            section: 'fiscal',
            intervalMinutes: 7 * 24 * 60,
            indicators: $indicators,
            reliability: 'secondary',
        );
    }

    public function probe(): ProbeResult
    {
        $token = '';
        foreach (self::FILES as $f) {
            $token .= hash_file('sha256', base_path($f)) ?: '';
        }

        return new ProbeResult(token: hash('sha256', $token), version: 'الحساب الختامي للدولة (وزارة المالية)');
    }

    public function fetch(): RawPayload
    {
        return new RawPayload(json_encode($this->load(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('OMN', 'Oman', 'country', 'سلطنة عُمان');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $snap) {
            if (isset($snap['tables'])) {
                foreach ($snap['entities'] as $code => $name) {
                    yield new ParsedEntity($code, $name, 'other', $name);
                }
                foreach (self::UNIT_TABLES as $table => [$key]) {
                    foreach ($snap['tables'][$table]['series'] ?? [] as $code => $values) {
                        foreach ($snap['tables'][$table]['years'] as $i => $year) {
                            if (isset($values[$i]) && is_numeric($values[$i])) {
                                yield new ParsedRecord($key, $code, (string) $year, (float) $values[$i]);
                            }
                        }
                    }
                }

                continue;
            }
            foreach ($snap['series'] as $key => $values) {
                foreach ($snap['years'] as $i => $year) {
                    if (isset($values[$i]) && is_numeric($values[$i])) {
                        yield new ParsedRecord($key, 'OMN', (string) $year, (float) $values[$i]);
                    }
                }
            }
        }
    }
}
