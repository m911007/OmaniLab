import { describe, expect, it } from 'vitest';
import { measureValue, monthGrid, shiftMonths, rankGovernorates, summarize, type GovSeries } from '@/modules/omanprices/governorates';

const months = (start: number, vals: number[]): [string, number][] => vals.map((v, i) => [`2025-${String(start + i).padStart(2, '0')}`, v]);
const gov = (code: string, infl: number[], index = 120): GovSeries => ({ code, label: code, infl: months(1, infl), index: [['2026-01', index]] });

describe('تضخم المحافظات', () => {
  const twelve = Array(12).fill(2);
  it('آخر شهر ومتوسط 12 شهرًا والتراكمي منذ 2018', () => {
    const g = gov('A', [...twelve.slice(0, 11), 5], 130);
    expect(measureValue(g, 'latest')?.value).toBe(5);
    expect(measureValue(g, 'avg12')?.value).toBeCloseTo((22 + 5) / 12);
    expect(measureValue(g, 'since')?.value).toBe(30);
  });
  it('متوسط 12 شهرًا يحتاج 12 قراءة', () => {
    expect(measureValue(gov('A', [1, 2, 3]), 'avg12')).toBeNull();
  });
  it('ترتيب تنازلي وملخص الفارق وعدد ما فوق الوطني', () => {
    const ranked = rankGovernorates([gov('A', [1]), gov('B', [4]), gov('C', [2])], 'latest');
    expect(ranked.map((r) => r.code)).toEqual(['B', 'C', 'A']);
    const s = summarize(ranked, 1.5)!;
    expect(s.spread).toBe(3);
    expect(s.aboveNational).toBe(2);
  });
  it('جدول الأشهر يقتصر على آخر n فترة', () => {
    expect(monthGrid([gov('A', Array(12).fill(1))], 3).periods).toEqual(['2025-10', '2025-11', '2025-12']);
  });
  it('إزاحة الأشهر عبر حدود السنة', () => {
    expect(shiftMonths('2026-09', -36)).toBe('2023-09');
    expect(shiftMonths('2026-02', -3)).toBe('2025-11');
  });
});
