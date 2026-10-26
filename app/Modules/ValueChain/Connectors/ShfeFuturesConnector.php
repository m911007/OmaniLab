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
use App\Core\Validation\Rules\Range;
use Illuminate\Support\Facades\DB;

/**
 * تسوية العقد الرئيسي اليومية في بورصة شنغهاي للعقود الآجلة (SHFE): حديد التسليح rb، واللفائف المدرفلة hc، والنحاس cu، والألمنيوم al (يوان/طن).
 * العقد الرئيسي = الأعلى في المراكز المفتوحة لذلك اليوم. ملف «kx» اليومي العام على موقع البورصة.
 *
 * البورصة تحتفظ بحقوق بيانات السوق؛ فُعِّل بقرار المالك بعد مراجعة الترخيص، ويُعرض مع وسم المصدر ووتيرة التحديث اليومية.
 */
final class ShfeFuturesConnector extends ApiConnector
{
    private const URL = 'https://www.shfe.com.cn/data/tradedata/future/dailydata/kx%s.dat';

    private const DAYS = 20;

    /** التحميل الأول: سنتان من الأيام لتكفي لتحليل التأخر والتقلب. */
    private const BACKFILL_DAYS = 730;

    /** @var array<string, array{0: string, 1: string}> */
    public const PRODUCTS = ['rb' => ['حديد التسليح', 'Rebar'], 'hc' => ['لفائف الصلب المدرفلة على الساخن', 'Hot-rolled coil'], 'cu' => ['النحاس', 'Copper'], 'al' => ['الألمنيوم', 'Aluminium']];

    public function definition(): DatasetDefinition
    {
        $ind = [];
        foreach (self::PRODUCTS as $p => [$ar, $en]) {
            $ind[] = new IndicatorDefinition("shfe.{$p}_main", "عقود {$ar} الآجلة في شنغهاي (العقد الرئيسي)", "SHFE {$en} futures, main contract settle", 'يوان/طن', 'D', [new Range(100, 500_000, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'shfe.futures',
            source: new SourceDefinition(key: 'shfe', nameAr: 'بورصة شنغهاي للعقود الآجلة', nameEn: 'Shanghai Futures Exchange', publisher: 'SHFE', url: 'https://www.shfe.com.cn', license: 'بيانات سوق البورصة — مفعّلة بقرار المالك'),
            nameAr: 'عقود شنغهاي الآجلة: حديد التسليح واللفائف والنحاس والألمنيوم (يومي)',
            nameEn: 'SHFE futures: rebar, HRC, copper, aluminium (daily)',
            section: 'markets',
            intervalMinutes: 12 * 60,
            indicators: $ind,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        $d = new \DateTimeImmutable('today', new \DateTimeZone('Asia/Shanghai'));
        $days = $this->storedDays() >= 300 ? self::DAYS : self::BACKFILL_DAYS;
        for ($i = 0; $i < $days; $i++) {
            $day = $d->modify("-{$i} day");
            if ((int) $day->format('N') >= 6) {
                continue;
            }
            $res = $this->http()->timeout(30)->get(sprintf(self::URL, $day->format('Ymd')));
            if (! $res->successful() || ! is_array($res->json('o_curinstrument'))) {
                continue; // عطلة أو يوم بلا تداول
            }
            $out[$day->format('Y-m-d')] = $this->mainSettles($res->json('o_curinstrument'));
            usleep(400_000);
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    private function storedDays(): int
    {
        try {
            return DB::table('core.observations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->where('i.key', 'shfe.rb_main')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, float>
     */
    public function mainSettles(array $rows): array
    {
        $best = [];
        foreach ($rows as $r) {
            $p = trim((string) ($r['PRODUCTGROUPID'] ?? ''));
            $settle = $r['SETTLEMENTPRICE'] ?? null;
            if (! isset(self::PRODUCTS[$p]) || ! is_numeric($settle) || ! ctype_digit((string) ($r['DELIVERYMONTH'] ?? ''))) {
                continue;
            }
            $oi = (float) ($r['OPENINTEREST'] ?? 0);
            if (! isset($best[$p]) || $oi > $best[$p][0]) {
                $best[$p] = [$oi, (float) $settle];
            }
        }

        return array_map(fn ($b) => $b[1], $best);
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('CHN', 'China', 'country', 'الصين');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as $date => $prices) {
            foreach ($prices as $p => $v) {
                yield new ParsedRecord("shfe.{$p}_main", 'CHN', (string) $date, (float) $v);
            }
        }
    }
}
