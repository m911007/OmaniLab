import { describe, expect, it } from 'vitest';
import { EDGES, NODES, STORIES, edgeBetween, nodeFor, relatedTo, transformAnnual } from '@/core/relations';
import { recentMove } from '@/core/useRelations';
import { propagate } from '@/modules/impact/ImpactPage';
import type { EdgeStats } from '@/core/useRelations';

describe('شبكة العلاقات', () => {
  it('كل علاقة وكل حلقة في السلاسل معرّفة ولها ملاحظة قرار', () => {
    for (const e of EDGES) {
      expect(NODES[e.from]).toBeDefined();
      expect(NODES[e.to]).toBeDefined();
      expect(e.note.length).toBeGreaterThan(20);
    }
    for (const s of STORIES) s.path.slice(1).forEach((k, i) => expect(edgeBetween(s.path[i], k)).toBeDefined());
  });

  it('المرادفات توجّه مؤشر البطاقة إلى سلسلة العلاقات', () => {
    expect(nodeFor('ncsi.CPI_INFLATION')).toBe('imf.PCPIPCH');
    expect(relatedTo('ncsi.CPI_INFLATION').drivers.map((e) => e.from)).toContain('fao.ffpi');
    expect(relatedTo('imf.GGXCNL_NGDP').effects.map((e) => e.to)).toEqual(['imf.GGXWDG_NGDP']);
  });

  it('التحويل: % للمستويات، فرق بالنقاط للنسب، وكما هو للمعدلات', () => {
    const m = new Map([['2020', 100], ['2021', 110]]);
    expect(transformAnnual(m, 'pct').get('2021')).toBeCloseTo(10);
    expect(transformAnnual(m, 'diff').get('2021')).toBe(10);
    expect(transformAnnual(m, 'level').get('2020')).toBe(100);
    expect(transformAnnual(m, 'pct').has('2020')).toBe(false);
  });

  it('آخر تغيّر: الشهر مقابل الشهر نفسه قبل سنة', () => {
    const m = recentMove([['2025-08', 50], ['2026-07', 55], ['2026-08', 60]], 'pct')!;
    expect(m.value).toBeCloseTo(20);
    expect([m.period, m.base]).toEqual(['2026-08', '2025-08']);
    expect(recentMove([['2024', 3], ['2025', 2.5]], 'diff')?.value).toBeCloseTo(-0.5);
  });

  it('الصدمة تنتقل بالميل وتتوقف عند أول حلقة غير مثبتة', () => {
    const stat = (from: string, to: string, slope: number, usable: boolean) => ({ edge: { from, to, expect: 1, note: '' }, effect: { slope } as EdgeStats['effect'], watch: null, usable, surprising: false }) as EdgeStats;
    const out = propagate(['a', 'b', 'c', 'd'], [stat('a', 'b', 0.5, true), stat('b', 'c', 2, true), stat('c', 'd', 1, false)], 10);
    expect(out.map((n) => n.value)).toEqual([10, 5, 10, null]);
  });
});
