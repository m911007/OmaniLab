import { describe, expect, it } from 'vitest';
import { axisNumber, changeBetween, compactParts, entityName, formatPeriod, formatValue, relativeTime, shortPeriod, yearOf } from '@/core/format';

describe('تنسيق الأرقام', () => {
  it('يفصل الرقم عن كلمة المقياس لترتيب عربي صحيح', () => {
    expect(compactParts(21_208_630_443)).toEqual({ num: '21.2', word: 'مليار' });
    expect(compactParts(4_090_000)).toEqual({ num: '4.1', word: 'مليون' });
    expect(compactParts(1_500)).toEqual({ num: '1.5', word: 'ألف' });
    expect(compactParts(42.5)).toEqual({ num: '42.5', word: null });
  });

  it('formatValue نص رقمي خالص بلا كلمات عربية', () => {
    expect(formatValue(21_208_630_443)).toBe('21,208,630,443');
    expect(formatValue(109.432)).toBe('109.4');
    expect(formatValue(3.256, '%')).toBe('3.3');
    expect(formatValue(Number.NaN)).toBe('—');
  });

  it('المحور باختصار لاتيني', () => {
    expect(axisNumber(2_500_000_000)).toBe('2.5B');
    expect(axisNumber(15_000)).toBe('15K');
    expect(axisNumber(0.5)).toBe('0.5');
  });
});

describe('التغيّر', () => {
  it('النسب تُقاس بنقاط مئوية، والمستويات بنسبة', () => {
    expect(changeBetween(3.2, 3.0, '%')).toEqual({ amount: '0.2', suffix: 'نقطة مئوية', direction: 'up' });
    expect(changeBetween(110, 100, 'دولار')).toEqual({ amount: '10', suffix: '%', direction: 'up' });
    expect(changeBetween(90, 100)).toMatchObject({ direction: 'down' });
  });

  it('تغيّر ضئيل يُعد ثابتًا، ولا قسمة على صفر', () => {
    expect(changeBetween(3.01, 3.0, '%')?.direction).toBe('flat');
    expect(changeBetween(5, 0)).toBeNull();
  });
});

describe('الفترات والتواريخ', () => {
  it('يعرض الفترات الشهرية والفصلية بالعربية', () => {
    expect(formatPeriod('2025')).toBe('2025');
    expect(formatPeriod('2025-Q3')).toBe('الربع 3 2025');
    expect(formatPeriod('2025-09')).toContain('2025');
    expect(shortPeriod('2025-09')).toBe('09/25');
    expect(yearOf('2025-09')).toBe(2025);
  });

  it('الوقت النسبي بالعربية', () => {
    const now = Date.parse('2026-10-15T12:00:00Z');
    expect(relativeTime('2026-10-15T11:25:00Z', now)).toMatch(/دقيق/);
    expect(relativeTime(null)).toBe('—');
    expect(relativeTime('2026-10-15T11:59:50Z', now)).toBe('الآن');
  });

  it('اسم الكيان عربي إن وُجد', () => {
    expect(entityName({ name_ar: 'عُمان', name_en: 'Oman' })).toBe('عُمان');
    expect(entityName({ name_ar: null, name_en: 'Taiwan' })).toBe('Taiwan');
  });
});
