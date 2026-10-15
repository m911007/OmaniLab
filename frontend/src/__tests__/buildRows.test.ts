import { describe, expect, it } from 'vitest';
import { buildRows } from '@/core/components/LineChartView';

const s = (id: string, points: [string, number][]) => ({ id, label: id, color: 'red', points });

describe('بناء صفوف المخطط', () => {
  it('يوحّد الفترات من كل السلاسل مرتبة', () => {
    const { rows } = buildRows([s('a', [['2024', 1], ['2025', 2]]), s('b', [['2023', 5], ['2025', 7]])]);
    expect(rows.map((r) => r.period)).toEqual(['2023', '2024', '2025']);
    expect(rows[1]).toMatchObject({ a: 1 });
    expect(rows[1].b).toBeUndefined();
  });

  it('ينقل التوقعات إلى مفتاح منفصل ويصل الخط عند آخر قيمة فعلية', () => {
    const { rows, hasForecast } = buildRows([s('a', [['2024', 1], ['2025', 2], ['2026', 3], ['2027', 4]])], 2026);
    expect(hasForecast).toBe(true);
    expect(rows.find((r) => r.period === '2025')).toMatchObject({ a: 2, a__f: 2 }); // نقطة الوصل
    expect(rows.find((r) => r.period === '2026')).toMatchObject({ a__f: 3 });
    expect(rows.find((r) => r.period === '2026')?.a).toBeUndefined();
  });

  it('بلا توقعات لا مفاتيح __f', () => {
    const { rows, hasForecast } = buildRows([s('a', [['2024', 1]])]);
    expect(hasForecast).toBe(false);
    expect(Object.keys(rows[0])).not.toContain('a__f');
  });
});
