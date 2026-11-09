import { describe, expect, it } from 'vitest';
import { assess, breakevenOilPrice, executionRate, growth, hydrocarbonShare, nonHydrocarbonBalance, sharesOf } from '@/modules/fiscal/metrics';

const m = (o: Record<string, number>) => new Map(Object.entries(o));

describe('مقاييس المالية العامة', () => {
  it('حصة الهيدروكربون والرصيد دونه', () => {
    expect(hydrocarbonShare(m({ 2025: 6.64 }), m({ 2025: 1.84 }), m({ 2025: 12.12 })).get('2025')).toBeCloseTo(69.9, 0);
    expect(nonHydrocarbonBalance(m({ 2025: -0.46 }), m({ 2025: 6.64 }), m({ 2025: 1.84 })).get('2025')).toBeCloseTo(-8.94, 2);
  });
  it('سعر التعادل: عجز يعني تعادلًا أعلى من السعر، وفائض أدنى', () => {
    const be = breakevenOilPrice(m({ 2024: 80, 2022: 100 }), m({ 2024: -1, 2022: 1 }), m({ 2024: 5, 2022: 10 }));
    expect(be.get('2024')).toBeCloseTo(96);
    expect(be.get('2022')).toBeCloseTo(90);
  });
  it('تنفيذ الميزانية', () => {
    expect(executionRate(m({ 2025: 12.58 }), m({ 2025: 11.8 })).get('2025')).toBeCloseTo(106.6, 0);
  });
  it('النمو لا يعبر فجوة سنة مفقودة', () => {
    const g = growth(m({ 2019: 10, 2020: 11, 2022: 13 }));
    expect(g.get('2020')).toBeCloseTo(10);
    expect(g.has('2022')).toBe(false);
  });
  it('الحصص', () => {
    expect(sharesOf([m({ 2025: 1 }), m({ 2025: 3 })], '2025')).toEqual([25, 75]);
    expect(sharesOf([m({ 2025: 1 }), m({})], '2025')).toBeNull();
  });
  it('جملة التقييم', () => {
    const a = assess({ year: '2025', balance: -0.46, hydroShare: 70, price: 70, breakeven: 75 });
    expect(a.tone).toBe('warning');
    expect(a.text).toContain('عجز 0.46');
    expect(a.text).toContain('دون سعر التعادل');
  });
});
