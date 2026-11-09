import { describe, expect, it } from 'vitest';
import { marketValueOmr, perConnection } from '@/modules/energy/EnergyDerived';

const m = (o: Record<string, number>) => new Map(Object.entries(o));
describe('مقاييس الطاقة المشتقة', () => {
  it('قيمة الإنتاج بسعر السوق بالريال', () => {
    // 1000 ألف برميل/يوم × 365 × 80$ = 29.2 مليار $ ← × 0.3845
    expect(marketValueOmr(m({ 2024: 1000 }), m({ 2024: 80 })).get('2024')).toBeCloseTo(29.2 * 0.3845, 3);
  });
  it('استهلاك الاشتراك', () => {
    expect(perConnection(m({ 2024: 36000 }), m({ 2024: 1_000_000 })).get('2024')).toBe(36);
  });
});
