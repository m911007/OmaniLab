<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CountryDirectoryController extends Controller
{
    /** أعمدة الدليل: مفتاح العمود => [مؤشر، آخر فترة مسموحة (تُستثنى التوقعات)]. */
    private const COLUMNS = [
        'gdp' => ['wb.NY.GDP.MKTP.CD', false],
        'gdp_pc' => ['wb.NY.GDP.PCAP.CD', false],
        'gdp_pc_ppp' => ['wb.NY.GDP.PCAP.PP.CD', false],
        'population' => ['wb.SP.POP.TOTL', false],
        'growth' => ['imf.NGDP_RPCH', true],
        'inflation' => ['wb.FP.CPI.TOTL.ZG', false],
        'unemployment' => ['wb.SL.UEM.TOTL.ZS', false],
        'debt' => ['imf.GGXWDG_NGDP', true],
        'exports_pct' => ['wb.NE.EXP.GNFS.ZS', false],
        'internet' => ['wb.IT.NET.USER.ZS', false],
        'life_expectancy' => ['wb.SP.DYN.LE00.IN', false],
        'hdi' => ['undp.hdi', false],
        'oman_exports_to' => ['manafeth.EXPORTS_TO', false],
        'oman_imports_from' => ['manafeth.IMPORTS_FROM', false],
    ];

    /**
     * دليل دول العالم: صف لكل دولة بآخر قيمة لأهم المؤشرات (مع سنتها) وإقليمها ومستوى دخلها وتجارتها مع عُمان.
     * GET /api/v1/countries/directory
     */
    public function index(): JsonResponse
    {
        $payload = Cache::remember('directory:'.DataVersion::token(), 3600, function () {
            $lastActual = (string) ((int) date('Y') - 1);
            $countries = DB::table('core.entities')->where('type', 'country')->get(['id', 'code', 'name_ar', 'name_en'])->keyBy('id');
            $rows = [];
            foreach ($countries as $id => $c) {
                $rows[$id] = ['code' => $c->code, 'name_ar' => $c->name_ar, 'name_en' => $c->name_en, 'region' => null, 'income_level' => null, 'capital' => null, 'iso2' => null, 'values' => []];
            }

            foreach (DB::table('core.entity_attributes')->whereIn('key', ['region', 'income_level', 'capital_city', 'iso2'])->get(['entity_id', 'key', 'value']) as $a) {
                if (isset($rows[$a->entity_id])) {
                    $field = ['region' => 'region', 'income_level' => 'income_level', 'capital_city' => 'capital', 'iso2' => 'iso2'][$a->key];
                    $rows[$a->entity_id][$field] = json_decode($a->value, true);
                }
            }

            foreach (self::COLUMNS as $column => [$key, $actualOnly]) {
                $cap = $actualOnly ? 'AND o.period <= ?' : '';
                $bindings = $actualOnly ? [$key, $lastActual.'~'] : [$key];
                $sql = "SELECT DISTINCT ON (o.entity_id) o.entity_id, o.period, o.value
                        FROM core.observations o JOIN core.indicators i ON i.id = o.indicator_id
                        WHERE i.key = ? {$cap} AND o.period ~ '^[0-9]{4}\$'
                        ORDER BY o.entity_id, o.period DESC";
                foreach (DB::select($sql, $bindings) as $r) {
                    if (isset($rows[$r->entity_id])) {
                        $rows[$r->entity_id]['values'][$column] = ['value' => (float) $r->value, 'period' => $r->period];
                    }
                }
            }

            // دولة تُدرج إن كان لها اقتصاد (ناتج أو سكان) — يستبعد الكيانات الفارغة.
            $data = array_values(array_filter($rows, fn ($r) => isset($r['values']['gdp']) || isset($r['values']['population'])));
            usort($data, fn ($a, $b) => strcmp($a['name_en'], $b['name_en']));

            return $data;
        });

        return response()->json(['data' => $payload, 'columns' => array_map(fn ($c) => $c[0], self::COLUMNS)]);
    }
}
