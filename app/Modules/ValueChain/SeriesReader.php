<?php

namespace App\Modules\ValueChain;

use Illuminate\Support\Facades\DB;

/**
 * قراءة نقاط أي سلسلة سعرية من مصدرها: مؤشرات النظام المجلوبة آليًا (core.observations) أو السلاسل المرفوعة يدويًا (upload.*).
 * كل قراءة تعيد مع النقاط تاريخ آخر تحديث ومصدر المجموعة كي يُعرض مع كل رقم.
 */
final class SeriesReader
{
    public static function isUpload(string $key): bool
    {
        return str_starts_with($key, 'upload.');
    }

    /** @return array<string, float> فترة => قيمة (تصاعديًا) */
    public function points(string $key, string $entity, ?string $from = null): array
    {
        if (self::isUpload($key)) {
            return DB::table('intel.uploaded_points as p')->join('intel.uploaded_series as s', 's.id', '=', 'p.series_id')
                ->where('s.key', $key)->where('s.entity_code', $entity)->when($from, fn ($q) => $q->where('p.period', '>=', $from))
                ->orderBy('p.period')->pluck('p.value', 'p.period')->map(fn ($v) => (float) $v)->all();
        }

        return DB::table('core.observations as o')->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('i.key', $key)->where('e.code', $entity)->when($from, fn ($q) => $q->where('o.period', '>=', $from))
            ->orderBy('o.period')->pluck('o.value', 'o.period')->map(fn ($v) => (float) $v)->all();
    }

    /** @return array{period: string, value: float, updated_at: string, dataset: string, dataset_status: string, last_success_at: ?string, points: int}|null */
    public function latest(string $key, string $entity): ?array
    {
        if (self::isUpload($key)) {
            $s = DB::table('intel.uploaded_series')->where('key', $key)->where('entity_code', $entity)->first();
            if (! $s) {
                return null;
            }
            $p = DB::table('intel.uploaded_points as p')->join('intel.uploads as u', 'u.id', '=', 'p.upload_id')->where('p.series_id', $s->id)->orderByDesc('p.period')->first(['p.period', 'p.value', 'p.updated_at', 'u.file_name']);

            return $p ? ['period' => $p->period, 'value' => (float) $p->value, 'updated_at' => (string) $p->updated_at, 'dataset' => "رفع يدوي: {$s->source_name} ({$p->file_name})",
                'dataset_status' => 'manual', 'last_success_at' => (string) $p->updated_at, 'points' => DB::table('intel.uploaded_points')->where('series_id', $s->id)->count()] : null;
        }
        $ind = DB::table('core.indicators as i')->join('ops.datasets as d', 'd.id', '=', 'i.dataset_id')->where('i.key', $key)->first(['i.id', 'd.key as dataset', 'd.status', 'd.last_success_at']);
        $eid = DB::table('core.entities')->where('code', $entity)->value('id');
        if (! $ind || ! $eid) {
            return null;
        }
        $obs = DB::table('core.observations')->where('indicator_id', $ind->id)->where('entity_id', $eid)->orderByDesc('period')->first(['period', 'value', 'updated_at']);

        return $obs ? ['period' => $obs->period, 'value' => (float) $obs->value, 'updated_at' => (string) $obs->updated_at, 'dataset' => $ind->dataset, 'dataset_status' => $ind->status,
            'last_success_at' => $ind->last_success_at, 'points' => DB::table('core.observations')->where('indicator_id', $ind->id)->where('entity_id', $eid)->count()] : null;
    }

    public function exists(string $key, string $entity): bool
    {
        return $this->latest($key, $entity) !== null;
    }
}
