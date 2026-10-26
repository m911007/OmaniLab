<?php

namespace App\Console;

use App\Modules\ValueChain\ChainRepository;
use Illuminate\Console\Attributes\AsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

#[AsCommand(name: 'chains:seed', description: 'تحميل سلاسل القيمة الموثّقة من database/data/chains (لا يستبدل سلسلة عدّلها محلل إلا بـ --force)')]
final class SeedChainsCommand extends Command
{
    protected $signature = 'chains:seed {slug? : سلسلة واحدة} {--force : استبدال سلسلة موجودة حتى لو عُدّلت}';

    public function handle(ChainRepository $repo): int
    {
        $dir = database_path('data/chains');
        $sources = json_decode((string) file_get_contents("{$dir}/_sources.json"), true, flags: JSON_THROW_ON_ERROR);
        foreach (glob("{$dir}/*.json") ?: [] as $file) {
            if (str_starts_with(basename($file), '_')) {
                continue;
            }
            $def = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            if ($this->argument('slug') && $def['slug'] !== $this->argument('slug')) {
                continue;
            }
            $existing = DB::table('intel.value_chains')->where('slug', $def['slug'])->first();
            if ($existing && ! $this->option('force')) {
                $this->line("تخطّي {$def['slug']}: موجودة (النسخة {$existing->version}). استخدم --force للاستبدال.");

                continue;
            }
            DB::transaction(function () use ($def, $sources, $existing, $repo): void {
                if ($existing) {
                    DB::table('intel.value_chains')->where('id', $existing->id)->delete();
                }
                $chainId = DB::table('intel.value_chains')->insertGetId([
                    'slug' => $def['slug'], 'name_ar' => $def['name_ar'], 'name_en' => $def['name_en'], 'description' => $def['description'] ?? null,
                    'route_note' => $def['route_note'] ?? null, 'status' => 'review', 'created_at' => now(), 'updated_at' => now(),
                ]);
                $ids = [];
                foreach ($def['nodes'] as $n) {
                    $ids[$n['slug']] = DB::table('intel.chain_nodes')->insertGetId([
                        'chain_id' => $chainId, 'slug' => $n['slug'], 'name_ar' => $n['name_ar'], 'name_en' => $n['name_en'], 'kind' => $n['kind'], 'stage' => $n['stage'],
                        'unit' => $n['unit'], 'hs_codes' => '{'.implode(',', $n['hs_codes']).'}', 'notes' => $n['notes'],
                    ]);
                    foreach ($n['series'] as $p => $s) {
                        $seriesId = $this->series($s);
                        DB::table('intel.node_series')->insert(['node_id' => $ids[$n['slug']], 'series_id' => $seriesId, 'role' => $s['role'], 'priority' => $p + 1]);
                    }
                }
                foreach ($def['edges'] as $e) {
                    if (! isset($ids[$e['from']], $ids[$e['to']])) {
                        throw new RuntimeException("حافة إلى عقدة غير معرّفة: {$e['from']} → {$e['to']}");
                    }
                    $edgeId = DB::table('intel.chain_edges')->insertGetId([
                        'chain_id' => $chainId, 'from_node_id' => $ids[$e['from']], 'to_node_id' => $ids[$e['to']],
                        'weight' => $e['weight'], 'weight_min' => $e['weight_min'], 'weight_max' => $e['weight_max'], 'weight_basis' => $e['weight_basis'],
                        'conversion_factor' => $e['conversion_factor'], 'conversion_min' => $e['conversion_min'], 'conversion_max' => $e['conversion_max'], 'conversion_unit' => $e['conversion_unit'],
                        'notes' => $e['notes'], 'logistics_note' => $e['logistics_note'], 'updated_at' => now(),
                    ]);
                    foreach ($e['sources'] as $src) {
                        $s = $sources[$src['key']] ?? throw new RuntimeException("مصدر غير معرّف: {$src['key']}");
                        DB::table('intel.edge_sources')->insert(['edge_id' => $edgeId, 'applies_to' => $src['applies_to'], 'title' => $s['title'], 'url' => $s['url'], 'publisher' => $s['publisher'], 'year' => $s['year'], 'kind' => $s['kind'], 'reliability' => $s['reliability'], 'excerpt' => $s['excerpt']]);
                    }
                }
                $repo->revise($chainId, null, 'تحميل أولي من database/data/chains (تقديرات موثّقة بانتظار مراجعة المحلل)');
            });
            $this->info("حُمّلت سلسلة {$def['slug']}: ".count($def['nodes']).' عقد، '.count($def['edges']).' حواف.');
        }

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $s */
    private function series(array $s): int
    {
        $row = ['label_ar' => $s['label_ar'], 'price_type' => $s['price_type'], 'confidence' => $s['confidence'], 'market' => $s['market'], 'incoterm' => $s['incoterm'],
            'unit' => $s['unit'], 'currency' => $s['currency'], 'frequency' => $s['frequency'], 'license_note' => $s['license_note'], 'updated_at' => now()];
        $id = DB::table('intel.price_series')->where('indicator_key', $s['indicator'])->where('entity_code', $s['entity'])->value('id');
        if ($id) {
            DB::table('intel.price_series')->where('id', $id)->update($row);

            return (int) $id;
        }

        return (int) DB::table('intel.price_series')->insertGetId($row + ['indicator_key' => $s['indicator'], 'entity_code' => $s['entity'], 'created_at' => now()]);
    }
}
