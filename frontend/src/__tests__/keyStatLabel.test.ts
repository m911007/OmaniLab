import { describe, expect, it } from 'vitest';
import { cleanLabel } from '@/core/components/KeyStat';

describe('عناوين البطاقات', () => {
  it('تُحذف الوحدة بين قوسين في آخر العنوان لأن البطاقة تعرض وحدتها الفعلية', () => {
    expect(cleanLabel('إنتاج المحاصيل (طن)')).toBe('إنتاج المحاصيل');
    expect(cleanLabel('الدين الحكومي (% من الناتج)')).toBe('الدين الحكومي');
    expect(cleanLabel('الإيرادات النفطية (مليون ر.ع)')).toBe('الإيرادات النفطية');
    expect(cleanLabel('الثروة الحيوانية (ألف رأس)')).toBe('الثروة الحيوانية');
  });
  it('الأقواس ذات المعنى تبقى', () => {
    expect(cleanLabel('بطالة الشباب (15–24)')).toBe('بطالة الشباب (15–24)');
    expect(cleanLabel('مستوى الأسعار (أمريكا = 100)')).toBe('مستوى الأسعار (أمريكا = 100)');
    expect(cleanLabel('زيارات ميناء صحار (شهريًا)')).toBe('زيارات ميناء صحار (شهريًا)');
  });
});
