<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ChartRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** واجهة وكيل الذكاء الاصطناعي: يقرأ الطلبات المعلّقة، وينفّذها، ويسجّل رده، ويعدّل الشروحات. محمية برمز Bearer. */
class AgentController extends Controller
{
    public function requests(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'pending');
        abort_unless(in_array($status, ChartRequest::STATUSES, true), 422, 'حالة غير صحيحة.');
        $rows = ChartRequest::with('user:id,name')->where('status', $status)->orderBy('id')->limit(50)->get();

        return response()->json(['data' => $rows->map(fn (ChartRequest $r) => ChartRequestController::present($r))->all()]);
    }

    public function updateRequest(Request $request, ChartRequest $chartRequest): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['in_progress', 'done', 'rejected'])], 'response' => ['nullable', 'string', 'max:4000']]);
        $chartRequest->update([
            'status' => $data['status'],
            'agent_response' => $data['response'] ?? $chartRequest->agent_response,
            'handled_at' => $data['status'] === 'in_progress' ? null : now(),
        ]);

        return response()->json(['data' => ChartRequestController::present($chartRequest->load('user:id,name'))]);
    }

    public function explanation(Request $request): JsonResponse
    {
        $key = (string) $request->validate(['chart_key' => ['required', 'string', 'max:300']])['chart_key'];
        $explanation = $request->validate(ChartExplanationController::rules());

        return response()->json(['data' => ChartExplanationController::save($key, $explanation, null, 'agent')]);
    }
}
