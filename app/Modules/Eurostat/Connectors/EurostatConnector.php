<?php

namespace App\Modules\Eurostat\Connectors;

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
 * اقتصادات أوروبا دولةً دولة من Eurostat (واجهة SDMX-CSV العامة): التضخم والبطالة ونمو الناتج والدين الحكومي وناتج الفرد والإنتاج الصناعي
 * وعوائد السندات طويلة الأجل لدول الاتحاد الأوروبي والاقتصاد الأوروبي الأوسع، مع منطقة اليورو والاتحاد ككل. أحدث تحديث للمصدر نفسه.
 */
final class EurostatConnector extends ApiConnector
{
    private const BASE = 'https://ec.europa.eu/eurostat/api/dissemination/sdmx/2.1/data/';

    /** @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string, 6: float, 7: float}> مفتاح => [تدفق، مفتاح، تردد، وحدة، عربي، إنجليزي، حد أدنى، حد أعلى] */
    private const SERIES = [
        'eurostat.hicp' => ['prc_hicp_manr', 'M..CP00.', 'M', '%', 'التضخم السنوي (HICP) — أوروبا', 'HICP inflation, y/y (Eurostat)', -20, 100],
        'eurostat.hicp_food' => ['prc_hicp_manr', 'M..CP01.', 'M', '%', 'تضخم الغذاء والمشروبات — أوروبا', 'HICP food inflation, y/y', -30, 200],
        'eurostat.hicp_energy' => ['prc_hicp_manr', 'M..NRG.', 'M', '%', 'تضخم الطاقة — أوروبا', 'HICP energy inflation, y/y', -60, 400],
        'eurostat.unemployment' => ['une_rt_m', 'M.SA.TOTAL.PC_ACT.T.', 'M', '%', 'معدل البطالة — أوروبا', 'Unemployment rate (Eurostat)', 0, 60],
        'eurostat.unemployment_youth' => ['une_rt_m', 'M.SA.Y_LT25.PC_ACT.T.', 'M', '%', 'بطالة الشباب (تحت 25) — أوروبا', 'Youth unemployment rate', 0, 90],
        'eurostat.gdp_growth' => ['namq_10_gdp', 'Q.CLV_PCH_SM.SCA.B1GQ.', 'Q', '%', 'نمو الناتج المحلي الحقيقي (سنوي) — أوروبا', 'Real GDP growth, y/y (Eurostat)', -30, 40],
        'eurostat.gov_debt' => ['gov_10dd_edpt1', 'A.PC_GDP.S13.GD.', 'A', '%', 'الدين الحكومي (% من الناتج) — أوروبا', 'General government debt, % of GDP', 0, 500],
        'eurostat.gdp_per_capita' => ['nama_10_pc', 'A.CLV10_EUR_HAB.B1GQ.', 'A', 'يورو', 'الناتج للفرد (يورو، أسعار 2010) — أوروبا', 'Real GDP per capita (chain-linked 2010 euro)', 0, 500000],
        'eurostat.industrial_production' => ['sts_inpr_m', 'M.PRD.C.SCA.I21.', 'M', 'مؤشر 2021=100', 'الإنتاج الصناعي (تصنيع واستخراج) — أوروبا', 'Industrial production index (2021=100)', 0, 1000],
        'eurostat.bond_yield_10y' => ['irt_lt_mcby_m', 'M.MCBY.', 'M', '%', 'عائد السندات الحكومية طويلة الأجل (معيار ماستريخت)', 'Long-term government bond yield (Maastricht criterion)', -3, 60],
    ];

    private const ISO = [
        'AT' => 'AUT', 'BE' => 'BEL', 'BG' => 'BGR', 'CY' => 'CYP', 'CZ' => 'CZE', 'DE' => 'DEU', 'DK' => 'DNK', 'EE' => 'EST', 'EL' => 'GRC', 'ES' => 'ESP', 'FI' => 'FIN', 'FR' => 'FRA',
        'HR' => 'HRV', 'HU' => 'HUN', 'IE' => 'IRL', 'IT' => 'ITA', 'LT' => 'LTU', 'LU' => 'LUX', 'LV' => 'LVA', 'MT' => 'MLT', 'NL' => 'NLD', 'PL' => 'POL', 'PT' => 'PRT', 'RO' => 'ROU',
        'SE' => 'SWE', 'SI' => 'SVN', 'SK' => 'SVK', 'IS' => 'ISL', 'NO' => 'NOR', 'CH' => 'CHE', 'UK' => 'GBR', 'TR' => 'TUR', 'US' => 'USA', 'JP' => 'JPN', 'AL' => 'ALB', 'MK' => 'MKD',
        'RS' => 'SRB', 'ME' => 'MNE', 'BA' => 'BIH', 'EA20' => 'EA20', 'EA19' => 'EA19', 'EU27_2020' => 'EU27',
    ];

    private const NAMES = [
        'EA20' => ['Euro area (20 countries)', 'منطقة اليورو (20 دولة)'], 'EA19' => ['Euro area (19 countries)', 'منطقة اليورو (19 دولة)'], 'EU27' => ['European Union (27)', 'الاتحاد الأوروبي (27)'],
    ];

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $indicators = [];
        foreach (self::SERIES as $key => [, , $freq, $unit, $ar, $en, $lo, $hi]) {
            $indicators[] = new IndicatorDefinition($key, $ar, $en, $unit, $freq, [new Range($lo, $hi, 'reject')]);
        }

        return new DatasetDefinition(
            key: 'eurostat.economy',
            source: new SourceDefinition(key: 'eurostat', nameAr: 'يوروستات (المكتب الإحصائي للاتحاد الأوروبي)', nameEn: 'Eurostat', publisher: 'European Commission — Eurostat', url: 'https://ec.europa.eu/eurostat', license: 'CC BY 4.0'),
            nameAr: 'اقتصادات أوروبا: التضخم والبطالة والنمو والدين والعوائد',
            nameEn: 'European economies: inflation, unemployment, growth, debt, yields',
            section: 'macro',
            intervalMinutes: 24 * 60,
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
        foreach (self::SERIES as $key => [$flow, $filter]) {
            if ($i++ > 0) {
                usleep(500_000);
            }
            $csv = $this->httpAccepting('*/*')->timeout(180)->retry(3, 4000)->get(self::BASE."{$flow}/{$filter}", ['startPeriod' => '1995', 'format' => 'SDMX-CSV'])->throw()->body();
            $lines = preg_split('/\r?\n/', trim($csv)) ?: [];
            $header = str_getcsv($lines[0] ?? '', ',', '"', '');
            $g = array_search('geo', $header, true);
            $t = array_search('TIME_PERIOD', $header, true);
            $v = array_search('OBS_VALUE', $header, true);
            if ($g === false || $t === false || $v === false) {
                throw new RuntimeException("Eurostat {$key}: تغيّر شكل الاستجابة.");
            }
            $n = 0;
            foreach (array_slice($lines, 1) as $line) {
                $f = str_getcsv($line, ',', '"', '');
                $iso = self::ISO[$f[$g] ?? ''] ?? null;
                if ($iso !== null && is_numeric($f[$v] ?? null) && preg_match('/^\d{4}(-\d{2}|-Q[1-4])?$/', $f[$t] ?? '')) {
                    $out[] = [$key, $iso, $f[$t], (float) $f[$v]];
                    $n++;
                }
            }
            if ($n === 0) {
                throw new RuntimeException("Eurostat {$key}: لا نقاط.");
            }
        }

        return new RawPayload(json_encode($out, JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        foreach (self::NAMES as $code => [$en, $ar]) {
            yield new ParsedEntity($code, $en, 'group', $ar);
        }
        foreach (json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR) as [$key, $iso, $period, $value]) {
            yield new ParsedRecord($key, $iso, $period, $value);
        }
    }
}
