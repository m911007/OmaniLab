<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Core\Insights\ScorecardBuilder;
use App\Core\Insights\SignalMonitor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class InsightController extends Controller
{
    /** بطاقة أداء عُمان: GET /api/v1/insights/scorecard — نقاط القوة والتحديات والفرص من البيانات المعتمدة. */
    public function scorecard(ScorecardBuilder $builder): JsonResponse
    {
        return response()->json(Cache::remember('scorecard:'.DataVersion::token(), 1800, fn () => $builder->build()));
    }

    /** إشارات السوق النشطة الآن: GET /api/v1/insights/signals */
    public function signals(SignalMonitor $monitor): JsonResponse
    {
        return response()->json(['data' => Cache::remember('signals:'.DataVersion::token().':'.now()->format('Y-m-d-H'), 900, fn () => $monitor->active())]);
    }
}
