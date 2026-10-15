<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Dataset;
use App\Models\IngestRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    /** ملخص صحة المنصة: أعداد، حالات المجموعات، آخر تدقيق، وآخر تشغيلات. */
    public function __invoke(): JsonResponse
    {
        $counts = DB::selectOne(<<<'SQL'
            SELECT (SELECT count(*) FROM ops.datasets WHERE enabled) datasets,
                   (SELECT count(*) FROM core.indicators) indicators,
                   (SELECT reltuples::bigint FROM pg_class WHERE oid = 'core.observations'::regclass) observations,
                   (SELECT count(*) FROM core.entities e WHERE EXISTS (SELECT 1 FROM core.observations o WHERE o.entity_id = e.id)) entities
            SQL);

        $datasets = Dataset::query()->where('enabled', true)->get();

        return response()->json([
            'counts' => ['datasets' => (int) $counts->datasets, 'indicators' => (int) $counts->indicators, 'observations' => (int) $counts->observations, 'entities' => (int) $counts->entities],
            'by_status' => $datasets->groupBy(fn (Dataset $d) => $d->status->value)->map->count(),
            'last_checked_at' => $datasets->max('last_checked_at')?->toIso8601String(),
            'last_changed_at' => $datasets->max('last_changed_at')?->toIso8601String(),
            'audit' => Cache::get('audit:last'),
            'recent_runs' => IngestRun::with('dataset')->latest('id')->limit(12)->get()->map(fn (IngestRun $r) => [
                'id' => $r->id, 'dataset' => $r->dataset->key, 'dataset_name_ar' => $r->dataset->name_ar, 'status' => $r->status,
                'trigger' => $r->trigger, 'started_at' => $r->started_at->toIso8601String(), 'records_count' => $r->records_count,
                'promoted_count' => $r->promoted_count, 'error' => $r->error,
                'rejected_points' => $r->meta['rejected_points'] ?? 0, 'removed_points' => $r->meta['removed_points'] ?? 0,
            ]),
        ]);
    }
}
