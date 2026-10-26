import { describe, expect, it } from 'vitest';
import { effectiveWeights, propagate, propagateRange } from '@/modules/chains/shock';
import type { ChainEdge, ChainNode } from '@/modules/chains/api';

const node = (id: number, stage: number, kind: ChainNode['kind'] = 'intermediate'): ChainNode => ({ id, slug: `n${id}`, name_ar: `n${id}`, name_en: '', kind, stage, unit: null, hs_codes: [], notes: null, series: [], check: null });
const edge = (from: number, to: number, w: number | null, lo?: number, hi?: number): ChainEdge => ({
  id: from * 10 + to, from_node_id: from, to_node_id: to, weight: w == null ? null : String(w), weight_min: lo == null ? null : String(lo), weight_max: hi == null ? null : String(hi),
  weight_basis: 'documented_estimate', conversion_factor: null, conversion_min: null, conversion_max: null, conversion_unit: null, notes: null, logistics_note: null, updated_at: '', sources: [],
});

// خام (1) ← وسيط (2) بوزن 0.6؛ طاقة (3) ← وسيط بوزن 0.2؛ وسيط ← نهائي (4) بوزن 0.8؛ تحويل (5) ← نهائي 0.2؛ حافة بلا وزن (6 ← 4)
const nodes = [node(1, 0, 'raw'), node(3, 0, 'energy'), node(2, 1), node(5, 1, 'conversion'), node(6, 1, 'raw'), node(4, 2, 'final')];
const edges = [edge(1, 2, 0.6, 0.5, 0.7), edge(3, 2, 0.2, 0.1, 0.3), edge(2, 4, 0.8, 0.75, 0.9), edge(5, 4, 0.2, 0.1, 0.25), edge(6, 4, null)];

describe('انتشار الصدمة السعرية', () => {
  it('ينقل الصدمة عبر المراحل بجداء الأوزان', () => {
    const r = propagate(nodes, edges, { 1: 10 });
    expect(r[2]).toBeCloseTo(6);
    expect(r[4]).toBeCloseTo(4.8);
    expect(r[3]).toBe(0);
  });

  it('يجمع صدمات متعددة ويتجاهل الحواف بلا وزن', () => {
    const r = propagate(nodes, edges, { 1: 10, 3: -10, 6: 50 });
    expect(r[4]).toBeCloseTo((6 - 2) * 0.8);
  });

  it('النطاق يستخدم الأوزان الدنيا والعليا', () => {
    const r = propagateRange(nodes, edges, { 1: 10 });
    expect(r[4].low).toBeCloseTo(10 * 0.5 * 0.75);
    expect(r[4].high).toBeCloseTo(10 * 0.7 * 0.9);
    const neg = propagateRange(nodes, edges, { 1: -10 });
    expect(neg[4].low).toBeLessThan(neg[4].high);
  });

  it('الوزن الفعلي في المنتج النهائي', () => {
    const w = effectiveWeights(nodes, edges, 4);
    expect(w[1].mid).toBeCloseTo(0.48);
    expect(w[5].mid).toBeCloseTo(0.2);
    expect(w[6].mid).toBe(0);
  });
});
