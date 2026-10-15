<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Entity;
use App\Models\Indicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EntityController extends Controller
{
    /** قائمة الكيانات للاختيار: GET /api/v1/entities?type=country&with=wb.NY.GDP.MKTP.CD (فقط من لها بيانات لهذا المؤشر). */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', 'in:country,region,group,other'],
            'with' => ['nullable', 'string', 'max:128'],
        ]);

        $rows = Cache::remember('entities:'.DataVersion::token().':'.md5(json_encode($data)), 3600, function () use ($data) {
            $q = DB::table('core.entities as e')->select('e.code', 'e.name_ar', 'e.name_en', 'e.type')->orderBy('e.name_en');
            if (! empty($data['type'])) {
                $q->where('e.type', $data['type']);
            }
            $q->whereExists(function ($sub) use ($data) {
                $sub->select(DB::raw(1))->from('core.observations as o')->whereColumn('o.entity_id', 'e.id');
                if (! empty($data['with'])) {
                    $sub->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->where('i.key', $data['with']);
                }
            });

            return $q->get()->map(fn ($r) => (array) $r)->all();
        });

        return response()->json(['data' => $rows]);
    }

    /**
     * آخر قيمة لكل مؤشر مطلوب لكيان، مع القيمة السابقة وترتيبه بين الدول: GET /api/v1/entities/OMN/latest?indicators=a,b
     * الترتيب يُحسب بين الدول فقط وللفترة نفسها.
     */
    public function latest(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'indicators' => ['required', 'string', 'max:1500'],
            'max_period' => ['nullable', 'string', 'regex:/^\d{4}(-\d{2}|-Q[1-4])?$/'], // آخر فترة مقبولة (لتجاوز التوقعات)
        ]);
        $maxPeriod = $request->query('max_period');
        $entity = Entity::where('code', strtoupper($code))->firstOrFail();
        $keys = array_values(array_unique(array_filter(array_map('trim', explode(',', (string) $request->query('indicators'))))));
        abort_if($keys === [] || count($keys) > 40, 422, 'عدد المؤشرات غير صالح.');

        $items = Cache::remember('latest:'.DataVersion::token().':'.$entity->id.':'.md5(implode(',', $keys).'|'.$maxPeriod), 1800, function () use ($entity, $keys, $maxPeriod) {
            $out = [];
            foreach (Indicator::whereIn('key', $keys)->with('dataset.source')->get()->keyBy('key') as $key => $ind) {
                $query = DB::table('core.observations')->where('indicator_id', $ind->id)->where('entity_id', $entity->id);
                if ($maxPeriod) {
                    $query->where('period', '<=', $maxPeriod.'~');
                }
                $two = $query->orderByDesc('period')->limit(2)->get(['period', 'value']);
                if ($two->isEmpty()) {
                    continue;
                }

                $latest = $two[0];
                $rank = DB::selectOne(<<<'SQL'
                    SELECT count(*) FILTER (WHERE o.value > ?) + 1 AS position, count(*) AS total
                    FROM core.observations o JOIN core.entities e ON e.id = o.entity_id
                    WHERE o.indicator_id = ? AND o.period = ? AND e.type = 'country'
                    SQL, [$latest->value, $ind->id, $latest->period]);

                $out[$key] = [
                    'indicator' => ['key' => $ind->key, 'name_ar' => $ind->name_ar, 'name_en' => $ind->name_en, 'unit' => $ind->unit, 'frequency' => $ind->frequency],
                    'period' => $latest->period,
                    'value' => (float) $latest->value,
                    'previous' => isset($two[1]) ? ['period' => $two[1]->period, 'value' => (float) $two[1]->value] : null,
                    'rank' => $entity->type === 'country' && $rank->total >= 20 ? ['position' => (int) $rank->position, 'total' => (int) $rank->total] : null,
                    'dataset' => (new DatasetResource($ind->dataset))->resolve(),
                ];
            }

            return $out;
        });

        // الترتيب كما طُلب
        $ordered = array_values(array_filter(array_map(fn ($k) => $items[$k] ?? null, $keys)));

        return response()->json([
            'entity' => ['code' => $entity->code, 'name_ar' => $entity->name_ar, 'name_en' => $entity->name_en, 'type' => $entity->type],
            'items' => $ordered,
        ]);
    }
}
