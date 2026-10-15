import { describe, expect, it } from 'vitest';
import type { Point } from '@/core/api';
import { annualMean, ols, pairByYear } from '@/core/stats';

const months = (year: number, count: number, value: number): Point[] => Array.from({ length: count }, (_, i): Point => [`${year}-${String(i + 1).padStart(2, '0')}`, value + i]);

describe('annualMean', () => {
  it('يحسب متوسط السنة الكاملة ويستبعد السنة الناقصة', () => {
    const out = annualMean([...months(2023, 12, 10), ...months(2024, 9, 50)]);

    expect(out).toEqual([['2023', 15.5]]);
  });

  it('يقبل حدًا أدنى للأشهر أقل', () => {
    expect(annualMean(months(2024, 9, 0), 9)).toEqual([['2024', 4]]);
  });
});

describe('ols', () => {
  it('يجد الخط المستقيم التام', () => {
    const r = ols([1, 2, 3, 4], [3, 5, 7, 9])!;

    expect(r.slope).toBeCloseTo(2);
    expect(r.intercept).toBeCloseTo(1);
    expect(r.r2).toBeCloseTo(1);
    expect(r.n).toBe(4);
  });

  it('يقيس ضعف الارتباط بمعامل التحديد', () => {
    const r = ols([1, 2, 3, 4, 5], [2, 9, 1, 8, 3])!;

    expect(r.r2).toBeLessThan(0.2);
  });

  it('يعيد null عند قلّة النقاط أو انعدام تشتت x', () => {
    expect(ols([1, 2], [1, 2])).toBeNull();
    expect(ols([3, 3, 3], [1, 2, 3])).toBeNull();
  });
});

describe('pairByYear', () => {
  it('يزاوج السنوات المشتركة فقط', () => {
    expect(pairByYear([['2020', 1], ['2021', 2]], [['2021', 20], ['2022', 30]])).toEqual([['2021', 2, 20]]);
  });
});

describe('hhi', () => {
  it('يقيس التركّز: منتج واحد = 10000، وعشرة بالتساوي = 1000', async () => {
    const { hhi } = await import('@/modules/trade/ProductMix');

    expect(hhi([1])).toBe(10000);
    expect(hhi(Array(10).fill(0.1))).toBe(1000);
  });
});
