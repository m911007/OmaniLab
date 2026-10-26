<?php

namespace App\Modules\Climate\Connectors;

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
 * بوابة معرفة تغير المناخ للبنك الدولي (CCKP، واجهة عامة بلا مفتاح): متوسط الحرارة السنوي والأمطار السنوية لكل دولة خليجية تاريخيًا منذ 1901 (CRU TS)،
 * وإسقاطات نماذج CMIP6 حتى 2100 (وسيط النماذج) تحت ثلاثة مسارات انبعاثات: SSP1-2.6 (خفض شديد) وSSP2-4.5 (وسط) وSSP5-8.5 (مرتفع).
 * الإسقاطات تقديرات نماذج لا أرصاد، وتُخزَّن بسنواتها المستقبلية.
 */
final class WorldBankClimateConnector extends ApiConnector
{
    private const BASE = 'https://cckpapi.worldbank.org/cckp/v1/';

    private const HIST = 'cru-x0.5_timeseries_%s_timeseries_annual_1901-2024_mean_historical_cru_ts4.09_mean';

    private const PROJ = 'cmip6-x0.25_timeseries_%s_timeseries_annual_2015-2100_median_%s_ensemble_all_mean';

    /** @var array<string, array{0: string, 1: string}> */
    private const COUNTRIES = ['OMN' => ['Oman', 'سلطنة عُمان'], 'ARE' => ['United Arab Emirates', 'الإمارات'], 'SAU' => ['Saudi Arabia', 'السعودية'], 'QAT' => ['Qatar', 'قطر'], 'KWT' => ['Kuwait', 'الكويت'], 'BHR' => ['Bahrain', 'البحرين']];

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: float, 5: float}> var => [وحدة، عربي، إنجليزي، لاحقة المفتاح، أدنى، أعلى] */
    private const VARS = [
        'tas' => ['°م', 'متوسط الحرارة السنوي', 'Mean annual temperature', 'tas', 0, 50],
        'pr' => ['ملم', 'الأمطار السنوية', 'Annual precipitation', 'pr', 0, 3000],
    ];

    private const SCENARIOS = ['ssp126' => 'SSP1-2.6 (خفض شديد للانبعاثات)', 'ssp245' => 'SSP2-4.5 (مسار وسط)', 'ssp585' => 'SSP5-8.5 (انبعاثات مرتفعة)'];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::VARS as [$unit, $ar, $en, $k, $lo, $hi]) {
            $rules = [new Range($lo, $hi, 'reject')];
            $indicators[] = new IndicatorDefinition("climate.hist_{$k}", "{$ar} (رصد تاريخي)", "{$en} (historical)", $unit, 'A', $rules);
            foreach (self::SCENARIOS as $s => $label) {
                if ($k === 'pr' && $s === 'ssp126') {
                    continue;
                }
                $indicators[] = new IndicatorDefinition("climate.proj_{$k}_{$s}", "{$ar}: إسقاط {$label}", "{$en}: projection {$s}", $unit, 'A', $rules);
            }
        }

        return new DatasetDefinition(
            key: 'climate.wb_cckp',
            source: new SourceDefinition(key: 'wb_cckp', nameAr: 'بوابة معرفة تغير المناخ — البنك الدولي', nameEn: 'World Bank Climate Change Knowledge Portal', publisher: 'World Bank', url: 'https://climateknowledgeportal.worldbank.org', license: 'CC BY 4.0'),
            nameAr: 'مناخ دول الخليج: التاريخ والإسقاطات حتى 2100',
            nameEn: 'GCC climate: history and projections to 2100',
            section: 'climate',
            intervalMinutes: 30 * 24 * 60,
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
        $n = 0;
        foreach (array_keys(self::COUNTRIES) as $iso) {
            foreach (self::VARS as $var => [, , , $k]) {
                $out = [...$out, ...$this->series(sprintf(self::HIST, $var), $iso, "climate.hist_{$k}")];
                foreach (array_keys(self::SCENARIOS) as $s) {
                    if ($var === 'pr' && $s === 'ssp126') {
                        continue;
                    }
                    $out = [...$out, ...$this->series(sprintf(self::PROJ, $var, $s), $iso, "climate.proj_{$k}_{$s}")];
                    $n++;
                }
            }
        }
        if ($n === 0 || $out === []) {
            throw new RuntimeException('CCKP: لا نقاط.');
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: float}> */
    private function series(string $path, string $iso, string $key): array
    {
        usleep(300_000);
        $res = $this->http()->timeout(90)->retry(3, 3000)->get(self::BASE.$path.'/'.$iso, ['_format' => 'json'])->throw()->json();
        $data = $res['data'][$iso] ?? null;
        if (! is_array($data) || $data === []) {
            throw new RuntimeException("CCKP {$key} {$iso}: لا بيانات.");
        }
        $rows = [];
        foreach ($data as $period => $value) {
            if (is_numeric($value) && preg_match('/^(\d{4})-/', (string) $period, $m)) {
                $rows[] = [$key, $iso, $m[1], (float) $value];
            }
        }

        return $rows;
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (self::COUNTRIES as $iso => [$en, $ar]) {
            yield new ParsedEntity($iso, $en, 'country', $ar);
        }
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $iso, $year, $value]) {
            yield new ParsedRecord($key, $iso, $year, $value);
        }
    }
}
