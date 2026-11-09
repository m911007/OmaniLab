import { describe, expect, it } from 'vitest';
import { canShowBars, canShowPie, modesFor } from '@/core/chartModes';

const annual = (n: number, v = 1): { points: [string, number][] } => ({ points: Array.from({ length: n }, (_, i) => [String(2010 + i), v] as [string, number]) });

describe('أوضاع عرض الرسم', () => {
  it('الأعمدة للكميات السنوية فقط', () => {
    expect(canShowBars([annual(10)], 'مليون ريال عُماني')).toBe(true);
    expect(canShowBars([annual(10)], '% سنويًا')).toBe(false);
    expect(canShowBars([annual(10)], 'دولار/برميل')).toBe(false);
    expect(canShowBars([{ points: [['2020-01', 1], ['2020-02', 2], ['2020-03', 3]] }], 'مليون ريال')).toBe(false);
    expect(canShowBars([annual(10), annual(10), annual(10), annual(10), annual(10)], 'مليون ريال')).toBe(false);
  });
  it('الدائري لمكوّنات غير سالبة', () => {
    expect(canShowPie([annual(5), annual(5)])).toBe(true);
    expect(canShowPie([annual(5)])).toBe(false);
    expect(canShowPie([annual(5), annual(5, -1)])).toBe(false);
  });
  it('الأوضاع البديلة بحسب النوع', () => {
    expect(modesFor('line', [annual(10)], '%')).toEqual(['lines', 'table']);
    expect(modesFor('line', [annual(10)], 'مليون ريال')).toEqual(['lines', 'bars', 'table']);
    expect(modesFor('columns', [annual(10)], '%')).toEqual(['bars', 'lines', 'table']);
    expect(modesFor('stacked', [annual(10), annual(10)], 'مليون')).toEqual(['stack', 'lines', 'pie', 'table']);
  });
});
