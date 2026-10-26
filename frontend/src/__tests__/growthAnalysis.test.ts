import { describe, expect, it } from 'vitest';
import { impact, jointImpact, pearson, rollingPearson, strength, toAnnual, trend } from '@/core/stats';

const m = (o: Record<string, number | null>) => new Map(Object.entries(o));
const years = ['2015', '2016', '2017', '2018', '2019', '2020'];

describe('معامل الارتباط في جدول تحليل النمو', () => {
  it('ارتباط تام طردي وعكسي', () => {
    const a = m({ 2015: 1, 2016: 2, 2017: 3, 2018: 4, 2019: 5, 2020: 6 });
    expect(pearson(a, m({ 2015: 2, 2016: 4, 2017: 6, 2018: 8, 2019: 10, 2020: 12 }), years).r).toBeCloseTo(1);
    expect(pearson(a, m({ 2015: 6, 2016: 5, 2017: 4, 2018: 3, 2019: 2, 2020: 1 }), years).r).toBeCloseTo(-1);
  });
  it('التأخر بسنة يقارن السنة بالتي تليها، ويتجاهل القيم الناقصة ويشترط 5 أزواج', () => {
    const a = m({ 2014: 1, 2015: 2, 2016: 3, 2017: 4, 2018: 5, 2019: 6 });
    const b = m({ 2015: 1, 2016: 2, 2017: 3, 2018: 4, 2019: 5, 2020: 6 });
    expect(pearson(a, b, ['2014', ...years], 1).r).toBeCloseTo(1);
    expect(pearson(a, m({ 2015: 1, 2016: null }), years).r).toBeNull();
  });
  it('وصف القوة والاتجاه', () => {
    expect(strength(0.82)).toBe('قوي طردي');
    expect(strength(-0.5)).toBe('متوسط عكسي');
    expect(strength(0.1)).toBe('شبه معدوم');
  });
  it('الارتباط المتحرك يحسب كل نافذة حتى سنة نهايتها، والاتجاه يقارن قوة أول نافذة بآخرها', () => {
    const ys = Array.from({ length: 12 }, (_, i) => String(2010 + i));
    const a = m(Object.fromEntries(ys.map((y, i) => [y, i % 2 ? 1 : -1])));
    // أول السنوات تتبع a تمامًا ثم تصبح ضوضاء غير مرتبطة
    const b = m(Object.fromEntries(ys.map((y, i) => [y, i < 6 ? (i % 2 ? 2 : -2) : [3, 3, -3, -3, 3, 3][i - 6]])));
    const r = rollingPearson(a, b, ys, 6);
    expect([...r.keys()][0]).toBe('2015');
    expect(r.get('2015')).toBeCloseTo(1);
    expect(trend(r)?.tone).toBe('down');
    expect(trend(new Map([['2015', 0.3], ['2016', 0.5], ['2017', 0.8]]))?.tone).toBe('up');
    expect(trend(new Map([['2015', 0.6], ['2016', 0.1], ['2017', -0.5]]))?.tone).toBe('flip');
  });
  it('الفصلية تُجمع للسنوات المكتملة فقط، والشهرية تُتوسّط', () => {
    const q = toAnnual([['2023-Q1', 1], ['2023-Q2', 2], ['2023-Q3', 3], ['2023-Q4', 4], ['2024-Q1', 5]]);
    expect(q.get('2023')).toBe(10);
    expect(q.has('2024')).toBe(false);
    expect(toAnnual([['2020', 7]]).get('2020')).toBe(7);
  });
  it('مدى التأثير: الميل وR² والدلالة من انحدار التغيّر على المحرّك', () => {
    const x = m({ 2015: -10, 2016: 0, 2017: 10, 2018: 20, 2019: -20, 2020: 5 });
    const y = m({ 2015: -2, 2016: 0, 2017: 2, 2018: 4, 2019: -4, 2020: 1 });
    const e = impact(x, y, years)!;
    expect(e.slope).toBeCloseTo(0.2);
    expect(e.r2).toBeCloseTo(1);
    expect(e.significant).toBe(true);
    expect(e.pairs).toHaveLength(6);
  });
  it('الانحدار المتعدد يفصل أثر محرّكين متداخلين', () => {
    const ys = ['2014', ...years, '2021'];
    const x1 = m({ 2014: 1, 2015: 2, 2016: 3, 2017: 5, 2018: 4, 2019: 7, 2020: 6, 2021: 9 });
    const x2 = m({ 2014: 2, 2015: 1, 2016: 4, 2017: 3, 2018: 6, 2019: 5, 2020: 8, 2021: 6 });
    const y = new Map([...x1].map(([k, v]) => [k, 1 + 2 * (v as number) - 0.5 * (x2.get(k) as number) + (Number(k) % 2 ? 0.01 : -0.01)]));
    const j = jointImpact(x1, x2, y, ys)!;
    expect(j.b1).toBeCloseTo(2, 1);
    expect(j.b2).toBeCloseTo(-0.5, 1);
    expect(j.r2).toBeGreaterThan(0.99);
  });
});
