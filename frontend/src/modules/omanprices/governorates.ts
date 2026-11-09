import type { Point } from '@/core/api';

export type Measure = 'latest' | 'avg12' | 'since';

export interface GovSeries {
  code: string;
  label: string;
  /** التضخم السنوي الشهري % */
  infl: Point[];
  /** الرقم القياسي (2018 = 100) */
  index: Point[];
}

export interface Ranked {
  code: string;
  label: string;
  value: number;
  period: string;
}

export const MEASURES: { value: Measure; label: string; hint: string }[] = [
  { value: 'latest', label: 'آخر شهر', hint: 'التضخم السنوي في آخر شهر متاح' },
  { value: 'avg12', label: 'متوسط 12 شهرًا', hint: 'متوسط التضخم السنوي في آخر 12 شهرًا' },
  { value: 'since', label: 'منذ 2018', hint: 'كم ارتفعت الأسعار تراكميًا منذ سنة الأساس 2018' },
];

/** آخر n نقطة (مرتّبة زمنيًا). */
export function lastPoints(points: Point[], n: number): Point[] {
  return [...points].sort((a, b) => a[0].localeCompare(b[0])).slice(-n);
}

/** قيمة المحافظة بحسب المقياس، أو null إن لم تكفِ بياناتها (متوسط 12 شهرًا يحتاج 12 قراءة كاملة). */
export function measureValue(s: Pick<GovSeries, 'infl' | 'index'>, m: Measure): { value: number; period: string } | null {
  if (m === 'since') {
    const last = lastPoints(s.index, 1)[0];
    return last ? { value: last[1] - 100, period: last[0] } : null;
  }
  const pts = lastPoints(s.infl, m === 'latest' ? 1 : 12);
  if (pts.length === 0 || (m === 'avg12' && pts.length < 12)) return null;
  return { value: pts.reduce((a, p) => a + p[1], 0) / pts.length, period: pts[pts.length - 1][0] };
}

/** المحافظات مرتّبة من الأعلى تضخمًا إلى الأدنى؛ من لا بيانات لها تُستبعد. */
export function rankGovernorates(rows: readonly GovSeries[], m: Measure): Ranked[] {
  return rows
    .map((r) => {
      const v = measureValue(r, m);
      return v ? { code: r.code, label: r.label, ...v } : null;
    })
    .filter((x): x is Ranked => x !== null)
    .sort((a, b) => b.value - a.value);
}

export interface GovSummary {
  top: Ranked;
  bottom: Ranked;
  /** الفارق بين الأعلى والأدنى بالنقاط المئوية */
  spread: number;
  aboveNational: number;
}

export function summarize(ranked: readonly Ranked[], national: number | null): GovSummary | null {
  if (ranked.length < 2) return null;
  const top = ranked[0];
  const bottom = ranked[ranked.length - 1];
  return { top, bottom, spread: top.value - bottom.value, aboveNational: national === null ? 0 : ranked.filter((r) => r.value > national).length };
}

/** مدخلات جدول الأشهر: لكل محافظة قيم آخر n شهرًا مصفوفة على فترات مشتركة. */
export function monthGrid(rows: readonly GovSeries[], n: number): { periods: string[]; cells: Map<string, Map<string, number>> } {
  const periods = [...new Set(rows.flatMap((r) => r.infl.map((p) => p[0])))].sort().slice(-n);
  const cells = new Map(rows.map((r) => [r.code, new Map(r.infl.map(([p, v]) => [p, v]))]));
  return { periods, cells };
}

/** فترة «YYYY-MM» بعد إزاحتها عددًا من الأشهر (سالبًا للماضي). */
export function shiftMonths(period: string, delta: number): string {
  const [y, m] = period.split('-').map(Number);
  const t = y * 12 + (m - 1) + delta;
  return `${Math.floor(t / 12)}-${String((t % 12) + 1).padStart(2, '0')}`;
}
