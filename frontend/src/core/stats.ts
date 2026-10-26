import type { Point } from './api';

/** متوسط سنوي لسلسلة شهرية؛ لا تُحسب سنة تقل أشهرها عن minMonths (متوسط سنة ناقصة يضلّل). */
export function annualMean(points: readonly Point[], minMonths = 10): Point[] {
  const byYear = new Map<string, number[]>();
  for (const [period, value] of points) {
    const year = period.slice(0, 4);
    byYear.set(year, [...(byYear.get(year) ?? []), value]);
  }
  return [...byYear.entries()]
    .filter(([, v]) => v.length >= minMonths)
    .map(([year, v]): Point => [year, v.reduce((a, b) => a + b, 0) / v.length])
    .sort((a, b) => a[0].localeCompare(b[0]));
}

export interface Regression {
  slope: number;
  intercept: number;
  r2: number;
  n: number;
}

/** انحدار خطي بسيط بالمربعات الصغرى. يعيد null إن قلّت النقاط عن ثلاث أو انعدم تشتت x. */
export function ols(xs: readonly number[], ys: readonly number[]): Regression | null {
  const n = Math.min(xs.length, ys.length);
  if (n < 3) return null;
  const mx = xs.slice(0, n).reduce((a, b) => a + b, 0) / n;
  const my = ys.slice(0, n).reduce((a, b) => a + b, 0) / n;
  let sxx = 0;
  let sxy = 0;
  let syy = 0;
  for (let i = 0; i < n; i++) {
    sxx += (xs[i] - mx) ** 2;
    sxy += (xs[i] - mx) * (ys[i] - my);
    syy += (ys[i] - my) ** 2;
  }
  if (sxx === 0) return null;
  const slope = sxy / sxx;
  return { slope, intercept: my - slope * mx, r2: syy === 0 ? 1 : (sxy * sxy) / (sxx * syy), n };
}

/** يزاوج سلسلتين سنويتين على السنوات المشتركة: [سنة، x، y]. */
export function pairByYear(xs: readonly Point[], ys: readonly Point[]): [string, number, number][] {
  const y = new Map(ys);
  return xs.filter(([year]) => y.has(year)).map(([year, x]) => [year, x, y.get(year)!]);
}

/* ——— الارتباط ومدى التأثير بين مؤشرين سنويين (تحليل وارتباط، المؤشرات المرتبطة) ——— */

/** يحوّل أي سلسلة إلى سنوية: الفصلية تُجمع (السنوات المكتملة فقط)، والشهرية تُتوسّط، والسنوية كما هي. */
export function toAnnual(points: readonly Point[] | undefined): Map<string, number> {
  const pts = points ?? [];
  if (pts.some((p) => p[0].includes('Q'))) {
    const by = new Map<string, number[]>();
    for (const [p, v] of pts) by.set(p.slice(0, 4), [...(by.get(p.slice(0, 4)) ?? []), v]);
    return new Map([...by].filter(([, v]) => v.length === 4).map(([y, v]) => [y, v.reduce((a, b) => a + b, 0)]));
  }
  if (pts.some((p) => p[0].length > 4)) return new Map(annualMean(pts));
  return new Map(pts);
}

/** التغيّر السنوي % لسنة عن السنة التي قبلها. */
export function annualChange(m: Map<string, number>, y: string): number | null {
  const a = m.get(y);
  const b = m.get(String(Number(y) - 1));
  return a != null && b != null && b !== 0 ? (a / b - 1) * 100 : null;
}

/** معامل ارتباط بيرسون مع عدد الأزواج؛ lag=1 يقارن المؤشر الأول في سنة بالثاني في السنة التالية. */
export function pearson(a: Map<string, number | null>, b: Map<string, number | null>, years: string[], lag = 0): { r: number | null; n: number; from?: string; to?: string } {
  const xs: number[] = [];
  const ys: number[] = [];
  const used: string[] = [];
  for (const y of years) {
    const x = a.get(y);
    const z = b.get(String(Number(y) + lag));
    if (x != null && z != null) {
      xs.push(x);
      ys.push(z);
      used.push(y);
    }
  }
  const n = xs.length;
  if (n < 5) return { r: null, n };
  const mx = xs.reduce((s, v) => s + v, 0) / n;
  const my = ys.reduce((s, v) => s + v, 0) / n;
  let c = 0;
  let vx = 0;
  let vy = 0;
  for (let i = 0; i < n; i++) {
    c += (xs[i] - mx) * (ys[i] - my);
    vx += (xs[i] - mx) ** 2;
    vy += (ys[i] - my) ** 2;
  }
  return { r: vx > 0 && vy > 0 ? c / Math.sqrt(vx * vy) : null, n, from: used[0], to: used[n - 1] };
}

/** زوج سنوي من المحرّك (x) والمؤشر (y) مع التأخر. */
export interface Pair { year: string; x: number; y: number }

export function pairs(a: Map<string, number | null>, b: Map<string, number | null>, years: string[], lag = 0): Pair[] {
  const out: Pair[] = [];
  for (const year of years) {
    const x = a.get(year);
    const y = b.get(String(Number(year) + lag));
    if (x != null && y != null) out.push({ year, x, y });
  }
  return out;
}

/** القيمة الحرجة لاختبار t ذي الطرفين عند 5% بحسب درجات الحرية. */
export function tCritical(df: number): number {
  const table = [12.71, 4.3, 3.18, 2.78, 2.57, 2.45, 2.36, 2.31, 2.26, 2.23, 2.2, 2.18, 2.16, 2.14, 2.13, 2.12, 2.11, 2.1, 2.09, 2.09];
  return df < 1 ? Infinity : (table[df - 1] ?? 2.0);
}

export interface Impact {
  n: number; mx: number; my: number; sxx: number; syy: number; sxy: number;
  r: number; slope: number; intercept: number; r2: number; t: number; significant: boolean; pairs: Pair[];
}

/**
 * انحدار تغيّر المؤشر (y) على تغيّر المحرّك (x) بالمربعات الصغرى:
 * الميل β = Sxy / Sxx (كم يتغيّر المؤشر لكل وحدة تغيّر في المحرّك)، R² = r² (نسبة تقلبات المؤشر التي يفسّرها المحرّك)،
 * و t = r·√(n−2)/√(1−r²) لاختبار أن الأثر ليس صدفة.
 */
export function impact(a: Map<string, number | null>, b: Map<string, number | null>, years: string[], lag = 0): Impact | null {
  const ps = pairs(a, b, years, lag);
  const n = ps.length;
  if (n < 5) return null;
  const mx = ps.reduce((s, p) => s + p.x, 0) / n;
  const my = ps.reduce((s, p) => s + p.y, 0) / n;
  let sxx = 0;
  let syy = 0;
  let sxy = 0;
  for (const p of ps) {
    sxx += (p.x - mx) ** 2;
    syy += (p.y - my) ** 2;
    sxy += (p.x - mx) * (p.y - my);
  }
  if (sxx === 0 || syy === 0) return null;
  const r = sxy / Math.sqrt(sxx * syy);
  const slope = sxy / sxx;
  const t = Math.abs(r) >= 1 ? Infinity : (r * Math.sqrt(n - 2)) / Math.sqrt(1 - r * r);
  return { n, mx, my, sxx, syy, sxy, r, slope, intercept: my - slope * mx, r2: r * r, t, significant: Math.abs(t) >= tCritical(n - 2), pairs: ps };
}

export interface JointImpact { n: number; b1: number; b2: number; t1: number; t2: number; sig1: boolean; sig2: boolean; r2: number; intercept: number }

/**
 * انحدار متعدد: y = a + b1·x1 + b2·x2. يفصل أثر كل محرّك مع تثبيت الآخر،
 * لأن النفط والسكان يتحركان معًا فيتداخل أثراهما في الانحدار البسيط.
 */
export function jointImpact(x1: Map<string, number | null>, x2: Map<string, number | null>, y: Map<string, number | null>, years: string[], lag = 0): JointImpact | null {
  const rows: [number, number, number][] = [];
  for (const year of years) {
    const a = x1.get(year);
    const b = x2.get(year);
    const c = y.get(String(Number(year) + lag));
    if (a != null && b != null && c != null) rows.push([a, b, c]);
  }
  const n = rows.length;
  if (n < 6) return null;
  const m = [0, 1, 2].map((k) => rows.reduce((s, r) => s + r[k], 0) / n);
  let s11 = 0, s22 = 0, s12 = 0, s1y = 0, s2y = 0, syy = 0;
  for (const [a, b, c] of rows) {
    const d1 = a - m[0], d2 = b - m[1], dy = c - m[2];
    s11 += d1 * d1; s22 += d2 * d2; s12 += d1 * d2; s1y += d1 * dy; s2y += d2 * dy; syy += dy * dy;
  }
  const det = s11 * s22 - s12 * s12;
  if (det <= 0 || syy === 0) return null;
  const b1 = (s22 * s1y - s12 * s2y) / det;
  const b2 = (s11 * s2y - s12 * s1y) / det;
  const ssr = syy - b1 * s1y - b2 * s2y;
  const s2 = ssr / (n - 3);
  const t1 = b1 / Math.sqrt((s2 * s22) / det);
  const t2 = b2 / Math.sqrt((s2 * s11) / det);
  const crit = tCritical(n - 3);
  return { n, b1, b2, t1, t2, sig1: Math.abs(t1) >= crit, sig2: Math.abs(t2) >= crit, r2: 1 - ssr / syy, intercept: m[2] - b1 * m[0] - b2 * m[1] };
}

/** ارتباط متحرك: لكل سنة نهاية، الارتباط على آخر `window` سنة حتى تلك السنة. */
export function rollingPearson(a: Map<string, number | null>, b: Map<string, number | null>, years: string[], window: number, lag = 0): Map<string, number | null> {
  const out = new Map<string, number | null>();
  for (let i = window - 1; i < years.length; i++) out.set(years[i], pearson(a, b, years.slice(i - window + 1, i + 1), lag).r);
  return out;
}

/** اتجاه العلاقة عبر الزمن من أول وآخر قيمة للارتباط المتحرك. */
export function trend(rolling: Map<string, number | null>): { label: string; tone: 'up' | 'down' | 'flat' | 'flip'; first: number; last: number } | null {
  const vals = [...rolling.values()].filter((v): v is number => v != null);
  if (vals.length < 3) return null;
  const first = vals[0];
  const last = vals[vals.length - 1];
  if (Math.abs(first) >= 0.2 && Math.abs(last) >= 0.2 && Math.sign(first) !== Math.sign(last)) return { label: 'انقلب اتجاه العلاقة', tone: 'flip', first, last };
  const d = Math.abs(last) - Math.abs(first);
  if (d > 0.15) return { label: 'العلاقة تقوى', tone: 'up', first, last };
  if (d < -0.15) return { label: 'العلاقة تضعف', tone: 'down', first, last };
  return { label: 'العلاقة مستقرة تقريبًا', tone: 'flat', first, last };
}

/** وصف قوة معامل الارتباط واتجاهه بالعربية. */
export function strength(r: number): string {
  const a = Math.abs(r);
  const s = a >= 0.7 ? 'قوي' : a >= 0.4 ? 'متوسط' : a >= 0.2 ? 'ضعيف' : 'شبه معدوم';
  return a < 0.2 ? s : `${s} ${r > 0 ? 'طردي' : 'عكسي'}`;
}
