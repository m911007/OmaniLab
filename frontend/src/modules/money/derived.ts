export type Series = [string, number][];

const monthsBack = (p: string, n: number): string => {
  const [y, m] = p.split('-').map(Number);
  const t = y * 12 + (m - 1) - n;
  return `${Math.floor(t / 12)}-${String((t % 12) + 1).padStart(2, '0')}`;
};

/** التغيّر % عن الشهر نفسه قبل سنة لسلسلة شهرية. */
export function yoyMonthly(points: Series): Series {
  const m = new Map(points);
  return points.filter(([p]) => m.has(monthsBack(p, 12)) && m.get(monthsBack(p, 12))! !== 0).map(([p, v]) => [p, ((v - m.get(monthsBack(p, 12))!) / Math.abs(m.get(monthsBack(p, 12))!)) * 100]);
}

/** الفرق a − b على الفترات المشتركة (هامش الفائدة، الفائدة الحقيقية…). */
export function difference(a: Series, b: Series): Series {
  const bm = new Map(b);
  return a.filter(([p]) => bm.has(p)).map(([p, v]) => [p, v - bm.get(p)!]);
}

/** نسبة a إلى b % على الفترات المشتركة (الائتمان إلى النقد الواسع). */
export function ratio(a: Series, b: Series): Series {
  const bm = new Map(b);
  return a.filter(([p]) => (bm.get(p) ?? 0) !== 0).map(([p, v]) => [p, (v / bm.get(p)!) * 100]);
}
