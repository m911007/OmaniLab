<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChartRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ChartRequestController extends Controller
{
    /** @return array<string, mixed> */
    public static function present(ChartRequest $r): array
    {
        return [
            'id' => $r->id, 'chart_key' => $r->chart_key, 'chart_title' => $r->chart_title, 'page_path' => $r->page_path,
            'message' => $r->message, 'status' => $r->status, 'agent_response' => $r->agent_response,
            'handled_at' => $r->handled_at?->toIso8601String(), 'created_at' => $r->created_at?->toIso8601String(),
            'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
        ];
    }

    /** المشرف يرى كل الطلبات، وغيره يرى طلباته. */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $query = ChartRequest::with('user:id,name')->latest('id')->limit(200);
        if (! $user->is_admin) {
            $query->where('user_id', $user->id);
        }
        if ($request->filled('chart_key')) {
            $query->where('chart_key', (string) $request->query('chart_key'));
        }

        return response()->json(['data' => $query->get()->map(fn (ChartRequest $r) => self::present($r))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chart_key' => ['required', 'string', 'max:300'],
            'chart_title' => ['required', 'string', 'max:300'],
            'page_path' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $r = ChartRequest::create($data + ['user_id' => $request->user()?->getAuthIdentifier()]);
        $r->load('user:id,name');

        return response()->json(['data' => self::present($r)], 201);
    }

    /** تغيير الحالة (المشرف) — يدويًا إن لم ينفّذ الوكيل. */
    public function update(Request $request, ChartRequest $chartRequest): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);
        $data = $request->validate(['status' => ['required', Rule::in(ChartRequest::STATUSES)], 'agent_response' => ['nullable', 'string', 'max:4000']]);
        $chartRequest->update($data + ['handled_at' => in_array($data['status'], ['done', 'rejected'], true) ? now() : null]);

        return response()->json(['data' => self::present($chartRequest->load('user:id,name'))]);
    }
}
