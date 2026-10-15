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
