import type { Scorecard, ScorecardItem } from '@/core/api';
import { CLASS_META, type InsightGroup } from './classes';

export const groupOf = (i: ScorecardItem): InsightGroup => CLASS_META[i.class].group;

/** أبرز ما في البطاقة: أقوى نقاط القوة، وأشد التحديات، وأوضح الفرص الصاعدة — مرتبة بتدرّج الأداء العالمي. */
export function headlines(card: Scorecard, n = 3): { strengths: ScorecardItem[]; challenges: ScorecardItem[]; opportunities: ScorecardItem[]; watch: ScorecardItem[] } {
  const by = (g: InsightGroup) => card.items.filter((i) => groupOf(i) === g);
  const byPct = (a: ScorecardItem, b: ScorecardItem) => b.world.percentile - a.world.percentile;
  return {
    strengths: by('strength').sort(byPct).slice(0, n),
    challenges: by('challenge').sort((a, b) => byPct(b, a)).slice(0, n),
    opportunities: by('opportunity').sort(byPct).slice(0, n),
    watch: by('watch').sort(byPct).slice(0, n),
  };
}

/** وصف موجز لموقع المؤشر: «المرتبة 59 من 195 عالميًا». */
export const worldRankText = (i: ScorecardItem): string => `المرتبة ${i.world.rank} من ${i.world.of} عالميًا`;
