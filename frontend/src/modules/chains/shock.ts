import type { ChainEdge, ChainNode } from './api';

export type WeightKey = 'weight' | 'weight_min' | 'weight_max';

/**
 * انتشار الصدمة السعرية في السلسلة: أثر العقدة = صدمتها الخارجية + Σ (وزن المدخل × أثر المدخل)، بترتيب المراحل.
 * الحواف بلا وزن لا تنقل الأثر، وتكاليف التحويل تبقى ثابتة ما لم تُصدَم مباشرة. الافتراض: انتقال كامل للتكلفة إلى السعر.
 */
export function propagate(nodes: ChainNode[], edges: ChainEdge[], shocks: Record<number, number>, key: WeightKey = 'weight'): Record<number, number> {
  const order = [...nodes].sort((a, b) => a.stage - b.stage || a.id - b.id);
  const incoming = new Map<number, ChainEdge[]>();
  for (const e of edges) incoming.set(e.to_node_id, [...(incoming.get(e.to_node_id) ?? []), e]);
  const impact: Record<number, number> = {};
  for (const n of order) {
    let v = shocks[n.id] ?? 0;
    for (const e of incoming.get(n.id) ?? []) {
      const w = e[key] ?? e.weight;
      if (w == null) continue;
      v += Number(w) * (impact[e.from_node_id] ?? 0);
    }
    impact[n.id] = v;
  }
  return impact;
}

/** الأثر المركزي ونطاقه (بالأوزان الدنيا والعليا) لكل عقدة. */
export function propagateRange(nodes: ChainNode[], edges: ChainEdge[], shocks: Record<number, number>): Record<number, { mid: number; low: number; high: number }> {
  const mid = propagate(nodes, edges, shocks, 'weight');
  const lo = propagate(nodes, edges, shocks, 'weight_min');
  const hi = propagate(nodes, edges, shocks, 'weight_max');
  const out: Record<number, { mid: number; low: number; high: number }> = {};
  for (const n of nodes) out[n.id] = { mid: mid[n.id], low: Math.min(lo[n.id], hi[n.id]), high: Math.max(lo[n.id], hi[n.id]) };
  return out;
}

/** الوزن الفعلي لكل عقدة في سعر المنتج النهائي = أثر صدمة 1 فيها على النهائي (مجموع جداءات الأوزان عبر المسارات). */
export function effectiveWeights(nodes: ChainNode[], edges: ChainEdge[], finalId: number): Record<number, { mid: number; low: number; high: number }> {
  const out: Record<number, { mid: number; low: number; high: number }> = {};
  for (const n of nodes) {
    if (n.id === finalId) continue;
    const r = propagateRange(nodes, edges, { [n.id]: 1 });
    out[n.id] = r[finalId];
  }
  return out;
}
