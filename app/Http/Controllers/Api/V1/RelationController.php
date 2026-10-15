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

class RelationController extends Controller
{
    /**
     * أكبر الأطراف في علاقة لكيان: GET /api/v1/relations?indicator=wits.EXPORTS_TO&entity=OMN&period=2023&direction=out&limit=15
     * direction=out: الكيان هو المصدر (صادراته إلى…)؛ in: الكيان هو الهدف (من يصدّر إليه).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'indicator' => ['required', 'string', 'max:128'],
            'entity' => ['required', 'string', 'max:64'],
            'period' => ['required', 'string', 'regex:/^\d{4}(-\d{2}|-Q[1-4])?$/'],
            'direction' => ['nullable', 'in:out,in'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $indicator = Indicator::where('key', $data['indicator'])->firstOrFail();
        $entity = Entity::where('code', strtoupper($data['entity']))->firstOrFail();
        $dataset = $indicator->dataset()->with('source')->firstOrFail();
        $out = ($data['direction'] ?? 'out') === 'out';
        $limit = (int) ($data['limit'] ?? 15);

        $payload = Cache::remember('rel:'.DataVersion::token().':'.md5(json_encode($data)), 1800, function () use ($indicator, $entity, $data, $out, $limit) {
            [$mine, $other] = $out ? ['source_entity_id', 'target_entity_id'] : ['target_entity_id', 'source_entity_id'];
            $base = DB::table('core.relations as r')->join('core.entities as e', 'e.id', '=', "r.{$other}")
                ->where('r.indicator_id', $indicator->id)->where("r.{$mine}", $entity->id)->where('r.period', $data['period']);

            $sum = (float) (clone $base)->sum('r.value');
            $count = (clone $base)->count();
            $items = $base->orderByDesc('r.value')->limit($limit)->get(['e.code', 'e.name_ar', 'e.name_en', 'e.type', 'r.value'])
                ->map(fn ($r, $i) => ['rank' => $i + 1, 'entity' => ['code' => $r->code, 'name_ar' => $r->name_ar, 'name_en' => $r->name_en, 'type' => $r->type], 'value' => (float) $r->value, 'share' => $sum != 0.0 ? (float) $r->value / $sum : null])->all();

            return ['count' => $count, 'sum' => $sum, 'items' => $items];
        });

        return response()->json($payload + [
            'entity' => ['code' => $entity->code, 'name_ar' => $entity->name_ar, 'name_en' => $entity->name_en],
            'direction' => $out ? 'out' : 'in',
            'period' => $data['period'],
            'indicator' => ['key' => $indicator->key, 'name_ar' => $indicator->name_ar, 'name_en' => $indicator->name_en, 'unit' => $indicator->unit, 'frequency' => $indicator->frequency],
            'dataset' => (new DatasetResource($dataset))->resolve(),
        ]);
    }

    /** تطور علاقة بين كيانين عبر الفترات: GET /api/v1/relations/series?indicator=&source=&target= */
    public function series(Request $request): JsonResponse
    {
        $data = $request->validate([
            'indicator' => ['required', 'string', 'max:128'],
            'source' => ['required', 'string', 'max:64'],
            'target' => ['required', 'string', 'max:64'],
        ]);

        $indicator = Indicator::where('key', $data['indicator'])->firstOrFail();
        $source = Entity::where('code', strtoupper($data['source']))->firstOrFail();
        $target = Entity::where('code', strtoupper($data['target']))->firstOrFail();
        $dataset = $indicator->dataset()->with('source')->firstOrFail();

        $points = DB::table('core.relations')->where('indicator_id', $indicator->id)
            ->where('source_entity_id', $source->id)->where('target_entity_id', $target->id)->orderBy('period')
            ->get(['period', 'value'])->map(fn ($r) => [$r->period, (float) $r->value])->all();

        return response()->json([
            'indicator' => ['key' => $indicator->key, 'name_ar' => $indicator->name_ar, 'name_en' => $indicator->name_en, 'unit' => $indicator->unit, 'frequency' => $indicator->frequency],
            'source' => ['code' => $source->code, 'name_ar' => $source->name_ar, 'name_en' => $source->name_en],
            'target' => ['code' => $target->code, 'name_ar' => $target->name_ar, 'name_en' => $target->name_en],
            'points' => $points,
            'dataset' => (new DatasetResource($dataset))->resolve(),
        ]);
    }
}
