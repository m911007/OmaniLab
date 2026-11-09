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

class ObservationController extends Controller
{
    private const MAX_ENTITIES = 200;

    /**
     * سلاسل مؤشر واحد لعدة كيانات: GET /api/v1/observations?indicator=imf.NGDP_RPCH&entities=OMN,SAU&from=2005
     * النقاط مصفوفات [فترة، قيمة] لتقليل الحجم. كل رد يحمل مصدره وحداثته.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'indicator' => ['required', 'string', 'max:128'],
            'entities' => ['required', 'string', 'max:2000'],
            'from' => ['nullable', 'string', 'regex:/^\d{4}(-\d{2})?$/'],
            'to' => ['nullable', 'string', 'regex:/^\d{4}(-\d{2})?$/'],
        ]);

        $codes = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim($c)), explode(',', $data['entities'])))));
        abort_if($codes === [] || count($codes) > self::MAX_ENTITIES, 422, 'عدد الكيانات غير صالح.');

        $indicator = Indicator::where('key', $data['indicator'])->firstOrFail();
        $dataset = $indicator->dataset()->with('source')->firstOrFail();
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? null;

        $key = 'obs:'.DataVersion::token().':'.md5($indicator->key.'|'.implode(',', $codes).'|'.$from.'|'.$to);
        $series = Cache::remember($key, 1800, function () use ($indicator, $codes, $from, $to) {
            $query = DB::table('core.observations as o')
                ->join('core.entities as e', 'e.id', '=', 'o.entity_id')
                ->where('o.indicator_id', $indicator->id)->whereIn('e.code', $codes)
                ->orderBy('e.code')->orderBy('o.period')
                ->select('e.code', 'e.name_ar', 'e.name_en', 'e.type', 'o.period', 'o.value');
            if ($from) {
                $query->where('o.period', '>=', $from);
            }
            if ($to) {
                $query->where('o.period', '<=', $to.'~');
            }

            $out = [];
            foreach ($query->cursor() as $r) {
                $out[$r->code] ??= ['entity' => ['code' => $r->code, 'name_ar' => $r->name_ar, 'name_en' => $r->name_en, 'type' => $r->type], 'points' => []];
                $out[$r->code]['points'][] = [$r->period, (float) $r->value];
            }

            return array_values($out);
        });

        return response()->json([
            'indicator' => $this->indicatorPayload($indicator),
            'dataset' => (new DatasetResource($dataset))->resolve(),
            'series' => $series,
        ]);
    }

    /** @return array<string, mixed> */
    private function indicatorPayload(Indicator $i): array
    {
        return ['key' => $i->key, 'name_ar' => $i->name_ar, 'name_en' => $i->name_en, 'unit' => $i->unit, 'frequency' => $i->frequency];
    }
}
