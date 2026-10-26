import { describe, expect, it } from 'vitest';
import { waterForCrops } from '@/modules/agriculture/WaterForCrops';

describe('المياه والمحاصيل', () => {
  it('مياه الزراعة = إجمالي السحب × حصة الزراعة، مقسومة على الإنتاج والمساحة، لسنوات المصدرين معًا', () => {
    const rows = waterForCrops(
      new Map([['2021', 1.6], ['2022', 1.634]]),
      new Map([['2021', 80], ['2022', 80]]),
      new Map([['2022', 4_000_000]]),
      new Map([['2022', 320_000]]),
      new Map([['2022', 128_000]]),
    );
    expect(rows).toHaveLength(1);
    expect(rows[0].agriWater).toBeCloseTo(1.3072e9, -3);
    expect(rows[0].perTonne).toBeCloseTo(326.8, 1);
    expect(rows[0].perFeddan).toBeCloseTo(4085, 0);
    expect(rows[0].fodderAreaShare).toBeCloseTo(40);
  });
});
