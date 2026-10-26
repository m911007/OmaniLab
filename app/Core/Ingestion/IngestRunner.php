<?php

namespace App\Core\Ingestion;

use App\Core\Freshness\DatasetStatus;
use App\Core\Freshness\FreshnessService;
use App\Core\Ingestion\Data\ParsedAttribute;
use App\Core\Ingestion\Data\ParsedEntity;
use App\Core\Ingestion\Data\ParsedRecord;
use App\Core\Ingestion\Data\ParsedRelation;
use App\Core\Quality\Exclusions;
use App\Models\Dataset;
use App\Models\IngestRun;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * المرحلة الأولى من الخط: فحص ← جلب ← تحليل ← تخزين في staging.
 * يعمل بدور ingest_writer: لا يكتب في core إطلاقًا. الاعتماد من اختصاص Promoter.
 */
final class IngestRunner
{
    private const CHUNK = 2000;

    public function __construct(
        private readonly ConnectorRegistry $registry,
        private readonly FreshnessService $freshness,
        private readonly EntityNormaliser $normaliser,
        private readonly Exclusions $exclusions,
    ) {}

    /**
     * النتيجة: run بحالة unchanged | staged | failed. حالة staged تعني جاهز للتحقق والاعتماد.
     */
    public function run(Dataset $dataset, string $trigger = 'schedule', bool $force = false): IngestRun
    {
        $run = IngestRun::create(['dataset_id' => $dataset->id, 'trigger' => $trigger, 'status' => 'running', 'started_at' => now()]);

        try {
            $connector = $this->registry->forDataset($dataset);

            $probe = $connector->probe();
            $revision = $connector->revision();
            // مراجعة المحلّل جزء من البصمتين: رفعها يُعيد المعالجة ولو لم يتغير المصدر.
            $token = $probe->token === null ? null : $probe->token.'#r'.$revision;
            $run->update(['probe_token' => $token, 'meta' => ['version' => $probe->version, 'published_at' => $probe->publishedAt?->toIso8601String()]]);

            if (! $force && $token !== null && $dataset->status !== DatasetStatus::Pending && $token === $dataset->probe_token) {
                return $this->finishUnchanged($dataset, $run, 'probe');
            }

            $payload = $connector->fetch();
            $hash = hash('sha256', $payload->sha256.'|r'.$revision);
            $run->update(['payload_hash' => $hash]);

            if (! $force && $hash === $dataset->payload_hash) {
                $dataset->update(['probe_token' => $token]);

                return $this->finishUnchanged($dataset, $run, 'hash');
            }

            if (! $force && $this->sameAsLastQuarantined($dataset, $hash)) {
                return $this->finishUnchanged($dataset, $run, 'quarantined_same');
            }

            DB::table('raw.payloads')->insert([
                'run_id' => $run->id, 'dataset_id' => $dataset->id, 'sha256' => $payload->sha256,
                'bytes' => $payload->bytes(), 'content_type' => $payload->contentType, 'body' => $payload->body,
            ]);

            $count = $this->stage($run, $connector->parse($payload));

            $run->update(['status' => 'staged', 'records_count' => $count]);

            return $run->refresh();
        } catch (Throwable $e) {
            $run->update(['status' => 'failed', 'error' => mb_substr($e::class.': '.$e->getMessage(), 0, 2000), 'finished_at' => now()]);
            $this->freshness->markFailed($dataset->refresh(), $e->getMessage());

            return $run->refresh();
        }
    }

    private function finishUnchanged(Dataset $dataset, IngestRun $run, string $reason): IngestRun
    {
        $run->update(['status' => 'unchanged', 'finished_at' => now(), 'meta' => array_merge($run->meta ?? [], ['unchanged_by' => $reason])]);
        $this->freshness->markCheckedUnchanged($dataset->refresh());

        return $run->refresh();
    }

    private function sameAsLastQuarantined(Dataset $dataset, string $hash): bool
    {
        if ($dataset->status !== DatasetStatus::Quarantined) {
            return false;
        }
        $last = IngestRun::where('dataset_id', $dataset->id)->where('status', 'quarantined')->latest('id')->first();

        return $last?->payload_hash === $hash;
    }

    /**
     * تنظيف وتخزين: رموز الكيانات وأسماؤها تُوحَّد، والتكرار الحرفي (نفس المفتاح ونفس القيمة) يُطوى،
     * والتكرار المتعارض (نفس المفتاح بقيمتين) يُسجَّل في meta.conflicts فيحجز المُعتمِد الدفعة.
     *
     * @param  iterable<ParsedRecord|ParsedEntity|ParsedAttribute|ParsedRelation>  $items
     */
    private function stage(IngestRun $run, iterable $items): int
    {
        $records = [];
        $relations = [];
        $entities = [];
        $attributes = [];
        $attributeSeen = [];
        $entitySeen = [];
        $seen = [];
        $conflicts = [];
        $duplicates = 0;
        $total = 0;

        foreach ($items as $item) {
            if ($item instanceof ParsedEntity) {
                $code = $this->normaliser->code($item->code);
                if (isset($entitySeen[$code])) {
                    continue;
                }
                $entitySeen[$code] = true;
                $entities[] = ['run_id' => $run->id, 'code' => $code, 'type' => $item->type, 'name_en' => $this->normaliser->name($item->nameEn, $item->type), 'name_ar' => $item->nameAr];

                continue;
            }

            if ($item instanceof ParsedAttribute) {
                $code = $this->normaliser->code($item->entityCode);
                if ($item->value === null || $item->value === '' || isset($attributeSeen[$code."\x1f".$item->key])) {
                    continue;
                }
                $attributeSeen[$code."\x1f".$item->key] = true;
                $attributes[] = ['run_id' => $run->id, 'entity_code' => $code, 'key' => $item->key, 'value' => json_encode($item->value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)];

                continue;
            }

            if ($item instanceof ParsedRelation) {
                $src = $this->normaliser->code($item->sourceCode);
                $tgt = $this->normaliser->code($item->targetCode);
                if ($src === $tgt) {
                    continue;
                }
                $pair = $src.'>'.$tgt;
                $key = $item->indicatorKey."\x1f".$pair."\x1f".$item->period;
                if (array_key_exists($key, $seen)) {
                    if ($seen[$key] === $item->value) {
                        $duplicates++;
                    } elseif (count($conflicts) < 50) {
                        $conflicts[] = ['key' => "{$item->indicatorKey}/{$pair}/{$item->period}", 'values' => [$seen[$key], $item->value]];
                    }

                    continue;
                }
                $seen[$key] = $item->value;
                $relations[] = ['run_id' => $run->id, 'indicator_key' => $item->indicatorKey, 'source_code' => $src, 'target_code' => $tgt, 'period' => $item->period, 'value' => $item->value];
                $total++;
                if (count($relations) >= self::CHUNK) {
                    DB::table('staging.relations')->insert($relations);
                    $relations = [];
                }

                continue;
            }

            $entity = $this->normaliser->code($item->entityCode);
            // نقطة خاطئة في المصدر بسبب موثّق: لا تُنشر، ويبقى سببها في سجل التصحيحات
            $excluded = $this->exclusions->reason($item->indicatorKey, $entity, $item->period);
            if ($excluded !== null) {
                $this->exclusions->record($item->indicatorKey, $entity, $item->period, $item->value, $excluded);

                continue;
            }
            $key = $item->indicatorKey."\x1f".$entity."\x1f".$item->period;
            if (array_key_exists($key, $seen)) {
                if ($seen[$key] === $item->value) {
                    $duplicates++;
                } elseif (count($conflicts) < 50) {
                    $conflicts[] = ['key' => "{$item->indicatorKey}/{$entity}/{$item->period}", 'values' => [$seen[$key], $item->value]];
                }

                continue;
            }
            $seen[$key] = $item->value;

            $records[] = ['run_id' => $run->id, 'indicator_key' => $item->indicatorKey, 'entity_code' => $entity, 'period' => $item->period, 'value' => $item->value];
            $total++;

            if (count($records) >= self::CHUNK) {
                DB::table('staging.records')->insert($records);
                $records = [];
            }
        }

        if ($records !== []) {
            DB::table('staging.records')->insert($records);
        }
        if ($relations !== []) {
            DB::table('staging.relations')->insert($relations);
        }
        foreach (array_chunk($entities, self::CHUNK) as $chunk) {
            DB::table('staging.entities')->insert($chunk);
        }
        foreach (array_chunk($attributes, self::CHUNK) as $chunk) {
            DB::table('staging.attributes')->insert($chunk);
        }

        if ($duplicates > 0 || $conflicts !== []) {
            $run->update(['meta' => array_merge($run->meta ?? [], ['duplicates_collapsed' => $duplicates, 'conflicts' => $conflicts])]);
        }

        $run->update(['meta' => array_merge($run->refresh()->meta ?? [], ['entities_count' => count($entities), 'attributes_count' => count($attributes)])]);

        return $total;
    }
}
