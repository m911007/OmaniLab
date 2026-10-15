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
use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * أسعار السلع العالمية الشهرية (IMF Primary Commodity Prices): النفط (برنت، دبي، غرب تكساس)، الغاز، الفحم، المعادن،
 * الغذاء والأسمدة، ومؤشرات الأسعار المجمَّعة. كلها بالدولار، متوسط شهري، كيان "العالم" (WLD).
 * الواجهة الجديدة لصندوق النقد (api.imf.org SDMX 2.1): ترتيب الأبعاد COUNTRY.INDICATOR.TRANSFORMATION.FREQUENCY، والفترة 2026-M09.
 * الفحص: UPDATE_DATE في ترويسة المجموعة (يتغير مع كل إصدار شهري).
 */
final class ImfCommodityPricesConnector extends ApiConnector
{
    private const BASE = 'https://api.imf.org/external/sdmx/2.1/data/PCPS/';

    private const FROM = 1990;

    /**
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: int|float}>
     *                                                                                     رمز IMF => [عربي، إنجليزي، وحدة، تحويل (USD أو INDEX)، حد أعلى]
     */
    private const SERIES = [
        'POILBRE' => ['خام برنت', 'Brent crude oil', 'دولار/برميل', 'USD', 500],
        'POILDUB' => ['خام دبي', 'Dubai crude oil', 'دولار/برميل', 'USD', 500],
        'POILWTI' => ['خام غرب تكساس', 'WTI crude oil', 'دولار/برميل', 'USD', 500],
        'PNGASEU' => ['الغاز الطبيعي (أوروبا)', 'Natural gas, Europe', 'دولار/مليون وحدة حرارية', 'USD', 300],
        'PNGASJP' => ['الغاز المسال (آسيا)', 'LNG, Asia', 'دولار/مليون وحدة حرارية', 'USD', 300],
        'PNGASUS' => ['الغاز الطبيعي (هنري هب)', 'Natural gas, US Henry Hub', 'دولار/مليون وحدة حرارية', 'USD', 100],
        'PPROPANE' => ['البروبان', 'Propane', 'دولار/طن', 'USD', 5_000],
        'PCOALAU' => ['الفحم (أستراليا)', 'Coal, Australia', 'دولار/طن', 'USD', 1_000],
        'PGOLD' => ['الذهب', 'Gold', 'دولار/أونصة', 'USD', 20_000],
        'PSILVER' => ['الفضة', 'Silver', 'دولار/أونصة', 'USD', 500],
        'PCOPP' => ['النحاس', 'Copper', 'دولار/طن', 'USD', 50_000],
        'PALUM' => ['الألمنيوم', 'Aluminium', 'دولار/طن', 'USD', 20_000],
        'PZINC' => ['الزنك', 'Zinc', 'دولار/طن', 'USD', 20_000],
        'PNICK' => ['النيكل', 'Nickel', 'دولار/طن', 'USD', 100_000],
        'PIORECR' => ['خام الحديد', 'Iron ore', 'دولار/طن', 'USD', 1_000],
        'PWHEAMT' => ['القمح', 'Wheat', 'دولار/طن', 'USD', 2_000],
        'PMAIZMT' => ['الذرة', 'Maize', 'دولار/طن', 'USD', 2_000],
        'PRICENPQ' => ['الأرز', 'Rice', 'دولار/طن', 'USD', 3_000],
        'PSUGAISA' => ['السكر', 'Sugar', 'سنت/رطل', 'USD', 200],
        'PSOYB' => ['فول الصويا', 'Soybeans', 'دولار/طن', 'USD', 3_000],
        'PUREA' => ['اليوريا (سماد)', 'Urea (fertiliser)', 'دولار/طن', 'USD', 3_000],
        'PALLFNF' => ['مؤشر أسعار السلع (غير الوقود)', 'Non-fuel commodities price index', 'مؤشر 2016=100', 'INDEX', 1_000],
        'PAGRI' => ['مؤشر أسعار الزراعة', 'Agricultural raw materials index', 'مؤشر 2016=100', 'INDEX', 1_000],
        'PALLMETA' => ['مؤشر أسعار المعادن', 'Metals price index', 'مؤشر 2016=100', 'INDEX', 1_000],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as $code => [$ar, $en, $unit, , $max]) {
            $indicators[] = new IndicatorDefinition('imf.pcps.'.strtolower($code), $ar, $en, $unit, 'M', [new Range(0, $max, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'imf.commodities',
            source: new SourceDefinition(
                key: 'imf',
                nameAr: 'صندوق النقد الدولي',
                nameEn: 'International Monetary Fund',
                publisher: 'IMF Research Department',
                url: 'https://www.imf.org/en/Research/commodity-prices',
                license: 'IMF Data Terms',
            ),
            nameAr: 'أسعار السلع العالمية (النفط والغاز والمعادن والغذاء)',
            nameEn: 'Global commodity prices (oil, gas, metals, food)',
            section: 'markets',
            intervalMinutes: 24 * 60,
            indicators: $indicators,
        );
    }

    /** @return list<array<string, string>> */
    private function query(string $key, string $from): array
    {
        $csv = $this->httpAccepting('application/vnd.sdmx.data+csv')->timeout(120)
            ->get(self::BASE.$key, ['startPeriod' => $from])->throw()->body();
        if (! str_starts_with($csv, 'DATAFLOW,')) {
            throw new RuntimeException('IMF PCPS: تغيّر شكل الاستجابة.');
        }

        return $this->rows($csv);
    }

    /** @return list<array<string, string>> */
    private function rows(string $csv): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);
        $header = fgetcsv($handle, 0, ',', '"', '') ?: [];
        $rows = [];
        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($row) === count($header)) {
                $rows[] = array_combine($header, $row);
            }
        }
        fclose($handle);

        return $rows;
    }

    public function probe(): ProbeResult
    {
        $rows = array_filter($this->query('G001.POILBRE.USD.M', (string) ((int) date('Y') - 1)), fn (array $r) => $r['TIME_PERIOD'] !== '');
        $latest = end($rows);
        if (! $latest) {
            throw new RuntimeException('IMF PCPS: لا نقاط في الفحص.');
        }
        $updated = $latest['UPDATE_DATE'] ?: null;

        return new ProbeResult(
            token: $latest['TIME_PERIOD'].'|'.$updated,
            version: 'PCPS '.$latest['TIME_PERIOD'],
            publishedAt: $updated ? CarbonImmutable::parse($updated)->utc() : null,
        );
    }

    public function fetch(): RawPayload
    {
        $out = [];
        foreach (['USD', 'INDEX'] as $transformation) {
            $codes = array_keys(array_filter(self::SERIES, fn (array $s) => $s[3] === $transformation));
            foreach ($this->query('G001.'.implode('+', $codes).'.'.$transformation.'.M', (string) self::FROM) as $r) {
                if ($r['TIME_PERIOD'] !== '' && $r['OBS_VALUE'] !== '') {
                    $out[] = [$r['INDICATOR'], $r['TIME_PERIOD'], $r['OBS_VALUE']];
                }
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('WLD', 'World', 'group', 'العالم');

        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$code, $period, $value]) {
            if (isset(self::SERIES[$code]) && preg_match('/^(\d{4})-M(\d{2})$/', $period, $m) && is_numeric($value)) {
                yield new ParsedRecord('imf.pcps.'.strtolower($code), 'WLD', "{$m[1]}-{$m[2]}", (float) $value);
            }
        }
    }
}
