<?php

namespace App\Modules\ValueChain;

use Illuminate\Support\Facades\DB;

/** قراءة سلسلة القيمة كاملة (عقد، سلاسل أسعار بآخر قيمة وحداثتها، حواف بمصادرها، آخر تحقق إحصائي) وحفظ لقطات التدقيق. */
final class ChainRepository
{
    /** @return list<array<string, mixed>> */
    public function list(): array
    {
        return DB::table('intel.value_chains as c')
            ->selectRaw('c.id, c.slug, c.name_ar, c.name_en, c.status, c.version, c.is_demo, c.updated_at,
                (SELECT count(*) FROM intel.chain_nodes n WHERE n.chain_id = c.id) AS nodes,
                (SELECT count(*) FROM intel.chain_edges e WHERE e.chain_id = c.id) AS edges,
                (SELECT count(*) FROM intel.chain_edges e WHERE e.chain_id = c.id AND e.weight IS NULL) AS missing_weights,
                (SELECT count(DISTINCT r.node_id) FROM intel.regression_checks r WHERE r.chain_id = c.id AND r.flagged
                    AND r.computed_at = (SELECT max(r2.computed_at) FROM intel.regression_checks r2 WHERE r2.node_id = r.node_id)) AS flagged_nodes')
            ->orderBy('c.id')->get()->map(fn ($r) => (array) $r)->all();
    }

    /** @return array<string, mixed>|null */
    public function full(string $slug): ?array
    {
        $chain = DB::table('intel.value_chains')->where('slug', $slug)->first();
        if (! $chain) {
            return null;
        }
        $nodes = DB::table('intel.chain_nodes')->where('chain_id', $chain->id)->orderBy('stage')->orderBy('id')->get();
        $links = DB::table('intel.node_series as ns')->join('intel.price_series as s', 's.id', '=', 'ns.series_id')
            ->whereIn('ns.node_id', $nodes->pluck('id'))->orderBy('ns.priority')->get(['ns.node_id', 'ns.role', 'ns.priority', 's.*']);
        $latest = $this->latest($links->map(fn ($l) => [$l->indicator_key, $l->entity_code])->all());

        $edges = DB::table('intel.chain_edges')->where('chain_id', $chain->id)->orderBy('to_node_id')->orderByDesc('weight')->get();
        $sources = DB::table('intel.edge_sources')->whereIn('edge_id', $edges->pluck('id'))->orderBy('id')->get()->groupBy('edge_id');
        $checks = DB::table('intel.regression_checks')->where('chain_id', $chain->id)->orderByDesc('computed_at')->get()->unique('node_id')->keyBy('node_id');

        return [
            'chain' => (array) $chain,
            'nodes' => $nodes->map(fn ($n) => [
                ...(array) $n,
                'hs_codes' => $this->pgArray($n->hs_codes),
                'series' => $links->where('node_id', $n->id)->values()->map(fn ($l) => [
                    'id' => $l->id, 'role' => $l->role, 'priority' => $l->priority, 'indicator_key' => $l->indicator_key, 'entity_code' => $l->entity_code,
                    'label_ar' => $l->label_ar, 'price_type' => $l->price_type, 'confidence' => $l->confidence, 'market' => $l->market, 'incoterm' => $l->incoterm,
                    'unit' => $l->unit, 'currency' => $l->currency, 'frequency' => $l->frequency, 'license_note' => $l->license_note, 'is_demo' => (bool) $l->is_demo,
                    'latest' => $latest["{$l->indicator_key}|{$l->entity_code}"] ?? null,
                ])->all(),
                'check' => isset($checks[$n->id]) ? [...(array) $checks[$n->id], 'results' => json_decode($checks[$n->id]->results, true)] : null,
            ])->all(),
            'edges' => $edges->map(fn ($e) => [...(array) $e, 'sources' => ($sources[$e->id] ?? collect())->map(fn ($s) => (array) $s)->values()->all()])->all(),
        ];
    }

    /**
     * آخر قيمة لكل (مؤشر، كيان) مع وقت آخر تحديث للمجموعة وحالتها — كي يُعرض مع كل رقم مصدره وتاريخه.
     *
     * @param  list<array{0: string, 1: string}>  $pairs
     * @return array<string, array<string, mixed>>
     */
    public function latest(array $pairs): array
    {
        $reader = new SeriesReader;
        $out = [];
        foreach ($pairs as [$key, $entity]) {
            if ($l = $reader->latest($key, $entity)) {
                $out["{$key}|{$entity}"] = $l;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> لقطة كاملة قابلة لإعادة البناء */
    public function snapshot(int $chainId): array
    {
        $slug = (string) DB::table('intel.value_chains')->where('id', $chainId)->value('slug');
        $full = $this->full($slug) ?? [];
        foreach ($full['nodes'] ?? [] as $i => $n) {
            unset($full['nodes'][$i]['check']);
            foreach ($n['series'] as $j => $s) {
                unset($full['nodes'][$i]['series'][$j]['latest']);
            }
        }

        return $full;
    }

    /** يرفع رقم النسخة ويحفظ لقطة بعد كل تعديل (قابلية التدقيق). */
    public function revise(int $chainId, ?int $userId, string $note): int
    {
        $version = (int) DB::table('intel.value_chains')->where('id', $chainId)->value('version');
        $hasRevisions = DB::table('intel.chain_revisions')->where('chain_id', $chainId)->exists();
        if ($hasRevisions) {
            $version++;
            DB::table('intel.value_chains')->where('id', $chainId)->update(['version' => $version, 'updated_by' => $userId, 'updated_at' => now()]);
        }
        DB::table('intel.chain_revisions')->insert(['chain_id' => $chainId, 'version' => $version, 'snapshot' => json_encode($this->snapshot($chainId), JSON_UNESCAPED_UNICODE), 'note' => mb_substr($note, 0, 300), 'edited_by' => $userId, 'created_at' => now()]);

        return $version;
    }

    /** @return list<string> */
    private function pgArray(mixed $v): array
    {
        if (is_array($v)) {
            return $v;
        }
        $s = trim((string) $v, '{}');

        return $s === '' ? [] : array_map(fn ($x) => trim($x, '"'), explode(',', $s));
    }
}
