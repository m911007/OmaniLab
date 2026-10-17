import { INDICES, loadRanking } from '@/modules/indices/data';
import { gccPosition, standingPct, tierOf } from '@/modules/indices/types';

describe('المؤشرات العالمية', () => {
  it('كل مؤشر له رابط مصدر وسنة وslug فريد', () => {
    expect(INDICES.length).toBe(25);
    expect(new Set(INDICES.map((i) => i.slug)).size).toBe(25);
    INDICES.forEach((i) => {
      expect(i.url).toMatch(/^https?:\/\//);
      expect(i.year).toBeGreaterThan(2015);
    });
  });

  it('الموقع بين 0 و100 والتصنيف متسق', () => {
    INDICES.forEach((i) => {
      const p = standingPct(i);
      if (p != null) {
        expect(p).toBeGreaterThanOrEqual(0);
        expect(p).toBeLessThanOrEqual(100);
        expect(tierOf(i)).not.toBe('none');
      }
    });
  });

  it('الترتيب الخليجي يُحسب من الجدول الكامل', async () => {
    const egdi = INDICES.find((i) => i.slug.startsWith('e-government'))!;
    expect(gccPosition(egdi)).toEqual({ rank: 4, of: 6 });
    const rows = await loadRanking(egdi.slug);
    expect(rows.length).toBe(193);
  });
});
