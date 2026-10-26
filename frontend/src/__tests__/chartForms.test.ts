import { describe, expect, it } from 'vitest';
import { bandAt, movingAverage, rebase } from '@/core/components/LineChartView';

const s = (id: string, pts: [string, number][]) => ({ id, label: id, color: 'red', points: pts });

describe('أشكال الرسوم', () => {
  it('المتوسط المتحرك يبدأ بعد اكتمال النافذة', () => {
    expect(movingAverage([['a', 1], ['b', 2], ['c', 3], ['d', 4]], 3)).toEqual([['c', 2], ['d', 3]]);
    expect(movingAverage([['a', 1]], 1)).toEqual([['a', 1]]);
  });
  it('إعادة القياس إلى 100 في أول نقطة', () => {
    expect(rebase([['a', 50], ['b', 75]])).toEqual([['a', 100], ['b', 150]]);
  });
  it('نطاق بقية الدول: الأدنى والأعلى والوسيط، ويحتاج دولتين على الأقل', () => {
    const others = [s('x', [['2024', 1]]), s('y', [['2024', 5]]), s('z', [['2024', 3]])];
    expect(bandAt(others, '2024')).toEqual({ lo: 1, hi: 5, mid: 3 });
    expect(bandAt([s('x', [['2024', 1]])], '2024')).toBeNull();
  });
});
