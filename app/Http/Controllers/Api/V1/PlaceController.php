<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Dataset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlaceController extends Controller
{
    /** @var list<string> */
    private const ATTRIBUTES = ['category', 'group', 'lat', 'lon', 'governorate', 'operator', 'sector', 'website', 'wikidata', 'osm_url', 'source_kind', 'seen'];

    /**
     * فئات المؤسسات وأعدادها ومحافظاتها: GET /api/v1/places/stats
     * الموقع الذي لم يُرَ في آخر جلب (سمة seen أقدم) يُستبعد: هكذا تختفي المواقع المحذوفة من الخريطة دون حذف كيانات.
     */
    public function stats(): JsonResponse
    {
        $payload = Cache::remember('places-stats:'.DataVersion::token(), 1800, function () {
            $rows = $this->base()->selectRaw("c.value #>> '{}' as category, coalesce(g.value #>> '{}', '—') as governorate, count(*) as n")
                ->groupBy('category', 'governorate')->get();
            $byCategory = [];
            $byGovernorate = [];
            foreach ($rows as $r) {
                $byCategory[$r->category] = ($byCategory[$r->category] ?? 0) + (int) $r->n;
                $byGovernorate[$r->category][$r->governorate] = (int) $r->n;
            }

            return ['categories' => collect((array) config('places.categories'))->map(fn ($c, $k) => ['key' => $k, 'group' => $c['group'], 'ar' => $c['ar'], 'en' => $c['en'], 'count' => $byCategory[$k] ?? 0, 'by_governorate' => $byGovernorate[$k] ?? (object) []])->values()->all(), 'groups' => config('places.groups')];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** قائمة المواقع: GET /api/v1/places?group=&category=&governorate=&q=&page=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['nullable', 'string', 'max:40'], 'category' => ['nullable', 'string', 'max:40'], 'governorate' => ['nullable', 'string', 'max:60'],
            'q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 25);
        $page = (int) ($data['page'] ?? 1);

        $payload = Cache::remember('places:'.DataVersion::token().':'.md5(json_encode($data)), 600, function () use ($data, $perPage, $page) {
            $query = $this->base();
            if (! empty($data['category'])) {
                $query->whereRaw("c.value #>> '{}' = ?", [$data['category']]);
            }
            if (! empty($data['group'])) {
                $query->whereExists(fn ($s) => $s->select(DB::raw(1))->from('core.entity_attributes as gr')->whereColumn('gr.entity_id', 'e.id')->where('gr.key', 'group')->whereRaw("gr.value #>> '{}' = ?", [$data['group']]));
            }
            if (! empty($data['governorate'])) {
                $query->whereRaw("g.value #>> '{}' = ?", [$data['governorate']]);
            }
            if (! empty($data['q'])) {
                $needle = '%'.mb_strtolower(trim($data['q'])).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(e.name_en) like ?', [$needle])->orWhereRaw('lower(e.name_ar) like ?', [$needle]));
            }

            $total = (clone $query)->count('e.id');
            $rows = $query->orderByRaw('coalesce(e.name_ar, e.name_en)')->forPage($page, $perPage)->get(['e.id', 'e.code', 'e.name_en', 'e.name_ar']);
            $attrs = DB::table('core.entity_attributes')->whereIn('entity_id', $rows->pluck('id'))->whereIn('key', self::ATTRIBUTES)->get(['entity_id', 'key', 'value'])->groupBy('entity_id');

            return [
                'total' => $total, 'page' => $page, 'per_page' => $perPage,
                'data' => $rows->map(fn ($r) => ['code' => $r->code, 'name_en' => $r->name_en, 'name_ar' => $r->name_ar] + collect($attrs[$r->id] ?? [])->mapWithKeys(fn ($a) => [$a->key => json_decode($a->value, true)])->all())->all(),
            ];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** مواقع «place» الحديثة فقط (seen = آخر جلب)، مع ربط سمة الفئة (c) والمحافظة (g). */
    private function base(): \Illuminate\Database\Query\Builder
    {
        $latest = DB::table('core.entity_attributes')->where('key', 'seen')->max(DB::raw("value #>> '{}'"));

        return DB::table('core.entities as e')->where('e.type', 'place')
            ->join('core.entity_attributes as c', fn ($j) => $j->on('c.entity_id', '=', 'e.id')->where('c.key', 'category'))
            ->join('core.entity_attributes as s', fn ($j) => $j->on('s.entity_id', '=', 'e.id')->where('s.key', 'seen'))
            ->leftJoin('core.entity_attributes as g', fn ($j) => $j->on('g.entity_id', '=', 'e.id')->where('g.key', 'governorate'))
            ->whereRaw("s.value #>> '{}' = ?", [(string) $latest]);
    }

    /** @return array<string, mixed>|null */
    private function dataset(): ?array
    {
        $d = Dataset::with('source')->where('key', 'osm.places')->first();

        return $d ? (new DatasetResource($d))->resolve() : null;
    }
}
