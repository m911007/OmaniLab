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
 * مؤشرات البورصة الأمريكية اليومية من ملفات Cboe العامة: S&P 500 وS&P 100 وراسل 2000 وداو جونز (يُنشر مقسومًا على 100 فيُضرب هنا)،
 * وعائلة مؤشرات التقلب (VIX بأعلاه وأدناه، 9 أيام، 3 و6 أشهر، VVIX، SKEW، ناسداك، النفط، الذهب…) ومؤشرات الاستراتيجيات. الإغلاق الرسمي لليوم، يتأخر إلى ما بعد نهاية الجلسة.
 */
final class CboeIndicesConnector extends ApiConnector
{
    private const BASE = 'https://cdn.cboe.com/api/global/us_indices/daily_prices/';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: float, 4: int}> رمز الملف => [مفتاح، عربي، إنجليزي، مضاعف، حد أعلى]؛ القيمة المعتمدة هي الإغلاق (آخر عمود) */
    private const FILES = [
        'SPX' => ['us.sp500', 'مؤشر S&P 500', 'S&P 500 index', 1.0, 100000],
        'DJX' => ['us.djia', 'مؤشر داو جونز الصناعي', 'Dow Jones Industrial Average', 100.0, 1000000],
        'VIX' => ['us.vix', 'مؤشر التقلب VIX', 'CBOE Volatility Index (VIX)', 1.0, 200],
        'OEX' => ['us.sp100', 'مؤشر S&P 100', 'S&P 100 index', 1.0, 100000],
        'RUT' => ['us.russell2000', 'مؤشر راسل 2000 (الشركات الصغيرة)', 'Russell 2000 index', 1.0, 100000],
        'XSP' => ['us.xsp', 'مؤشر S&P 500 المصغّر (XSP، عُشر المؤشر)', 'Mini-SPX (XSP)', 1.0, 100000],
        'VIX9D' => ['us.vix9d', 'تقلب 9 أيام VIX9D', 'Cboe 9-Day Volatility', 1.0, 200],
        'VIX3M' => ['us.vix3m', 'تقلب 3 أشهر VIX3M', 'Cboe 3-Month Volatility', 1.0, 200],
        'VIX6M' => ['us.vix6m', 'تقلب 6 أشهر VIX6M', 'Cboe 6-Month Volatility', 1.0, 200],
        'VVIX' => ['us.vvix', 'تقلب التقلب VVIX', 'VVIX (volatility of VIX)', 1.0, 500],
        'SKEW' => ['us.skew', 'مؤشر مخاطر الذيل SKEW', 'Cboe SKEW index', 1.0, 300],
        'VXN' => ['us.vxn', 'تقلب ناسداك 100 VXN', 'Nasdaq-100 Volatility (VXN)', 1.0, 200],
        'RVX' => ['us.rvx', 'تقلب راسل 2000 RVX', 'Russell 2000 Volatility (RVX)', 1.0, 200],
        'OVX' => ['us.ovx', 'تقلب النفط OVX', 'Crude Oil Volatility (OVX)', 1.0, 500],
        'GVZ' => ['us.gvz', 'تقلب الذهب GVZ', 'Gold Volatility (GVZ)', 1.0, 200],
        'VXEEM' => ['us.vxeem', 'تقلب الأسواق الناشئة VXEEM', 'Emerging Markets Volatility (VXEEM)', 1.0, 200],
        'VXTLT' => ['us.vxtlt', 'تقلب سندات الخزانة الطويلة VXTLT', '20+ Year Treasury Bond Volatility', 1.0, 200],
        'COR3M' => ['us.cor3m', 'الارتباط الضمني 3 أشهر COR3M', '3-Month Implied Correlation', 1.0, 200],
        'BXM' => ['us.bxm', 'مؤشر البيع المغطى BXM', 'Cboe S&P 500 BuyWrite (BXM)', 1.0, 100000],
        'PUT' => ['us.putwrite', 'مؤشر بيع الخيارات PUT', 'Cboe S&P 500 PutWrite (PUT)', 1.0, 100000],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::FILES as [$key, $ar, $en, , $max]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, 'نقطة', 'D', [new Range(0, $max, 'reject')]);
        }
        $indicators[] = new IndicatorDefinition('us.vix_high', 'VIX: أعلى قيمة في اليوم', 'VIX daily high', 'نقطة', 'D', [new Range(0, 200, 'reject')]);
        $indicators[] = new IndicatorDefinition('us.vix_low', 'VIX: أدنى قيمة في اليوم', 'VIX daily low', 'نقطة', 'D', [new Range(0, 200, 'reject')]);

        return new DatasetDefinition(
            key: 'us.cboe_indices',
            source: new SourceDefinition(key: 'cboe', nameAr: 'Cboe (مؤشرات البورصة الأمريكية)', nameEn: 'Cboe Global Markets (index data)', publisher: 'Cboe', url: 'https://www.cboe.com/us/indices', license: 'بيانات عامة للعرض'),
            nameAr: 'البورصة الأمريكية: S&P 500 وداو جونز وVIX (يومي)',
            nameEn: 'US equities: S&P 500, Dow Jones, VIX (daily)',
            section: 'markets',
            intervalMinutes: 60,
            indicators: $indicators,
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    public function fetch(): RawPayload
    {
        $out = [];
        foreach (self::FILES as $file => [$key, , , $mult]) {
            $csv = $this->httpAccepting('text/csv')->timeout(60)->retry(3, 3000)->get(self::BASE.$file.'_History.csv')->throw()->body();
            $lines = preg_split('/\r?\n/', trim($csv));
            if (! isset($lines[0]) || ! str_starts_with(strtoupper($lines[0]), 'DATE')) {
                throw new RuntimeException("Cboe {$file}: تغيّر شكل الملف.");
            }
            foreach (array_slice($lines, 1) as $line) {
                $f = str_getcsv($line);
                $col = count($f) >= 5 ? 4 : 1;
                if (count($f) > $col && preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $f[0], $m) && is_numeric($f[$col])) {
                    $row = [$key, "{$m[3]}-{$m[1]}-{$m[2]}", (float) $f[$col] * $mult];
                    $out[] = $row;
                    if ($file === 'VIX' && count($f) >= 4 && is_numeric($f[2]) && is_numeric($f[3])) {
                        $out[] = ['us.vix_high', $row[1], (float) $f[2]];
                        $out[] = ['us.vix_low', $row[1], (float) $f[3]];
                    }
                }
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('USA', 'United States', 'country', 'الولايات المتحدة');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $day, $value]) {
            yield new ParsedRecord($key, 'USA', $day, $value);
        }
    }
}
