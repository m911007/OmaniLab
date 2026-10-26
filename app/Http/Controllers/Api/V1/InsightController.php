<?php

namespace App\Http\Controllers\Api\V1;

use App\Core\Api\DataVersion;
use App\Core\Insights\ReadingBuilder;
use App\Core\Insights\ScorecardBuilder;
use App\Core\Insights\SignalMonitor;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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

    /**
     * قراءات المؤشرات لصانع القرار: GET /api/v1/readings?indicators=imf.NGDP_RPCH,ncsi.CPI_INFLATION أو ?set=pulse
     * لكل مؤشر: آخر قيمة فعلية والسابقة، وموقعها في آخر عشر سنوات وبين دول الخليج، وحكم وجملة مولَّدة.
     */
    public function readings(Request $request, ReadingBuilder $builder): JsonResponse
    {
        $data = $request->validate([
            'indicators' => ['required_without:set', 'string', 'max:2000'],
            'set' => ['nullable', 'in:pulse'],
            'entity' => ['nullable', 'string', 'max:64'],
            'max_period' => ['nullable', 'string', 'regex:/^\d{4}(-\d{2}|-Q[1-4])?$/'],
            'polarity' => ['nullable', 'string', 'regex:/^(higher|lower|target:-?\d+(\.\d+)?)$/'],
            'omr' => ['nullable', 'boolean'],
        ]);
        $keys = isset($data['set'])
            ? array_values((array) config('indicator_meta.'.$data['set']))
            : array_slice(array_values(array_filter(array_map('trim', explode(',', (string) $data['indicators'])))), 0, 40);
        $entity = $data['entity'] ?? null;

        $options = array_filter(['max_period' => $data['max_period'] ?? null, 'polarity' => $data['polarity'] ?? null, 'omr' => (bool) ($data['omr'] ?? false)]);
        $suffix = $options === [] ? '' : '|'.json_encode($options);

        $readings = Cache::remember('readings:'.DataVersion::token().':'.md5(implode(',', $keys).'|'.$entity.$suffix), 1800, fn () => $builder->build($keys, $entity, $options));

        return response()->json(['data' => $readings]);
    }

    /**
     * سجل تصحيحات البيانات: GET /api/v1/corrections?entity=OMN
     * applied = صُحّحت آليًا قبل النشر، excluded = خطأ مصدر استُبعد بسبب موثّق، suspected = ينتظر المراجعة، dismissed = رُوجع وثبتت صحته.
     */
    public function corrections(Request $request): JsonResponse
    {
        $data = $request->validate(['entity' => ['nullable', 'string', 'max:64'], 'all' => ['nullable', 'boolean']]);
        $entity = strtoupper($data['entity'] ?? 'OMN');

        $rows = DB::table('ops.corrections as c')->leftJoin('core.indicators as i', 'i.key', '=', 'c.indicator_key')
            ->when(empty($data['all']), fn ($q) => $q->whereIn('c.entity_code', [$entity, 'WLD']))
            ->whereIn('c.status', ['applied', 'excluded', 'suspected', 'dismissed'])
            ->orderByDesc('c.detected_at')->orderBy('c.indicator_key')->orderBy('c.period')->limit(300)
            ->get(['c.indicator_key', 'i.name_ar', 'c.entity_code', 'c.period', 'c.kind', 'c.status', 'c.original', 'c.corrected', 'c.note', 'c.detected_at']);

        return response()->json(['data' => $rows]);
    }
}
