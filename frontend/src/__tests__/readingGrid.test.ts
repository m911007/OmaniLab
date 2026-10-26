import { describe, expect, it } from 'vitest';
import { layoutCards } from '@/core/components/ReadingGrid';

describe('توزيع بطاقات القراءة على عرض الشاشة', () => {
  it('صفوف متوازنة: 12 على عرض يتسع لخمس ← 4×3، و7 ← 4+3', () => {
    expect(layoutCards(12, 1200).columns).toBe(4);
    expect(layoutCards(7, 1200).columns).toBe(4);
  });
  it('البطاقات تغطي العرض: عرض البطاقة × الأعمدة + الفراغات = العرض المتاح', () => {
    const { columns, cardWidth } = layoutCards(3, 1000);
    expect(columns).toBe(3);
    expect(columns * cardWidth + (columns - 1) * 14).toBeLessThanOrEqual(1000);
    expect(columns * cardWidth + (columns - 1) * 14).toBeGreaterThan(995);
  });
  it('لا تقل البطاقة عن الحد الأدنى، وبطاقة واحدة تأخذ العرض', () => {
    expect(layoutCards(12, 600).cardWidth).toBeGreaterThanOrEqual(220);
    expect(layoutCards(1, 800).cardWidth).toBe(800);
  });
});
