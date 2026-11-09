export type ChartKind = 'line' | 'columns' | 'stacked';
/** أوضاع العرض الأساسية للرسم الواحد: خطوط، أعمدة، مكدّس، دائري، جدول. */
export type Mode = 'lines' | 'bars' | 'stack' | 'pie' | 'table';

interface SeriesShape {
  points: readonly (readonly [string, number])[];
}

/** وحدات لا يصلح لها العمود: نسب ومؤشرات ومعدلات وأسعار «لكل». العمود يناسب كميات تُجمَّع (تدفقات ومخزونات سنوية). */
const NOT_BARS = /[%/]|مؤشر|نقطة|معدل|نسبة|لكل|index|rate/i;

/** هل تُعرض هذه الخطوط أعمدة أيضًا؟ سنوية فقط (أعمدة شهرية مئات)، وحتى 4 خطوط، ووحدة كمية. */
export function canShowBars(series: readonly SeriesShape[], unit: string | null | undefined): boolean {
  if (series.length === 0 || series.length > 4) return false;
  if (unit && NOT_BARS.test(unit)) return false;
  return series.every((s) => s.points.length >= 3 && s.points.every((p) => /^\d{4}$/.test(p[0])));
}

/** هل يصلح الدائري لتركيب هذه السلاسل؟ مكوّنان فأكثر (حتى 8) كلها بقيم غير سالبة، وهي أجزاء من كلّ (مكدّس). */
export function canShowPie(series: readonly SeriesShape[]): boolean {
  if (series.length < 2 || series.length > 8) return false;
  return series.every((s) => s.points.every((p) => p[1] >= 0)) && series.some((s) => s.points.length > 0);
}

/** الوضع الأساسي لنوع الرسم، والأوضاع البديلة التي تناسب بياناته. */
export function modesFor(kind: ChartKind, series: readonly SeriesShape[], unit: string | null | undefined): Mode[] {
  const primary: Mode = kind === 'stacked' ? 'stack' : kind === 'columns' ? 'bars' : 'lines';
  const out: Mode[] = [primary];
  if (kind !== 'line') out.push('lines');
  else if (canShowBars(series, unit)) out.push('bars');
  if (kind === 'stacked' && canShowPie(series)) out.push('pie');
  out.push('table');
  return out;
}
