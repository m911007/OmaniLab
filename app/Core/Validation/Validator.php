<?php

namespace App\Core\Validation;

use App\Core\Ingestion\Data\DatasetDefinition;
use App\Core\Validation\Rules\Freshness;
use App\Core\Validation\Rules\WellFormed;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * يطبّق قواعد المجموعة (WellFormed, Freshness) ثم قواعد كل مؤشر على سجلات التشغيل في staging،
 * مقارنةً بالقيم المعتمدة سابقًا في core.
 */
final class Validator
{
    /**
     * @return list<Issue>
     */
    public function validate(
        int $runId,
        DatasetDefinition $definition,
        ?CarbonImmutable $incomingPublishedAt,
        ?CarbonImmutable $currentPublishedAt,
    ): array {
        $known = array_map(fn ($i) => $i->key, $definition->indicators);

        $byIndicator = [];
        $all = [];
        foreach (DB::table('staging.records')->where('run_id', $runId)->orderBy('id')
            ->select('indicator_key', 'entity_code', 'period', 'value')->cursor() as $row) {
            $rec = ['indicator' => $row->indicator_key, 'entity' => $row->entity_code, 'period' => $row->period, 'value' => (float) $row->value];
            $all[] = $rec;
            $byIndicator[$row->indicator_key][] = $rec;
        }

        foreach (DB::table('staging.relations')->where('run_id', $runId)->orderBy('id')
            ->select('indicator_key', 'source_code', 'target_code', 'period', 'value')->cursor() as $row) {
            $rec = ['indicator' => $row->indicator_key, 'entity' => $row->source_code.'>'.$row->target_code, 'period' => $row->period, 'value' => (float) $row->value];
            $all[] = $rec;
            $byIndicator[$row->indicator_key][] = $rec;
        }

        $issues = [];
        $extra = (int) DB::table('staging.entities')->where('run_id', $runId)->count() + (int) DB::table('staging.attributes')->where('run_id', $runId)->count();
        $datasetContext = new ValidationContext(null, $all, [], 0, $known, $incomingPublishedAt, $currentPublishedAt, $extra);
        foreach ([new WellFormed, new Freshness] as $rule) {
            array_push($issues, ...iterator_to_array($rule->check($datasetContext), false));
        }

        foreach ($definition->indicators as $indicator) {
            if ($indicator->rules === []) {
                continue;
            }
            $records = $byIndicator[$indicator->key] ?? [];
            $previous = $this->previousValues($indicator->key);
            $context = new ValidationContext($indicator->key, $records, $previous['values'], $previous['count'], $known);

            foreach ($indicator->rules as $rule) {
                array_push($issues, ...iterator_to_array($rule->check($context), false));
            }
        }

        return $issues;
    }

    /** @return array{values: array<string, float>, count: int} */
    private function previousValues(string $indicatorKey): array
    {
        $values = [];
        foreach (DB::table('core.relations as r')
            ->join('core.indicators as i', 'i.id', '=', 'r.indicator_id')
            ->join('core.entities as s', 's.id', '=', 'r.source_entity_id')
            ->join('core.entities as t', 't.id', '=', 'r.target_entity_id')
            ->where('i.key', $indicatorKey)
            ->select('s.code as src', 't.code as tgt', 'r.period', 'r.value')->cursor() as $r) {
            $values["{$r->src}>{$r->tgt}|{$r->period}"] = (float) $r->value;
        }
        $rows = DB::table('core.observations as o')
            ->join('core.indicators as i', 'i.id', '=', 'o.indicator_id')
            ->join('core.entities as e', 'e.id', '=', 'o.entity_id')
            ->where('i.key', $indicatorKey)
            ->select('e.code', 'o.period', 'o.value')->cursor();
        foreach ($rows as $row) {
            $values["{$row->code}|{$row->period}"] = (float) $row->value;
        }

        return ['values' => $values, 'count' => count($values)];
    }
}
