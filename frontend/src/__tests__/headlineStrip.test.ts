import { describe, expect, it } from 'vitest';
import { tileValue } from '@/core/components/HeadlineStrip';

const spec = { indicator: 'ncsi.X', label: 'x' } as const;
describe('بطاقات الرأس', () => {
  it('آخر قيمة والفرق عن السابقة', () => {
    const v = tileValue([['2024-01', 5], ['2024-02', 6]], spec, '2025')!;
    expect(v.value).toBe(6);
    expect(v.delta).toBe(1);
  });
  it('yoy يقارن بالفترة نفسها قبل سنة', () => {
    const pts: [string, number][] = [['2023-01', 100], ['2024-01', 110], ['2025-01', 132]];
    const v = tileValue(pts, { ...spec, mode: 'yoy' }, '2025')!;
    expect(v.value).toBeCloseTo(20);
    expect(v.previous).toBeCloseTo(10);
  });
  it('مؤشرات صندوق النقد تتوقف عند آخر سنة فعلية', () => {
    const v = tileValue([['2024', 1], ['2025', 2], ['2027', 9]], { indicator: 'imf.A', label: 'a' }, '2025')!;
    expect(v.value).toBe(2);
  });
});
