<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Core\Insights\CrossCheck;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CrossCheckController extends Controller
{
    /** فهرس القياسات متعددة المصادر وحكمها لكيان: GET /api/v1/insights/crosschecks?entity=OMN */
    public function index(Request $request, CrossCheck $check): JsonResponse
    {
        $entity = strtoupper($request->validate(['entity' => ['nullable', 'string', 'max:32']])['entity'] ?? 'OMN');

        return response()->json(['data' => Cache::remember("xcheck:index:{$entity}:".DataVersion::token(), 1800, fn () => $check->index($entity))]);
    }

    /** تفصيل المصادر المتاحة لمؤشر: GET /api/v1/insights/crosschecks/detail?indicator=imf.LP&entity=OMN */
    public function detail(Request $request, CrossCheck $check): JsonResponse
    {
        $data = $request->validate(['indicator' => ['required', 'string', 'max:128'], 'entity' => ['nullable', 'string', 'max:32']]);
        $entity = strtoupper($data['entity'] ?? 'OMN');

        $payload = Cache::remember("xcheck:detail:{$entity}:{$data['indicator']}:".DataVersion::token(), 1800, fn () => $check->detail($data['indicator'], $entity));

        return $payload === null ? response()->json(['message' => 'لا مصادر بديلة لهذا المؤشر.'], 404) : response()->json($payload);
    }
}
