import { describe, expect, it } from 'vitest';
import { normaliseArabic } from '@/app/SearchPalette';

describe('البحث الشامل', () => {
  it('يوحّد الحروف العربية كما يفعل الخادم', () => {
    expect(normaliseArabic('أسعار السِّلع والطاقة')).toBe('اسعار السلع والطاقه');
    expect(normaliseArabic('مستوى')).toBe('مستوي');
    expect(normaliseArabic('  Inflation ')).toBe('inflation');
  });
});
