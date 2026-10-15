<?php

namespace App\Core\Ingestion;

use App\Core\Freshness\DatasetStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * يزامن تعريفات الكود (المصادر والمجموعات والمؤشرات) إلى قاعدة البيانات.
 * الكود هو المرجع: لا يلمس الحالة ولا الطوابع الزمنية لمجموعة موجودة.
 * يعمل بدور المالك (admin) لأنه يكتب في core.
 */
final class DefinitionSync
{
    public function __construct(private readonly ConnectorRegistry $registry) {}

    /** @return array{datasets: int, indicators: int} */
    public function sync(): array
    {
        $datasets = 0;
        $indicators = 0;

        DB::connection('admin')->transaction(function ($db) use (&$datasets, &$indicators) {
            $connectors = $this->registry->all();
            $defined = array_map(fn ($c) => $c->definition()->key, $connectors);

            foreach ($connectors as $connector) {
                $def = $connector->definition();
                $s = $def->source;

                $db->table('core.sources')->upsert([[
                    'key' => $s->key, 'name_ar' => $s->nameAr, 'name_en' => $s->nameEn,
                    'publisher' => $s->publisher, 'url' => $s->url, 'license' => $s->license,
                    'reliability' => $s->reliability, 'updated_at' => now(),
                ]], ['key'], ['name_ar', 'name_en', 'publisher', 'url', 'license', 'reliability', 'updated_at']);

                $sourceId = $db->table('core.sources')->where('key', $s->key)->value('id');

                $exists = $db->table('ops.datasets')->where('key', $def->key)->exists();
                $fields = [
                    'source_id' => $sourceId, 'name_ar' => $def->nameAr, 'name_en' => $def->nameEn,
                    'section' => $def->section, 'connector' => $connector::class,
                    'reliability' => $def->reliability, 'interval_minutes' => $def->intervalMinutes,
                    'updated_at' => now(),
                ];
                if ($exists) {
                    $db->table('ops.datasets')->where('key', $def->key)->update($fields);
                } else {
                    $db->table('ops.datasets')->insert($fields + [
                        'key' => $def->key, 'status' => DatasetStatus::Pending->value, 'next_check_at' => now(),
                    ]);
                }
                $datasetId = $db->table('ops.datasets')->where('key', $def->key)->value('id');
                $datasets++;

                foreach ($def->indicators as $i) {
                    // مؤشر يملكه الآن مجموعة أخرى ما زالت معرّفة في الكود = تعارض. أما مالك لم يعد معرّفًا (فُصلت المجموعة) فيُعاد الإسناد.
                    $owner = $db->table('core.indicators as ci')->join('ops.datasets as cd', 'cd.id', '=', 'ci.dataset_id')
                        ->where('ci.key', $i->key)->where('cd.key', '!=', $def->key)->whereIn('cd.key', $defined)->value('cd.key');
                    if ($owner !== null) {
                        throw new \RuntimeException("المؤشر {$i->key} معرَّف في المجموعة {$owner} ولا يجوز تعريفه ثانيةً في {$def->key}.");
                    }

                    $db->table('core.indicators')->upsert([[
                        'key' => $i->key, 'dataset_id' => $datasetId, 'name_ar' => $i->nameAr, 'name_en' => $i->nameEn,
                        'unit' => $i->unit, 'frequency' => $i->frequency, 'description' => $i->description,
                        'config' => json_encode(['rules' => array_map(fn ($r) => ['name' => $r->name()] + $r->describe(), $i->rules)], JSON_UNESCAPED_UNICODE),
                        'updated_at' => now(),
                    ]], ['key'], ['dataset_id', 'name_ar', 'name_en', 'unit', 'frequency', 'description', 'config', 'updated_at']);
                    $indicators++;
                }
            }

            // مجموعات حُذفت من الكود تُعطَّل ولا تُحذف (بياناتها وتاريخها يبقيان)؛ فلا يرسلها المُجدوِل بعد الآن.
            $orphans = $db->table('ops.datasets')->whereNotIn('key', $defined)->where('status', '!=', DatasetStatus::Disabled->value)
                ->update(['enabled' => false, 'status' => DatasetStatus::Disabled->value, 'updated_at' => now()]);
            if ($orphans > 0) {
                Log::warning("عُطِّلت {$orphans} مجموعة لم تعد معرّفة في الكود.");
            }
        });

        return ['datasets' => $datasets, 'indicators' => $indicators];
    }
}
