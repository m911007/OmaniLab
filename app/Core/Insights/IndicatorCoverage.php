<?php

namespace App\Core\Insights;

use App\Core\Api\DataVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * تغطية كل مؤشر له بيانات: آخر فترة، وعدد الكيانات، وهل لعُمان بيانات. الحساب على ملايين القيم يستغرق ثوانيَ،
 * فيُخزَّن لكل إصدار بيانات ويُسخَّن مسبقًا من المجدول (insights:warm) كي لا ينتظره القارئ.
 */
final class IndicatorCoverage
{
    /** @return array<int, array{last_period: string, entities: int, omn: bool}> */
    public function all(): array
    {
        return Cache::remember('indicator-coverage:'.DataVersion::token(), 3600, function () {
            $omn = (int) DB::table('core.entities')->where('code', 'OMN')->value('id');
            $rows = DB::table('core.observations')->groupBy('indicator_id')
                ->get(['indicator_id', DB::raw('max(period) as last_period'), DB::raw('count(distinct entity_id) as entities'), DB::raw('bool_or(entity_id = '.$omn.') as omn')]);
            $out = [];
            foreach ($rows as $r) {
                $out[(int) $r->indicator_id] = ['last_period' => (string) $r->last_period, 'entities' => (int) $r->entities, 'omn' => (bool) $r->omn];
            }

            return $out;
        });
    }
}
