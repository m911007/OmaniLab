import { describe, expect, it } from 'vitest';
import { difference, ratio, yoyMonthly } from '@/modules/money/derived';
import { inflationTint } from '@/modules/omanprices/PriceItemsHeat';

describe('مقاييس النقد والأسعار المشتقة', () => {
  it('التغيّر السنوي للشهري', () => {
    const v = yoyMonthly([['2024-06', 100], ['2025-06', 110], ['2025-07', 120]]);
    expect(v).toEqual([['2025-06', 10]]);
  });
  it('الفرق والنسبة على الفترات المشتركة', () => {
    expect(difference([['2025-01', 5], ['2025-02', 6]], [['2025-02', 2]])).toEqual([['2025-02', 4]]);
    expect(ratio([['2025-01', 50]], [['2025-01', 200]])).toEqual([['2025-01', 25]]);
  });
  it('ألوان التضخم', () => {
    expect(inflationTint(-1)).toContain('39, 150');
    expect(inflationTint(8)).toContain('196, 68');
  });
});
