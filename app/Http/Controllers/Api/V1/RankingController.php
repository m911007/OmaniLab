<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Indicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class RankingController extends Controller
{
    /**
     * ترتيب الكيانات في مؤشر وفترة: GET /api/v1/rankings?indicator=manafeth.EXPORTS_TO&period=2025&limit=15&type=country&highlight=OMN
     * type=any يشمل البنود غير الدولية (مثل «غير موزّع») فيتطابق المجموع مع الإجمالي. النسبة من المجموع الكلي.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'indicator' => ['required', 'string', 'max:128'],
            'period' => ['required', 'string', 'regex:/^\d{4}(-\d{2}|-Q[1-4])?$/'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
            'type' => ['nullable', 'in:country,any'],
            'highlight' => ['nullable', 'string', 'max:32'],
        ]);

        $indicator = Indicator::where('key', $data['indicator'])->firstOrFail();
        $dataset = $indicator->dataset()->with('source')->firstOrFail();
        $limit = (int) ($data['limit'] ?? 15);
        $type = $data['type'] ?? 'country';
        $highlight = isset($data['highlight']) ? strtoupper($data['highlight']) : null;

        $payload = Cache::remember('rank:'.DataVersion::token().':'.md5(json_encode($data)), 1800, function () use ($indicator, $data, $limit, $type, $highlight) {
            $base = DB::table('core.observations as o')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
                ->where('o.indicator_id', $indicator->id)->where('o.period', $data['period']);
            if ($type === 'country') {
                $base->where('e.type', 'country');
            }

            $total = (float) (clone $base)->sum('o.value');
            $count = (clone $base)->count();
            $items = (clone $base)->orderByDesc('o.value')->limit($limit)
                ->get(['e.code', 'e.name_ar', 'e.name_en', 'e.type', 'o.value'])
                ->map(fn ($r, $i) => ['rank' => $i + 1, 'entity' => ['code' => $r->code, 'name_ar' => $r->name_ar, 'name_en' => $r->name_en, 'type' => $r->type], 'value' => (float) $r->value, 'share' => $total != 0.0 ? (float) $r->value / $total : null])->all();

            $mine = null;
            if ($highlight) {
                $row = (clone $base)->where('e.code', $highlight)->first(['o.value']);
                if ($row) {
                    $pos = (clone $base)->where('o.value', '>', $row->value)->count() + 1;
                    $mine = ['code' => $highlight, 'value' => (float) $row->value, 'rank' => $pos, 'of' => $count];
                }
            }

            return ['period' => $data['period'], 'count' => $count, 'sum' => $total, 'items' => $items, 'highlight' => $mine];
        });

        return response()->json($payload + [
            'indicator' => ['key' => $indicator->key, 'name_ar' => $indicator->name_ar, 'name_en' => $indicator->name_en, 'unit' => $indicator->unit, 'frequency' => $indicator->frequency],
            'dataset' => (new DatasetResource($dataset))->resolve(),
        ]);
    }
}
