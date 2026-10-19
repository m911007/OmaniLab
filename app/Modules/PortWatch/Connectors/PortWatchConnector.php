<?php

namespace App\Modules\PortWatch\Connectors;

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
 * الملاحة البحرية العالمية من IMF PortWatch (صندوق النقد مع جامعة أكسفورد، تقديرات من إشارات AIS للسفن، خدمة ArcGIS عامة):
 * عبور السفن اليومي في 28 مضيقًا وممرًا بحريًا (هرمز وباب المندب والسويس وملقا…)، وزيارات الموانئ العُمانية (صحار وصلالة والدقم وقلهات…)
 * وأحجام وارداتها وصادراتها شهريًا، ومجاميع الدول الشهرية. تقديرات وليست إحصاءات جمركية رسمية، وتُراجَع.
 */
final class PortWatchConnector extends ApiConnector
{
    private const BASE = 'https://services9.arcgis.com/weJ1QsnbMYJlCHdG/arcgis/rest/services/';

    private const PAGE = 2000;

    public function queue(): string
    {
        return 'ingest-heavy';
    }

    public function definition(): DatasetDefinition
    {
        $r = fn (float $max = 1e12) => [new Range(0, $max, 'reject')];

        return new DatasetDefinition(
            key: 'portwatch.maritime',
            source: new SourceDefinition(key: 'portwatch', nameAr: 'IMF PortWatch (الملاحة البحرية)', nameEn: 'IMF PortWatch', publisher: 'IMF / University of Oxford', url: 'https://portwatch.imf.org', license: 'IMF PortWatch — مع الإشارة إلى المصدر', reliability: 'secondary'),
            nameAr: 'الملاحة والموانئ: المضائق والموانئ العُمانية ومجاميع الدول',
            nameEn: 'Maritime traffic: chokepoints, Omani ports, country totals',
            section: 'trade',
            intervalMinutes: 24 * 60,
            indicators: [
                new IndicatorDefinition('portwatch.chokepoint_transits', 'عبور السفن اليومي في الممر البحري', 'Daily vessel transits at chokepoint', 'سفينة', 'D', $r(5000)),
                new IndicatorDefinition('portwatch.chokepoint_tankers', 'عبور ناقلات النفط اليومي في الممر البحري', 'Daily tanker transits at chokepoint', 'سفينة', 'D', $r(5000)),
                new IndicatorDefinition('portwatch.chokepoint_capacity', 'حمولة السفن العابرة يوميًا (طن)', 'Daily transit capacity (dwt)', 'طن', 'D', $r(1e9)),
                new IndicatorDefinition('portwatch.port_calls', 'زيارات السفن للميناء (شهري)', 'Port calls (monthly)', 'زيارة', 'M', $r(1e6)),
                new IndicatorDefinition('portwatch.port_imports', 'واردات الميناء (طن، شهري)', 'Port imports (tonnes, monthly)', 'طن', 'M', $r(1e10)),
                new IndicatorDefinition('portwatch.port_exports', 'صادرات الميناء (طن، شهري)', 'Port exports (tonnes, monthly)', 'طن', 'M', $r(1e10)),
                new IndicatorDefinition('portwatch.country_port_calls', 'زيارات السفن لموانئ الدولة (شهري)', 'Country port calls (monthly)', 'زيارة', 'M', $r(1e7)),
                new IndicatorDefinition('portwatch.country_imports', 'واردات الدولة البحرية (طن، شهري)', 'Country maritime imports (tonnes, monthly)', 'طن', 'M', $r(1e11)),
                new IndicatorDefinition('portwatch.country_exports', 'صادرات الدولة البحرية (طن، شهري)', 'Country maritime exports (tonnes, monthly)', 'طن', 'M', $r(1e11)),
            ],
        );
    }

    public function probe(): ProbeResult
    {
        return new ProbeResult(token: null);
    }

    /** @return list<array<string, mixed>> */
    private function query(string $service, array $params): array
    {
        $rows = [];
        $offset = 0;
        do {
            $res = $this->http()->timeout(120)->retry(3, 3000)->get(self::BASE.$service.'/FeatureServer/0/query', $params + ['f' => 'json', 'resultRecordCount' => self::PAGE, 'resultOffset' => $offset])->throw()->json();
            if (! isset($res['features']) || isset($res['error'])) {
                throw new RuntimeException("PortWatch {$service}: ".json_encode($res['error'] ?? 'تغيّر شكل الاستجابة', JSON_UNESCAPED_UNICODE));
            }
            foreach ($res['features'] as $f) {
                $rows[] = $f['attributes'];
            }
            // الخادم قد يعيد أقل من الحد المطلوب (1000 في الإحصاءات): نتقدم بعدد ما وصل فعلًا.
            $offset += max(1, count($res['features']));
        } while (! empty($res['exceededTransferLimit']) && $res['features'] !== []);

        return $rows;
    }

    public function fetch(): RawPayload
    {
        $chokes = [];
        foreach ($this->query('PortWatch_chokepoints_database', ['where' => '1=1', 'outFields' => 'portid,portname', 'orderByFields' => 'portid']) as $c) {
            $chokes[$c['portid']] = $c['portname'];
        }
        $daily = [];
        foreach ($this->query('Daily_Chokepoints_Data', ['where' => '1=1', 'outFields' => 'date,portid,n_total,n_tanker,capacity', 'orderByFields' => 'ObjectId']) as $r) {
            $daily[] = [$r['portid'], $r['date'], $r['n_total'], $r['n_tanker'], $r['capacity']];
        }
        $stats = json_encode([
            ['statisticType' => 'sum', 'onStatisticField' => 'portcalls', 'outStatisticFieldName' => 'calls'],
            ['statisticType' => 'sum', 'onStatisticField' => 'import', 'outStatisticFieldName' => 'imp'],
            ['statisticType' => 'sum', 'onStatisticField' => 'export', 'outStatisticFieldName' => 'exp'],
        ], JSON_THROW_ON_ERROR);
        $ports = [];
        foreach ($this->query('Daily_Ports_Data', ['where' => "ISO3='OMN'", 'groupByFieldsForStatistics' => 'portid,portname,year,month', 'outStatistics' => $stats, 'orderByFields' => 'portid,year,month']) as $r) {
            $ports[] = [$r['portid'], $r['portname'], sprintf('%04d-%02d', $r['year'], $r['month']), $r['calls'], $r['imp'], $r['exp']];
        }
        $names = [];
        foreach ($this->query('PortWatch_ports_database', ['where' => '1=1', 'outFields' => 'ISO3,country', 'orderByFields' => 'ObjectId']) as $r) {
            if (preg_match('/^[A-Z]{3}$/', (string) ($r['ISO3'] ?? ''))) {
                $names[$r['ISO3']] = (string) $r['country'];
            }
        }
        $countries = [];
        foreach ($this->query('Daily_Ports_Data', ['where' => '1=1', 'groupByFieldsForStatistics' => 'ISO3,year,month', 'outStatistics' => $stats, 'orderByFields' => 'ISO3,year,month']) as $r) {
            if (preg_match('/^[A-Z]{3}$/', (string) $r['ISO3']) && isset($names[$r['ISO3']])) {
                $countries[] = [$r['ISO3'], sprintf('%04d-%02d', $r['year'], $r['month']), $r['calls'], $r['imp'], $r['exp']];
            }
        }
        if ($daily === [] || $ports === [] || $countries === []) {
            throw new RuntimeException('PortWatch: نتيجة فارغة.');
        }

        return new RawPayload(json_encode(compact('chokes', 'daily', 'ports', 'countries', 'names'), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function parse(RawPayload $payload): iterable
    {
        $d = json_decode($payload->body, true, flags: JSON_THROW_ON_ERROR);
        foreach ($d['chokes'] as $id => $name) {
            yield new ParsedEntity('PW_'.$id, $name, 'place', null);
        }
        foreach ($d['daily'] as [$id, $date, $total, $tankers, $capacity]) {
            $day = is_numeric($date) ? gmdate('Y-m-d', (int) ($date / 1000)) : (string) $date;
            foreach (['portwatch.chokepoint_transits' => $total, 'portwatch.chokepoint_tankers' => $tankers, 'portwatch.chokepoint_capacity' => $capacity] as $key => $v) {
                if (is_numeric($v)) {
                    yield new ParsedRecord($key, 'PW_'.$id, $day, (float) $v);
                }
            }
        }
        $seen = [];
        foreach ($d['ports'] as [$id, $name, $period, $calls, $imp, $exp]) {
            if (! isset($seen[$id])) {
                $seen[$id] = true;
                yield new ParsedEntity('PW_'.$id, $name, 'place', null);
            }
            foreach (['portwatch.port_calls' => $calls, 'portwatch.port_imports' => $imp, 'portwatch.port_exports' => $exp] as $key => $v) {
                if (is_numeric($v)) {
                    yield new ParsedRecord($key, 'PW_'.$id, $period, (float) $v);
                }
            }
        }
        foreach ($d['names'] as $iso => $name) {
            yield new ParsedEntity($iso, $name, 'country', null);
        }
        foreach ($d['countries'] as [$iso, $period, $calls, $imp, $exp]) {
            foreach (['portwatch.country_port_calls' => $calls, 'portwatch.country_imports' => $imp, 'portwatch.country_exports' => $exp] as $key => $v) {
                if (is_numeric($v)) {
                    yield new ParsedRecord($key, $iso, $period, (float) $v);
                }
            }
        }
    }
}
