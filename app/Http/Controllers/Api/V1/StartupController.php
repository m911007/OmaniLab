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

/** الشركات الناشئة (منصة عُمان للشركات الناشئة): قائمة قابلة للتصفية وإحصاءات التوزيع. */
class StartupController extends Controller
{
    private const LIST = ['blurb', 'description', 'stage', 'employees', 'legal_type', 'activity_status', 'sector', 'subsector', 'seeking', 'founded', 'founded_year', 'funding_sought', 'url'];

    /** GET /api/v1/startups?q=&sector=&stage=&page=&per_page= */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'], 'sector' => ['nullable', 'string', 'max:100'], 'stage' => ['nullable', 'string', 'max:60'],
            'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($data['per_page'] ?? 24);
        $page = (int) ($data['page'] ?? 1);

        $payload = Cache::remember('startups:'.DataVersion::token().':'.md5(json_encode($data)), 600, function () use ($data, $perPage, $page) {
            $query = DB::table('core.entities as e')->where('e.type', 'startup');
            if (! empty($data['stage'])) {
                $query->whereExists(fn ($s) => $s->select(DB::raw(1))->from('core.entity_attributes as a')->whereColumn('a.entity_id', 'e.id')->where('a.key', 'stage')->whereRaw("a.value #>> '{}' = ?", [$data['stage']]));
            }
            if (! empty($data['sector'])) {
                $query->whereExists(fn ($s) => $s->select(DB::raw(1))->from('core.entity_attributes as a')->whereColumn('a.entity_id', 'e.id')->where('a.key', 'sector')->whereRaw('a.value @> ?::jsonb', [json_encode([$data['sector']], JSON_UNESCAPED_UNICODE)]));
            }
            if (! empty($data['q'])) {
                $needle = '%'.mb_strtolower(trim($data['q'])).'%';
                $query->where(fn ($q) => $q->whereRaw('lower(e.name_en) like ?', [$needle])->orWhereRaw('lower(coalesce(e.name_ar, $$$$)) like ?', [$needle]));
            }
            $total = (clone $query)->count('e.id');
            $rows = $query->orderBy('e.name_en')->forPage($page, $perPage)->get(['e.id', 'e.code', 'e.name_en', 'e.name_ar']);
            $attrs = DB::table('core.entity_attributes')->whereIn('entity_id', $rows->pluck('id'))->whereIn('key', self::LIST)->get(['entity_id', 'key', 'value'])->groupBy('entity_id');

            return ['total' => $total, 'page' => $page, 'per_page' => $perPage, 'data' => $rows->map(fn ($r) => ['name' => $r->name_ar ?: $r->name_en, 'code' => $r->code] + ($attrs[$r->id] ?? collect())->mapWithKeys(fn ($a) => [$a->key => json_decode($a->value, true)])->all())->all()];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** GET /api/v1/startups/stats */
    public function stats(): JsonResponse
    {
        $payload = Cache::remember('startups-stats:'.DataVersion::token(), 1800, function () {
            $scalar = fn (string $key) => DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')->where('e.type', 'startup')->where('a.key', $key)
                ->selectRaw("a.value #>> '{}' as label, count(*) as n")->groupBy('label')->orderByDesc('n')->get()->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->all();
            $sectors = DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')->where('e.type', 'startup')->where('a.key', 'sector')
                ->selectRaw('jsonb_array_elements_text(a.value) as label, count(*) as n')->groupBy('label')->orderByDesc('n')->get()->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->all();
            $subsectors = DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')->where('e.type', 'startup')->where('a.key', 'subsector')
                ->selectRaw('jsonb_array_elements_text(a.value) as label, count(*) as n')->groupBy('label')->orderByDesc('n')->limit(20)->get()->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->all();
            $seeking = DB::table('core.entity_attributes as a')->join('core.entities as e', 'e.id', '=', 'a.entity_id')->where('e.type', 'startup')->where('a.key', 'seeking')
                ->selectRaw('jsonb_array_elements_text(a.value) as label, count(*) as n')->groupBy('label')->orderByDesc('n')->get()->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->n])->all();

            $latest = fn (string $key) => DB::table('core.observations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->where('i.key', $key)->orderByDesc('o.period')->value('o.value');

            return [
                'total' => (int) DB::table('core.entities')->where('type', 'startup')->count(),
                'platform_count' => ($v = $latest('startups.platform_count')) !== null ? (int) $v : null,
                'platform_links' => ($v = $latest('startups.platform_links')) !== null ? (int) $v : null,
                'by_stage' => $scalar('stage'), 'by_sector' => $sectors, 'by_subsector' => $subsectors, 'by_legal_type' => $scalar('legal_type'), 'by_status' => $scalar('activity_status'),
                'by_employees' => $scalar('employees'), 'by_founded_year' => collect($scalar('founded_year'))->sortBy('label')->values()->all(), 'seeking' => $seeking, 'by_funding' => $scalar('funding_sought'),
            ];
        });

        return response()->json($payload + ['dataset' => $this->dataset()]);
    }

    /** @return array<string, mixed>|null */
    private function dataset(): ?array
    {
        $d = Dataset::with('source')->where('key', 'startups.oman_hub')->first();

        return $d ? (new DatasetResource($d))->resolve() : null;
    }
}
