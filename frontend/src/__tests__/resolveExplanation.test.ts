import { describe, expect, it } from 'vitest';
import type { IndicatorMeta } from '@/core/api';
import { EXPLANATION_REGISTRY, resolveExplanation } from '@/core/resolveExplanation';

const ind = (key: string, over: Partial<IndicatorMeta> = {}): IndicatorMeta => ({ key, name_ar: 'اسم المؤشر', name_en: 'x', unit: '%', frequency: 'A', ...over });

describe('شرح الرسوم', () => {
  it('يعيد null بلا مؤشرات', () => {
    expect(resolveExplanation({ indicators: [], labels: [], multi: false })).toBeNull();
  });

  it('مؤشر له شرح مكتوب: معناه ومصطلحاته، و«ماذا يوضح الرسم» مولَّد من وحدته وتردده', () => {
    const e = resolveExplanation({ indicators: [ind('ncsi.MON_M2', { name_ar: 'عرض النقد الواسع (M2)', unit: 'مليون ريال عُماني', frequency: 'M' })], labels: ['M2'], multi: false })!;

    expect(e.meaning).toContain('الأموال');
    expect(e.shows).toContain('شهريًا');
    expect(e.shows).toContain('مليون ريال عُماني');
    expect(e.terms?.some((t) => t.term.includes('M2'))).toBe(true);
    expect(e.read.some((r) => r.includes('مرّر المؤشر'))).toBe(true);
  });

  it('مؤشر بلا مدخل خاص يأخذ شرح عائلته (مثل أسعار السلع)', () => {
    const e = resolveExplanation({ indicators: [ind('imf.pcps.pzinc', { name_ar: 'الزنك', frequency: 'M' })], labels: ['الزنك'], multi: false })!;

    expect(e.meaning).toContain('سلعة');
  });

  it('مؤشر مجهول يحصل على شرح عام صريح عن حدوده لا على ادّعاء معنى', () => {
    const e = resolveExplanation({ indicators: [ind('zzz.unknown', { name_ar: 'مؤشر غريب' })], labels: ['عُمان'], multi: false })!;

    expect(e.meaning).toContain('مؤشر غريب');
    expect(e.meaning).toContain('الجهة المصدِّرة');
    expect(e.why.length).toBeGreaterThan(10);
  });

  it('الرسم المقارن والمتعدد الخطوط والتوقعات يضيف إرشادات القراءة المناسبة', () => {
    const e = resolveExplanation({ indicators: [ind('imf.NGDP_RPCH')], labels: ['عُمان', 'السعودية'], multi: true, hasForecast: true })!;

    expect(e.shows).toContain('مقارنةً بدول أخرى');
    expect(e.read.some((r) => r.includes('مفتاح الرسم'))).toBe(true);
    expect(e.read.some((r) => r.includes('الخط المتقطع'))).toBe(true);
  });

  it('كل المدخلات المكتوبة بجودة دنيا: معنى مفصّل ولماذا وقراءة', () => {
    expect(Object.keys(EXPLANATION_REGISTRY).length).toBeGreaterThan(150);
    for (const [key, e] of Object.entries(EXPLANATION_REGISTRY)) {
      expect(e.meaning.length, `${key} meaning`).toBeGreaterThanOrEqual(20);
      expect(e.why.length, `${key} why`).toBeGreaterThanOrEqual(10);
      expect(e.read.length, `${key} read`).toBeGreaterThanOrEqual(1);
      expect(e.read.every((r) => r.length > 8), `${key} read items`).toBe(true);
    }
  });
});
