<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Entity;
use App\Models\Indicator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SeriesController extends Controller
{
    /**
     * سلسلة مؤشر لكيان واحد مع حداثتها: GET /api/v1/series/imf.NGDP_RPCH/OMN
     * كل استجابة تحمل مصدرها وتاريخ آخر فحص وآخر تغيير، فلا رقم بلا أصل.
     */
    public function show(string $indicator, string $entity): JsonResponse
    {
        $ind = Indicator::where('key', $indicator)->firstOrFail();
        $ent = Entity::where('code', strtoupper($entity))->firstOrFail();
        $dataset = $ind->dataset()->with('source')->firstOrFail();

        // مفتاح الكاش يتضمن لحظة آخر تغيير: أي ترحيل جديد يبطله تلقائيًا.
        $version = md5((string) $dataset->getRawOriginal('last_changed_at'));
        $points = Cache::remember("series:{$ind->id}:{$ent->id}:{$version}", 3600, fn () => DB::table('core.observations')
            ->where('indicator_id', $ind->id)->where('entity_id', $ent->id)
            ->orderBy('period')->get(['period', 'value'])
            ->map(fn ($r) => ['period' => $r->period, 'value' => (float) $r->value])->all());

        return response()->json([
            'indicator' => ['key' => $ind->key, 'name_ar' => $ind->name_ar, 'name_en' => $ind->name_en, 'unit' => $ind->unit, 'frequency' => $ind->frequency],
            'entity' => ['code' => $ent->code, 'name_en' => $ent->name_en, 'name_ar' => $ent->name_ar, 'type' => $ent->type],
            'points' => $points,
            'dataset' => (new DatasetResource($dataset))->resolve(),
        ]);
    }
}
