import { describe, expect, it } from 'vitest';
import { balanceOf, coverage, growthPct } from '@/modules/trade/TradeDerived';

const m = (o: Record<string, number>) => new Map(Object.entries(o));
describe('مقاييس التجارة المشتقة', () => {
  it('الميزان والتغطية', () => {
    expect(balanceOf(m({ 2025: 21 }), m({ 2025: 17 })).get('2025')).toBe(4);
    expect(coverage(m({ 2025: 21 }), m({ 2025: 14 })).get('2025')).toBe(150);
  });
  it('النمو بين سنتين متتاليتين فقط', () => {
    const g = growthPct(m({ 2023: 100, 2024: 110, 2026: 200 }));
    expect(g.get('2024')).toBeCloseTo(10);
    expect(g.has('2026')).toBe(false);
  });
});
