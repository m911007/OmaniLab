<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Http\Controllers\Controller;
use App\Http\Resources\DatasetResource;
use App\Models\Dataset;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    /** سمات تُعرض في القوائم (الباقي في صفحة الشركة). */
    private const LIST_ATTRIBUTES = ['status', 'category', 'city', 'governorate', 'legal_form', 'creation_date', 'registered_as'];

    /** فلاتر بسيطة: اسم الوسيط في الطلب → مفتاح السمة. */
    private const FILTERS = ['status' => 'status', 'category' => 'category', 'governorate' => 'governorate', 'city' => 'city', 'legal_form' => 'legal_form'];

    /**
     * قائمة الشركات: GET /api/v1/companies?q=&status=&governorate=&category=&sort=name|created&page=&per_page=
     * البحث بالاسم عربيًا أو إنجليزيًا أو بالـ LEI (فهرس trigram).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:60'],
            'governorate' => ['nullable', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:80'],
            'legal_form' => ['nullable', 'string', 'max:40'],
            'sort' => ['nullable', 'in:name,created'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = (int) ($data['per_page'] ?? 25);
        $page = (int) ($data['page'] ?? 1);

        $payload = Cache::remember('companies:'.DataVersion::token().':'.md5(json_encode($data)), 600, function () use ($data, $perPage, $page) {
            $query = DB::table('core.entities as e')->where('e.type', 'company');

            if (! empty($data['q'])) {
                $needle = '%'.mb_strtolower(trim($data['q'])).'%';
                $byLei = mb_strlen(trim($data['q'])) >= 8; // مطابقة LEI لا تكون إلا لنص طويل كفاية (وإلا ضجيج)
                $query->where(function ($q) use ($needle, $byLei) {
                    $q->whereRaw('lower(e.name_en) like ?', [$needle])->orWhereRaw('lower(e.name_ar) like ?', [$needle]);
                    if ($byLei) {
                        $q->orWhereRaw('lower(e.code) like ?', [$needle]);
                    }
                });
            }
            foreach (self::FILTERS as $param => $key) {
                if (! empty($data[$param])) {
                    $query->whereExists(fn ($sub) => $sub->select(DB::raw(1))->from('core.entity_attributes as f')
                        ->whereColumn('f.entity_id', 'e.id')->where('f.key', $key)->whereRaw("f.value #>> '{}' = ?", [$data[$param]]));
                }
            }

            $total = (clone $query)->count();
            $sort = ($data['sort'] ?? 'name') === 'created'
                ? "(SELECT value #>> '{}' FROM core.entity_attributes c WHERE c.entity_id = e.id AND c.key = 'creation_date') DESC NULLS LAST"
                : 'lower(e.name_en) ASC';

            $rows = $query->orderByRaw($sort)->forPage($page, $perPage)->get(['e.id', 'e.code', 'e.name_en', 'e.name_ar']);
            $attrs = $this->attributesFor($rows->pluck('id')->all(), self::LIST_ATTRIBUTES);

            return [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'data' => $rows->map(fn ($r) => ['code' => $r->code, 'name_en' => $r->name_en, 'name_ar' => $r->name_ar] + ($attrs[$r->id] ?? []))->all(),
            ];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** ملخص الشركات: أعداد بحسب الحالة والمحافظة والفئة وسنة التأسيس. */
    public function stats(): JsonResponse
    {
        $payload = Cache::remember('companies:stats:'.DataVersion::token(), 900, function () {
            $group = fn (string $key, int $limit = 30) => DB::table('core.entity_attributes as a')
                ->join('core.entities as e', 'e.id', '=', 'a.entity_id')->where('e.type', 'company')->where('a.key', $key)
                ->selectRaw("a.value #>> '{}' as label, count(*) as n")->groupBy('label')->orderByDesc('n')->limit($limit)->get()
                ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->all();

            $byYear = DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')
                ->where('e.type', 'company')->where('a.key', 'creation_date')
                ->selectRaw("substr(a.value #>> '{}', 1, 4) as year, count(*) as n")->groupBy('year')->orderBy('year')->get()
                ->map(fn ($r) => ['year' => $r->year, 'count' => (int) $r->n])->all();

            return [
                'total' => (int) DB::table('core.entities')->where('type', 'company')->count(),
                'by_status' => $group('status'),
                'by_governorate' => $group('governorate'),
                'by_category' => $group('category'),
                'by_legal_form' => $group('legal_form', 12),
                'by_year' => $byYear,
            ];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** ملف الشركة: كل سماتها مع مصدرها وحداثتها. */
    public function show(string $code): JsonResponse
    {
        $entity = Entity::where('type', 'company')->where('code', $code)->firstOrFail();
        $attrs = $this->attributesFor([$entity->id], null)[$entity->id] ?? [];

        return response()->json([
            'code' => $entity->code,
            'name_en' => $entity->name_en,
            'name_ar' => $entity->name_ar,
            'attributes' => $attrs,
            'dataset' => $this->dataset(),
        ]);
    }

    /**
     * @param  list<int>  $ids
     * @param  list<string>|null  $keys  null = كل السمات
     * @return array<int, array<string, mixed>>
     */
    private function attributesFor(array $ids, ?array $keys): array
    {
        if ($ids === []) {
            return [];
        }
        $q = DB::table('core.entity_attributes')->whereIn('entity_id', $ids);
        if ($keys !== null) {
            $q->whereIn('key', $keys);
        }

        $out = [];
        foreach ($q->get(['entity_id', 'key', 'value']) as $r) {
            $out[$r->entity_id][$r->key] = json_decode($r->value, true);
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    private function dataset(): ?array
    {
        $dataset = Dataset::with('source')->where('key', 'gleif.oman')->first();

        return $dataset ? (new DatasetResource($dataset))->resolve() : null;
    }
}
