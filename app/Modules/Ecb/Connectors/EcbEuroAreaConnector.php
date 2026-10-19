<?php

namespace App\Modules\Ecb\Connectors;

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
 * منطقة اليورو من البنك المركزي الأوروبي (ECB Data Portal، SDMX بلا مفتاح): أسعار الفائدة الرسمية و€STR ويوريبور، ومنحنى عوائد السندات،
 * والتضخم وبنوده والبطالة والنمو والمعروض النقدي ومؤشر الضغط النظامي وأسعار صرف اليورو المرجعية. التاريخ كاملًا منذ بدايته.
 */
final class EcbEuroAreaConnector extends ApiConnector
{
    private const BASE = 'https://data-api.ecb.europa.eu/service/data/';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: float, 7: float}> مفتاح => [تدفق، مفتاح السلسلة، تردد، وحدة، عربي، إنجليزي، حد أدنى، حد أعلى] */
    private const SERIES = [
        'ecb.mro_rate' => ['FM', 'D.U2.EUR.4F.KR.MRR_FR.LEV', 'D', '%', 'سعر الفائدة الرئيسي للبنك المركزي الأوروبي (عمليات إعادة التمويل)', 'ECB main refinancing rate', -2, 15],
        'ecb.deposit_rate' => ['FM', 'D.U2.EUR.4F.KR.DFR.LEV', 'D', '%', 'سعر فائدة الودائع لدى البنك المركزي الأوروبي', 'ECB deposit facility rate', -2, 15],
        'ecb.lending_rate' => ['FM', 'D.U2.EUR.4F.KR.MLFR.LEV', 'D', '%', 'سعر فائدة الإقراض الهامشي للبنك المركزي الأوروبي', 'ECB marginal lending rate', -2, 15],
        'ecb.estr' => ['EST', 'B.EU000A2X2A25.WT', 'D', '%', 'سعر الفائدة قصير الأجل باليورو (€STR)', 'Euro short-term rate (€STR)', -2, 15],
        'ecb.euribor_3m' => ['FM', 'M.U2.EUR.RT.MM.EURIBOR3MD_.HSTA', 'M', '%', 'يوريبور 3 أشهر', 'Euribor 3-month', -2, 20],
        'ecb.yield_1y' => ['YC', 'B.U2.EUR.4F.G_N_A.SV_C_YM.SR_1Y', 'D', '%', 'عائد سندات منطقة اليورو سنة', 'Euro area govt yield 1y', -3, 20],
        'ecb.yield_2y' => ['YC', 'B.U2.EUR.4F.G_N_A.SV_C_YM.SR_2Y', 'D', '%', 'عائد سندات منطقة اليورو سنتان', 'Euro area govt yield 2y', -3, 20],
        'ecb.yield_5y' => ['YC', 'B.U2.EUR.4F.G_N_A.SV_C_YM.SR_5Y', 'D', '%', 'عائد سندات منطقة اليورو 5 سنوات', 'Euro area govt yield 5y', -3, 20],
        'ecb.yield_10y' => ['YC', 'B.U2.EUR.4F.G_N_A.SV_C_YM.SR_10Y', 'D', '%', 'عائد سندات منطقة اليورو 10 سنوات', 'Euro area govt yield 10y', -3, 20],
        'ecb.yield_30y' => ['YC', 'B.U2.EUR.4F.G_N_A.SV_C_YM.SR_30Y', 'D', '%', 'عائد سندات منطقة اليورو 30 سنة', 'Euro area govt yield 30y', -3, 20],
        'ecb.hicp' => ['ICP', 'M.U2.N.000000.4.ANR', 'M', '%', 'التضخم السنوي في منطقة اليورو (HICP)', 'Euro area HICP inflation, y/y', -10, 50],
        'ecb.hicp_core' => ['ICP', 'M.U2.N.XEF000.4.ANR', 'M', '%', 'التضخم الأساسي في منطقة اليورو (دون الطاقة والغذاء)', 'Euro area core HICP inflation', -10, 50],
        'ecb.hicp_energy' => ['ICP', 'M.U2.N.NRGY00.4.ANR', 'M', '%', 'تضخم الطاقة في منطقة اليورو', 'Euro area energy HICP inflation', -60, 200],
        'ecb.hicp_food' => ['ICP', 'M.U2.N.FOOD00.4.ANR', 'M', '%', 'تضخم الغذاء في منطقة اليورو', 'Euro area food HICP inflation', -20, 60],
        'ecb.hicp_services' => ['ICP', 'M.U2.N.SERV00.4.ANR', 'M', '%', 'تضخم الخدمات في منطقة اليورو', 'Euro area services HICP inflation', -20, 60],
        'ecb.m3_growth' => ['BSI', 'M.U2.Y.V.M30.X.I.U2.2300.Z01.A', 'M', '%', 'نمو المعروض النقدي M3 في منطقة اليورو (سنوي)', 'Euro area M3 growth, y/y', -20, 50],
        'ecb.unemployment' => ['LFSI', 'M.I9.S.UNEHRT.TOTAL0.15_74.T', 'M', '%', 'معدل البطالة في منطقة اليورو', 'Euro area unemployment rate', 0, 40],
        'ecb.gdp_growth' => ['MNA', 'Q.Y.I9.W2.S1.S1.B.B1GQ._Z._Z._Z.EUR.LR.GY', 'Q', '%', 'نمو الناتج المحلي لمنطقة اليورو (سنوي)', 'Euro area real GDP growth, y/y', -25, 25],
        'ecb.ciss' => ['CISS', 'D.U2.Z0Z.4F.EC.SS_CIN.IDX', 'D', 'مؤشر', 'مؤشر الضغط النظامي في الأسواق المالية الأوروبية (CISS)', 'Euro area systemic stress (CISS)', 0, 1],
        'ecb.eurusd' => ['EXR', 'D.USD.EUR.SP00.A', 'D', 'دولار', 'سعر اليورو مقابل الدولار (مرجعي ECB)', 'EUR/USD ECB reference rate', 0.1, 5],
        'ecb.eurgbp' => ['EXR', 'D.GBP.EUR.SP00.A', 'D', 'جنيه', 'سعر اليورو مقابل الجنيه الإسترليني (مرجعي ECB)', 'EUR/GBP ECB reference rate', 0.1, 5],
        'ecb.eurjpy' => ['EXR', 'D.JPY.EUR.SP00.A', 'D', 'ين', 'سعر اليورو مقابل الين (مرجعي ECB)', 'EUR/JPY ECB reference rate', 10, 400],
        'ecb.eurchf' => ['EXR', 'D.CHF.EUR.SP00.A', 'D', 'فرنك', 'سعر اليورو مقابل الفرنك السويسري (مرجعي ECB)', 'EUR/CHF ECB reference rate', 0.3, 5],
        'ecb.eurcny' => ['EXR', 'D.CNY.EUR.SP00.A', 'D', 'يوان', 'سعر اليورو مقابل اليوان (مرجعي ECB)', 'EUR/CNY ECB reference rate', 1, 30],
    ];

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as $key => [, , $freq, $unit, $ar, $en, $lo, $hi]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, $freq, [new Range($lo, $hi, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'ecb.euro_area',
            source: new SourceDefinition(key: 'ecb', nameAr: 'البنك المركزي الأوروبي', nameEn: 'European Central Bank (ECB Data Portal)', publisher: 'ECB', url: 'https://data.ecb.europa.eu', license: 'ECB — مع الإشارة إلى المصدر'),
            nameAr: 'منطقة اليورو: الفائدة والعوائد والتضخم والنمو',
            nameEn: 'Euro area: rates, yields, inflation, growth',
            section: 'markets',
            intervalMinutes: 6 * 60,
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
        $i = 0;
        foreach (self::SERIES as $key => [$flow, $seriesKey]) {
            if ($i++ > 0) {
                usleep(300_000);
            }
            $csv = $this->httpAccepting('text/csv')->timeout(120)->retry(3, 4000)->get(self::BASE."{$flow}/{$seriesKey}", ['format' => 'csvdata'])->throw()->body();
            $lines = preg_split('/\r?\n/', trim($csv)) ?: [];
            $header = str_getcsv($lines[0] ?? '', ',', '"', '');
            $t = array_search('TIME_PERIOD', $header, true);
            $v = array_search('OBS_VALUE', $header, true);
            if ($t === false || $v === false) {
                throw new RuntimeException("ECB {$key}: تغيّر شكل الاستجابة.");
            }
            $n = 0;
            foreach (array_slice($lines, 1) as $line) {
                $f = str_getcsv($line, ',', '"', '');
                if (isset($f[$t], $f[$v]) && is_numeric($f[$v]) && preg_match('/^\d{4}(-\d{2}(-\d{2})?|-Q[1-4])?$/', $f[$t])) {
                    $out[] = [$key, $f[$t], (float) $f[$v]];
                    $n++;
                }
            }
            if ($n === 0) {
                throw new RuntimeException("ECB {$key}: لا نقاط.");
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        yield new ParsedEntity('EMU', 'Euro area', 'group', 'منطقة اليورو');
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $period, $value]) {
            yield new ParsedRecord($key, 'EMU', $period, $value);
        }
    }
}
