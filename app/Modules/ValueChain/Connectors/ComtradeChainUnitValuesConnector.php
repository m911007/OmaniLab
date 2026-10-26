<?php

namespace App\Modules\ValueChain\Connectors;

use App\Core\Ingestion\Connectors\ApiConnector;
use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Ingestion\Data\IndicatorDefinition;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ProbeResult;
use App\Core\Ingestion\Data\RawPayload;
use App\Core\Ingestion\Data\SourceDefinition;
use App\Core\Support\ApiKeys;
use App\Core\Validation\Rules\Range;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;

/**
 * قيم الوحدة الجمركية الشهرية (دولار/طن) لبنود HS6 في سلاسل القيمة، من UN Comtrade (الواجهة العامة، فترة واحدة لكل طلب):
 *   - واردات دول الخليج الست (سعر الوصول CIF تقريبًا) ← ctm.uv_m.{hs6}
 *   - صادرات الصين والهند وتركيا (سعر المنشأ FOB تقريبًا) ← ctm.uv_x.{hs6}
 * قبل التخزين تُفلتر القيم: وزن صافٍ معروف ≥ 20 طنًا، ثم تُستبعد القيم الشاذة داخل كل سلسلة (أبعد من 3 أضعاف وسيطها أو 4 انحرافات مطلقة وسيطة
 * على اللوغاريتم)، ثم عبر الأسواق: أبعد من 3 أضعاف الوسيط المشترك للبند في كل الدول (شحنات صغيرة متخصصة تحت رمز عام، مثل خردة أو إسمنت خاص).
 * الكمية (طن) تُخزَّن بجانبها، ونسبة الكمية التقديرية تُخزَّن مؤشرًا للموثوقية.
 */
final class ComtradeChainUnitValuesConnector extends ApiConnector
{
    private const URL = 'https://comtradeapi.un.org/public/v1/preview/C/M/HS';

    private const MONTHS = 36;

    /** بعد التحميل الأول تكفي آخر 8 أشهر (المراجعات والأشهر المتأخرة)، توفيرًا لحصة الواجهة العامة. */
    private const MONTHS_INCREMENTAL = 8;

    /** @var array<string, string> بنود HS6 => الاسم العربي */
    public const HS6 = [
        '260112' => 'خام الحديد المكتّل (كريات)', '720310' => 'الحديد المختزل مباشرة (DRI)', '720449' => 'خردة الحديد والصلب', '720711' => 'بليت صلب كربوني',
        '721420' => 'حديد التسليح (قضبان مضلعة)', '854511' => 'أقطاب الجرافيت للأفران', '720230' => 'سبائك الحديد والسيليكون والمنغنيز',
        '252310' => 'الكلنكر', '252329' => 'الإسمنت البورتلاندي', '252100' => 'حجر جيري (للصهر والإسمنت)', '252010' => 'الجبس', '251710' => 'حصى وحجر مكسر (ركام)', '382440' => 'إضافات الخرسانة',
        '740311' => 'كاثودات النحاس', '740811' => 'قضبان أسلاك النحاس (≥6 ملم)', '390410' => 'بولي فينيل كلوريد (PVC)', '390110' => 'بولي إيثيلين منخفض الكثافة', '721720' => 'أسلاك صلب مجلفنة', '854449' => 'كابلات معزولة ≤1000 فولت',
    ];

    /** @var array<int, string> رمز M49 => ISO3 */
    public const IMPORTERS = [512 => 'OMN', 784 => 'ARE', 682 => 'SAU', 634 => 'QAT', 414 => 'KWT', 48 => 'BHR'];

    /** @var array<int, string> */
    public const EXPORTERS = [156 => 'CHN', 699 => 'IND', 792 => 'TUR'];

    private const NAMES = ['OMN' => ['Oman', 'سلطنة عُمان'], 'ARE' => ['United Arab Emirates', 'الإمارات'], 'SAU' => ['Saudi Arabia', 'السعودية'], 'QAT' => ['Qatar', 'قطر'], 'KWT' => ['Kuwait', 'الكويت'], 'BHR' => ['Bahrain', 'البحرين'], 'CHN' => ['China', 'الصين'], 'IND' => ['India', 'الهند'], 'TUR' => ['Turkey', 'تركيا']];

    public function definition(): DatasetDefinition
    {
        $ind = [];
        foreach (self::HS6 as $hs => $ar) {
            $ind[] = new IndicatorDefinition("ctm.uv_m.{$hs}", "قيمة وحدة الاستيراد: {$ar} (HS {$hs})", "Import unit value HS {$hs}", 'دولار/طن', 'M', [new Range(1, 1e6, 'reject')]);
            $ind[] = new IndicatorDefinition("ctm.uv_x.{$hs}", "قيمة وحدة التصدير: {$ar} (HS {$hs})", "Export unit value HS {$hs}", 'دولار/طن', 'M', [new Range(1, 1e6, 'reject')]);
            $ind[] = new IndicatorDefinition("ctm.qty_m.{$hs}", "كمية الاستيراد: {$ar} (HS {$hs})", "Import quantity HS {$hs}", 'طن', 'M', [new Range(0, 1e9, 'reject')]);
            $ind[] = new IndicatorDefinition("ctm.qty_x.{$hs}", "كمية التصدير: {$ar} (HS {$hs})", "Export quantity HS {$hs}", 'طن', 'M', [new Range(0, 1e9, 'reject')]);
            $ind[] = new IndicatorDefinition("ctm.est_m.{$hs}", "استيراد بكمية تقديرية: {$ar}", "Import quantity estimated flag HS {$hs}", '0/1', 'M', [new Range(0, 1, 'reject')]);
            $ind[] = new IndicatorDefinition("ctm.est_x.{$hs}", "تصدير بكمية تقديرية: {$ar}", "Export quantity estimated flag HS {$hs}", '0/1', 'M', [new Range(0, 1, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'comtrade.chain_unit_values',
            source: new SourceDefinition(key: 'comtrade', nameAr: 'قاعدة الأمم المتحدة لتجارة السلع (UN Comtrade)', nameEn: 'UN Comtrade', publisher: 'United Nations Statistics Division', url: 'https://comtradeplus.un.org', license: 'UN Comtrade — الاستخدام العام مع الإشارة إلى المصدر'),
            nameAr: 'قيم الوحدة الجمركية الشهرية لبنود سلاسل القيمة (الخليج، الصين، الهند، تركيا)',
            nameEn: 'Monthly customs unit values for value-chain HS6 lines',
            section: 'trade',
            intervalMinutes: 7 * 24 * 60,
            indicators: $ind,
        );
    }

    /** 2: فلتر الشواذ عبر الأسواق (يُعيد معالجة الحمولة نفسها). */
    public function revision(): int
    {
        return 2;
    }

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $rows = [];
        $first = true;
        $quotaHit = false;
        foreach ($this->months() as $period) {
            foreach ([['M', self::IMPORTERS], ['X', self::EXPORTERS]] as [$flow, $reporters]) {
                if (! $first) {
                    usleep(1_500_000);
                }
                $first = false;
                $res = $this->http()->withHeaders(ApiKeys::comtradeHeaders())->timeout(90)->retry(3, 5000, fn ($e) => ! ($e instanceof RequestException && $e->response->status() === 403), throw: false)->get(ApiKeys::comtradeUrl(self::URL), [
                    'reporterCode' => implode(',', array_keys($reporters)), 'period' => $period, 'cmdCode' => implode(',', array_keys(self::HS6)), 'flowCode' => $flow, 'partnerCode' => 0,
                ]);
                // الواجهة العامة محدودة الحصة: عند نفادها نحفظ ما جُلب (الأحدث أولًا) ونكمل في التشغيل التالي.
                if ($res->status() === 403 && str_contains($res->body(), 'quota')) {
                    $quotaHit = true;
                    break 2;
                }
                $res->throw();
                foreach ($res->json('data') ?? [] as $r) {
                    if (($r['customsCode'] ?? 'C00') !== 'C00' || (int) ($r['motCode'] ?? 0) !== 0 || (int) ($r['partner2Code'] ?? 0) !== 0) {
                        continue;
                    }
                    $iso = $reporters[(int) ($r['reporterCode'] ?? 0)] ?? null;
                    if ($iso === null) {
                        continue;
                    }
                    $rows[] = [$flow, (string) $r['cmdCode'], $iso, substr((string) $period, 0, 4).'-'.substr((string) $period, 4, 2), (float) ($r['primaryValue'] ?? 0), (float) ($r['netWgt'] ?? 0), (bool) ($r['isNetWgtEstimated'] ?? false)];
                }
            }
        }
        if ($quotaHit) {
            if ($rows === []) {
                throw new \RuntimeException('UN Comtrade: نفدت حصة الواجهة العامة قبل جلب أي شهر؛ يُعاد لاحقًا.');
            }
            logger()->warning('[comtrade] نفدت الحصة؛ حُفظ ما جُلب ('.count($rows).' صفًا) ويُستكمل الباقي في التشغيل التالي.');
        }

        return new RawPayload(json_encode($rows, JSON_THROW_ON_ERROR));
    }

    /** @return list<string> الأشهر المطلوبة بصيغة YYYYMM (الأحدث أولًا؛ Comtrade يتأخر شهرين إلى أربعة) */
    private function months(): array
    {
        $out = [];
        $d = new \DateTimeImmutable('first day of this month');
        $n = $this->historyMonths() >= self::MONTHS - 6 ? self::MONTHS_INCREMENTAL : self::MONTHS;
        for ($i = 1; $i <= $n; $i++) {
            $out[] = $d->modify("-{$i} month")->format('Ym');
        }

        return $out;
    }

    /** عدد الأشهر المخزّنة سابقًا (أي بند)؛ يحدد إن كان التحميل الأول اكتمل. */
    private function historyMonths(): int
    {
        try {
            return (int) DB::table('core.observations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')
                ->where('i.key', 'like', 'ctm.uv_%')->distinct()->count('o.period');
        } catch (\Throwable) {
            return 0;
        }
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (self::NAMES as $iso => [$en, $ar]) {
            yield new ParsedEntity($iso, $en, 'country', $ar);
        }
        foreach ($this->clean(json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR)) as [$flow, $hs, $iso, $period, $uv, $tonnes, $est]) {
            $f = strtolower($flow);
            yield new ParsedRecord("ctm.uv_{$f}.{$hs}", $iso, $period, round($uv, 2));
            yield new ParsedRecord("ctm.qty_{$f}.{$hs}", $iso, $period, round($tonnes, 3));
            yield new ParsedRecord("ctm.est_{$f}.{$hs}", $iso, $period, $est ? 1.0 : 0.0);
        }
    }

    /**
     * فلترة الجودة: وزن ≥ 20 طنًا وقيمة موجبة، ثم استبعاد الشواذ داخل كل سلسلة (تدفق × بند × دولة).
     *
     * @param  list<array{0: string, 1: string, 2: string, 3: string, 4: float, 5: float, 6: bool}>  $rows
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: float, 5: float, 6: bool}>
     */
    public function clean(array $rows): array
    {
        $series = [];
        foreach ($rows as [$flow, $hs, $iso, $period, $value, $kg, $est]) {
            if ($value <= 0 || $kg < 20_000 || ! isset(self::HS6[$hs])) {
                continue;
            }
            $series["{$flow}|{$hs}|{$iso}"][$period] = [$flow, $hs, $iso, $period, $value / $kg * 1000, $kg / 1000, $est];
        }
        $kept = [];
        foreach ($series as $points) {
            $logs = array_map(fn ($p) => log($p[4]), $points);
            $med = $this->median(array_values($logs));
            $mad = $this->median(array_map(fn ($l) => abs($l - $med), array_values($logs)));
            foreach ($points as $k => $p) {
                $dev = abs($logs[$k] - $med);
                if ($dev > log(3) || ($mad > 0 && count($points) >= 6 && $dev > 4 * 1.4826 * $mad)) {
                    continue;
                }
                $kept[] = $p;
            }
        }
        // فلترة عبر الأسواق: الوسيط المشترك للبند (كل الدول والتدفقات والأشهر).
        $byHs = [];
        foreach ($kept as $p) {
            $byHs[$p[1]][] = log($p[4]);
        }
        $hsMedian = array_map(fn ($v) => $this->median($v), $byHs);

        return array_values(array_filter($kept, fn ($p) => count($byHs[$p[1]]) < 6 || abs(log($p[4]) - $hsMedian[$p[1]]) <= log(3)));
    }

    /** @param  list<float>  $v */
    private function median(array $v): float
    {
        sort($v);
        $n = count($v);

        return $n === 0 ? 0.0 : ($n % 2 ? $v[intdiv($n, 2)] : ($v[$n / 2 - 1] + $v[$n / 2]) / 2);
    }
}
