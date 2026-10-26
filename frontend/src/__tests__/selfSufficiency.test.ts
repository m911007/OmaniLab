import { describe, expect, it } from 'vitest';
import { selfSufficiency } from '@/modules/agriculture/NationalSelfSufficiency';

describe('الاكتفاء الذاتي من الإنتاج الرسمي وتجارة FAO', () => {
  it('الإنتاج بالطن ÷ (الإنتاج + الواردات − الصادرات) بالألف طن، ويتخطى السنوات الناقصة', () => {
    // دواجن 2023: إنتاج 132 ألف طن، واردات 101، صادرات 32 ← 132 / 201 = 65.7%
    const out = selfSufficiency(new Map([['2023', 132000], ['2024', 140000]]), [new Map([['2023', 101]])], [new Map([['2023', 32]])]);
    expect(out).toHaveLength(1);
    expect(out[0][0]).toBe('2023');
    expect(out[0][1]).toBeCloseTo(65.67, 1);
  });
  it('اللحوم الحمراء تجمع واردات وصادرات الأبقار والضأن', () => {
    const out = selfSufficiency(new Map([['2023', 50000]]), [new Map([['2023', 17]]), new Map([['2023', 13]])], [new Map([['2023', 3]]), new Map([['2023', 0]])]);
    expect(out[0][1]).toBeCloseTo((50 / 77) * 100, 1);
  });
});
