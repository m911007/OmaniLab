<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Prices\DbCurrencyConverter;
use App\Modules\ValueChain\Benchmark;
use App\Modules\ValueChain\UploadParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * أسعار موردي الجهة ومقارنتها بالسوق. كل طلب مقيّد بجهة المستخدم (رأس X-Org-Id أو أول عضوية)، فلا ترى جهة أسعار غيرها أبدًا.
 * الإضافة لكل الأعضاء، والحذف لمن أضاف السعر أو لمدير الجهة.
 */
class OrgPriceController extends Controller
{
    public function __construct(private Benchmark $benchmark) {}

    /** @return array{organization_id: int, role: string, name_ar: string} */
    private function org(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();
        $m = collect($user->memberships());
        $wanted = $request->header('X-Org-Id') ?: $request->query('org_id');
        $row = $wanted ? $m->firstWhere('organization_id', (int) $wanted) : $m->first();
        abort_if(! $row, 403, 'حسابك غير مرتبط بجهة؛ يضيفك مدير النظام إلى جهتك.');

        return $row;
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['memberships' => $request->user()->memberships()]);
    }

    public function index(Request $request): JsonResponse
    {
        $org = $this->org($request);
        $rows = DB::table('app.supplier_prices as p')->leftJoin('intel.chain_nodes as n', 'n.id', '=', 'p.node_id')->leftJoin('intel.value_chains as c', 'c.id', '=', 'n.chain_id')
            ->where('p.organization_id', $org['organization_id'])->orderByDesc('p.price_date')->orderByDesc('p.id')->limit(500)
            ->get(['p.*', 'n.name_ar as node_name', 'n.unit as node_unit', 'c.slug as chain_slug', 'c.name_ar as chain_name']);

        return response()->json(['organization' => $org, 'data' => $rows]);
    }

    public function store(Request $request, DbCurrencyConverter $fx): JsonResponse
    {
        $org = $this->org($request);
        $d = $request->validate($this->rules());
        $id = DB::table('app.supplier_prices')->insertGetId($this->row($d, $org['organization_id'], $request->user()->id, 'manual', $fx));

        return response()->json(['id' => $id], 201);
    }

    /** رفع أسعار بملف CSV/XLSX بأعمدة: التاريخ، المنتج، المورد، الدولة، السعر، العملة، الوحدة، الكمية، شروط التسليم، رمز HS، العقدة. */
    public function upload(Request $request, DbCurrencyConverter $fx): JsonResponse
    {
        $org = $this->org($request);
        $request->validate(['file' => ['required', 'file', 'max:5120'], 'node_id' => ['nullable', 'integer']]);
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'الصيغ المقبولة: xlsx أو csv.']);
        }
        $data = (new UploadParser)->rows($file->getRealPath(), $ext);
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($data) ?? []);
        $map = ['date' => ['date', 'التاريخ', 'price_date'], 'product' => ['product', 'المنتج', 'product_label'], 'supplier' => ['supplier', 'المورد', 'supplier_name'], 'country' => ['country', 'الدولة', 'supplier_country'],
            'price' => ['price', 'السعر'], 'currency' => ['currency', 'العملة'], 'unit' => ['unit', 'الوحدة'], 'quantity' => ['quantity', 'الكمية'], 'incoterm' => ['incoterm', 'شروط التسليم'], 'hs' => ['hs', 'hs_code', 'رمز hs'], 'spec' => ['spec', 'المواصفة']];
        $col = [];
        foreach ($map as $k => $names) {
            foreach ($names as $n) {
                if (($i = array_search($n, $header, true)) !== false) {
                    $col[$k] = $i;
                    break;
                }
            }
        }
        foreach (['date', 'product', 'supplier', 'price', 'currency', 'unit'] as $req) {
            if (! isset($col[$req])) {
                throw ValidationException::withMessages(['file' => "عمود مطلوب مفقود: {$req}"]);
            }
        }
        $ok = 0;
        $warnings = [];
        $parser = new UploadParser;
        foreach ($data as $i => $r) {
            $get = fn ($k) => isset($col[$k]) ? trim((string) ($r[$col[$k]] ?? '')) : null;
            $date = $parser->period($get('date'), 'D');
            $priceVal = str_replace(',', '', (string) $get('price'));
            if (! $date || ! is_numeric($priceVal) || (float) $priceVal <= 0 || ! preg_match('/^[A-Za-z]{3}$/', (string) $get('currency'))) {
                $warnings[] = 'السطر '.($i + 2).': تاريخ أو سعر أو عملة غير صالحة.';

                continue;
            }
            DB::table('app.supplier_prices')->insert($this->row([
                'product_label' => $get('product'), 'supplier_name' => $get('supplier'), 'supplier_country' => $get('country') ? strtoupper((string) $get('country')) : null, 'price' => (float) $priceVal,
                'currency' => strtoupper((string) $get('currency')), 'unit' => $get('unit'), 'quantity' => is_numeric($get('quantity')) ? (float) $get('quantity') : null, 'incoterm' => $get('incoterm') ?: null,
                'hs_code' => $get('hs') ?: null, 'spec' => $get('spec') ?: null, 'price_date' => $date, 'node_id' => $request->input('node_id'),
            ], $org['organization_id'], $request->user()->id, 'upload', $fx));
            $ok++;
        }

        return response()->json(['inserted' => $ok, 'warnings' => array_slice($warnings, 0, 50)], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $org = $this->org($request);
        $row = DB::table('app.supplier_prices')->where('id', $id)->where('organization_id', $org['organization_id'])->first();
        abort_if(! $row, 404);
        abort_unless($row->created_by === $request->user()->id || $org['role'] === 'org_admin', 403, 'الحذف لمن أضاف السعر أو لمدير الجهة.');
        DB::table('app.supplier_prices')->where('id', $id)->delete();

        return response()->json(['ok' => true]);
    }

    /** GET /intel/org/benchmark?price_id=&freight_pct=&duty_pct= — مقارنة سعر واحد بالسوق والتكلفة المتوقعة وسلوك المورد، مع حفظ لقطة. */
    public function benchmark(Request $request): JsonResponse
    {
        $org = $this->org($request);
        $d = $request->validate(['price_id' => ['required', 'integer'], 'freight_pct' => ['nullable', 'numeric', 'between:0,200'], 'duty_pct' => ['nullable', 'numeric', 'between:0,100']]);
        $price = DB::table('app.supplier_prices')->where('id', $d['price_id'])->where('organization_id', $org['organization_id'])->first();
        abort_if(! $price, 404);
        $result = $this->benchmark->evaluate($price, ['freight_pct' => (float) ($d['freight_pct'] ?? 10), 'duty_pct' => (float) ($d['duty_pct'] ?? 5)]);
        $history = DB::table('app.supplier_prices')->where('organization_id', $org['organization_id'])->where('supplier_name', $price->supplier_name)->where('product_label', $price->product_label)
            ->where('node_id', $price->node_id)->orderBy('price_date')->get()->all();
        $result['supplier_behaviour'] = $this->benchmark->passThrough($history);
        $result['peer_index'] = $price->node_id ? DB::table('intel.supplier_price_index')->where('node_id', $price->node_id)->orderByDesc('month')->first() : null;
        $hashable = $result;
        unset($hashable['computed_at']);
        DB::table('intel.benchmark_results')->insertOrIgnore(['organization_id' => $org['organization_id'], 'price_id' => $price->id, 'result_hash' => hash('sha256', json_encode($hashable)), 'result' => json_encode($result, JSON_UNESCAPED_UNICODE), 'computed_by' => $request->user()->id, 'computed_at' => now()]);

        return response()->json($result);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'node_id' => ['nullable', 'integer', 'exists:pgsql.intel.chain_nodes,id'], 'hs_code' => ['nullable', 'regex:/^\d{4,10}$/'], 'product_label' => ['required', 'string', 'max:250'], 'spec' => ['nullable', 'string', 'max:2000'],
            'supplier_name' => ['required', 'string', 'max:200'], 'supplier_country' => ['nullable', 'regex:/^[A-Z]{3}$/'], 'price' => ['required', 'numeric', 'gt:0'], 'currency' => ['required', 'regex:/^[A-Z]{3}$/'],
            'unit' => ['required', 'string', 'max:40'], 'quantity' => ['nullable', 'numeric', 'gt:0'], 'incoterm' => ['nullable', Rule::in(['FOB', 'CFR', 'CIF', 'EXW', 'DAP', 'DDP', 'FCA'])],
            'price_date' => ['required', 'date'], 'valid_until' => ['nullable', 'date'], 'document_ref' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @param  array<string, mixed>  $d @return array<string, mixed> */
    private function row(array $d, int $orgId, int $userId, string $source, DbCurrencyConverter $fx): array
    {
        return [
            'organization_id' => $orgId, 'node_id' => $d['node_id'] ?? null, 'hs_code' => $d['hs_code'] ?? null, 'product_label' => mb_substr((string) $d['product_label'], 0, 250), 'spec' => $d['spec'] ?? null,
            'supplier_name' => mb_substr((string) $d['supplier_name'], 0, 200), 'supplier_country' => $d['supplier_country'] ?? null, 'price' => $d['price'], 'currency' => $d['currency'], 'unit' => $d['unit'],
            'quantity' => $d['quantity'] ?? null, 'incoterm' => $d['incoterm'] ?? null, 'price_date' => $d['price_date'], 'valid_until' => $d['valid_until'] ?? null, 'source' => $source,
            'document_ref' => $d['document_ref'] ?? null, 'price_omr_per_unit' => $fx->toOmr((float) $d['price'], (string) $d['currency']), 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ];
    }
}
