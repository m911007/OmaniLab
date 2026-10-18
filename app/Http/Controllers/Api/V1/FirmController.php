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

/** الشركات العالمية (ويكيداتا): قائمة قابلة للتصفية والترتيب، وإحصاءات، وتفاصيل شركة. */
class FirmController extends Controller
{
    /** سمات تُعرض في القائمة. */
    private const LIST = ['country', 'employees', 'revenue', 'market_cap', 'industry', 'industry_ar', 'hq', 'hq_ar', 'inception', 'ticker', 'exchange', 'website', 'logo'];

    /** GET /api/v1/firms?q=&country=&industry=&sort=employees|revenue|name|inception&page=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'country' => ['nullable', 'string', 'size:3'], 'industry' => ['nullable', 'string', 'max:100'],
            'listed' => ['nullable', 'in:1'], 'sort' => ['nullable', 'in:employees,name,inception,market_cap'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 25);
        $page = (int) ($data['page'] ?? 1);

        $payload = Cache::remember('firms:'.DataVersion::token().':'.md5(json_encode($data)), 600, function () use ($data, $perPage, $page) {
            $query = DB::table('core.entities as e')->where('e.type', 'firm');
            $attr = fn (string $alias, string $key) => $query->leftJoin("core.entity_attributes as {$alias}", fn ($j) => $j->on("{$alias}.entity_id", '=', 'e.id')->where("{$alias}.key", $key));
            $attr('co', 'country');
            $attr('em', 'employees');
            if (! empty($data['country'])) {
                $query->whereRaw("co.value #>> '{}' = ?", [strtoupper($data['country'])]);
            }
            if (! empty($data['industry'])) {
                $needle = mb_strtolower($data['industry']);
                $query->whereExists(fn ($s) => $s->select(DB::raw(1))->from('core.entity_attributes as i')->whereColumn('i.entity_id', 'e.id')->whereIn('i.key', ['industry', 'industry_ar'])->whereRaw('lower(i.value::text) like ?', ['%'.$needle.'%']));
            }
            if (! empty($data['listed'])) {
                $query->whereExists(fn ($s) => $s->select(DB::raw(1))->from('core.entity_attributes as t')->whereColumn('t.entity_id', 'e.id')->whereIn('t.key', ['ticker', 'exchange']));
            }
            if (! empty($data['q'])) {
                $needle = '%'.mb_strtolower(trim($data['q'])).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(e.name_en) like ?', [$needle])->orWhereRaw('lower(e.name_ar) like ?', [$needle]));
            }
            $sort = $data['sort'] ?? 'employees';
            if ($sort === 'name') {
                $query->orderByRaw('coalesce(e.name_ar, e.name_en)');
            } elseif ($sort === 'inception') {
                $attr('inc', 'inception');
                $query->orderByRaw("nullif(inc.value #>> '{}', '')::int ASC NULLS LAST");
            } elseif ($sort === 'market_cap') {
                $attr('mc', 'market_cap');
                $query->orderByRaw("(mc.value ->> 'amount')::float DESC NULLS LAST");
            } else {
                $query->orderByRaw("(em.value #>> '{}')::float DESC NULLS LAST");
            }

            $total = (clone $query)->count('e.id');
            $rows = $query->orderBy('e.id')->forPage($page, $perPage)->get(['e.id', 'e.code', 'e.name_en', 'e.name_ar']);
            $attrs = DB::table('core.entity_attributes')->whereIn('entity_id', $rows->pluck('id'))->whereIn('key', self::LIST)->get(['entity_id', 'key', 'value'])->groupBy('entity_id');

            return ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'data' => $rows->map(fn ($r) => ['code' => substr($r->code, 3), 'name_en' => $r->name_en, 'name_ar' => $r->name_ar] + collect($attrs[$r->id] ?? [])->mapWithKeys(fn ($a) => [$a->key => json_decode($a->value, true)])->all())->all()];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** GET /api/v1/firms/stats: الإجمالي وعدد الشركات حسب الدولة (الأعلى) وأكثر القطاعات. */
    public function stats(): JsonResponse
    {
        $payload = Cache::remember('firms-stats:'.DataVersion::token(), 1800, function () {
            $countries = DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')->where('e.type', 'firm')->where('a.key', 'country')
                ->selectRaw("a.value #>> '{}' as iso, count(*) as n")->groupBy('iso')->orderByDesc('n')->get();
            $names = DB::table('core.entities')->where('type', 'country')->whereIn('code', $countries->pluck('iso'))->get(['code', 'name_ar', 'name_en'])->keyBy('code');
            $industries = DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')->where('e.type', 'firm')->where('a.key', 'industry_ar')
                ->selectRaw('jsonb_array_elements_text(a.value) as industry, count(*) as n')->groupBy('industry')->orderByDesc('n')->limit(40)->get();

            return [
                'total' => (int) DB::table('core.entities')->where('type', 'firm')->count(),
                'countries' => $countries->map(fn ($r) => ['code' => $r->iso, 'name_ar' => $names[$r->iso]->name_ar ?? null, 'name_en' => $names[$r->iso]->name_en ?? $r->iso, 'count' => (int) $r->n])->all(),
                'industries' => $industries->map(fn ($r) => ['name' => $r->industry, 'count' => (int) $r->n])->all(),
            ];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** GET /api/v1/firms/Q123: كل سمات الشركة (الرمز معرّف ويكيداتا). */
    public function show(string $code): JsonResponse
    {
        $entity = Entity::where('type', 'firm')->where('code', 'wd:'.$code)->firstOrFail();
        $attrs = DB::table('core.entity_attributes')->where('entity_id', $entity->id)->get(['key', 'value'])->mapWithKeys(fn ($a) => [$a->key => json_decode($a->value, true)])->all();

        return response()->json(['data' => ['code' => substr($entity->code, 3), 'name_en' => $entity->name_en, 'name_ar' => $entity->name_ar] + $attrs, 'dataset' => $this->dataset()]);
    }

    /** @return array<string, mixed>|null */
    private function dataset(): ?array
    {
        $d = Dataset::with('source')->where('key', 'wikidata.firms')->first();

        return $d ? (new DatasetResource($d))->resolve() : null;
    }
}
