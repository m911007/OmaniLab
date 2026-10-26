/** تنسيق الأرقام والتواريخ للعرض العربي. الأرقام لاتينية (0-9) بفواصل ألوف؛ التواريخ بتوقيت مسقط. */

const TZ = 'Asia/Muscat';
const LOCALE = 'ar-OM-u-nu-latn';

const SCALES: [number, string][] = [
  [1e12, 'تريليون'],
  [1e9, 'مليار'],
  [1e6, 'مليون'],
  [1e3, 'ألف'],
];

export function formatNumber(v: number, maxDigits = 2): string {
  if (!Number.isFinite(v)) return '—';
  return new Intl.NumberFormat('en-US', { maximumFractionDigits: maxDigits }).format(v);
}

/** عدد كبير مختصرًا بالعربية: 21,208,630,443 → «21.2 مليار». */
export function compact(v: number, digits = 1): string {
  if (!Number.isFinite(v)) return '—';
  const abs = Math.abs(v);
  for (const [size, word] of SCALES) {
    if (abs >= size) return `${formatNumber(v / size, digits)} ${word}`;
  }
  return formatNumber(v, abs >= 100 ? 0 : digits);
}

/** رقم وكلمة المقياس منفصلان، كي يُرتَّبا صحيحًا في سياق عربي: «21.2» ثم «مليار» (الرقم أولًا من اليمين). */
export function compactParts(v: number, digits = 1): { num: string; word: string | null } {
  if (!Number.isFinite(v)) return { num: '—', word: null };
  const abs = Math.abs(v);
  for (const [size, word] of SCALES) {
    if (abs >= size) return { num: formatNumber(v / size, digits), word };
  }
  // القيم الصغيرة (مؤشرات 0–1) تحتاج منازل أكثر كي لا تتساوى ظاهريًا.
  const places = abs >= 100 ? 0 : abs >= 10 ? digits : abs >= 1 ? 2 : 3;
  return { num: formatNumber(v, places), word: null };
}

/** نص رقمي خالص (للجداول والتلميحات): فواصل ألوف وعدد كسور بحسب المقدار، بلا كلمات عربية داخله. */
export function formatValue(v: number, unit?: string | null): string {
  if (!Number.isFinite(v)) return '—';
  const abs = Math.abs(v);
  if (unit?.includes('%')) return formatNumber(v, 1);
  if (abs >= 1000) return formatNumber(v, 0);
  if (abs >= 100) return formatNumber(v, 1);
  return formatNumber(v, 2);
}

/** المحور (SVG بالاتجاه اللاتيني): اختصار لاتيني K/M/B/T لا يلتبس مع اتجاه النص العربي. */
export function axisNumber(v: number): string {
  const abs = Math.abs(v);
  const scales: [number, string][] = [[1e12, 'T'], [1e9, 'B'], [1e6, 'M'], [1e3, 'K']];
  for (const [size, suffix] of scales) {
    if (abs >= size) return `${formatNumber(v / size, abs / size >= 100 ? 0 : 1)}${suffix}`;
  }
  return formatNumber(v, abs >= 100 ? 0 : 1);
}

export function formatPeriod(p: string): string {
  if (/^\d{4}$/.test(p)) return p;
  const q = /^(\d{4})-Q([1-4])$/.exec(p);
  if (q) return `الربع ${q[2]} ${q[1]}`;
  const m = /^(\d{4})-(\d{2})$/.exec(p);
  if (m) return new Intl.DateTimeFormat(LOCALE, { month: 'long', year: 'numeric', timeZone: 'UTC' }).format(new Date(Date.UTC(+m[1], +m[2] - 1, 1)));
  return p;
}

/** تسمية محور مختصرة: 2025-09 → «09/25». */
export function shortPeriod(p: string): string {
  const m = /^(\d{4})-(\d{2})$/.exec(p);
  if (m) return `${m[2]}/${m[1].slice(2)}`;
  const q = /^(\d{4})-Q([1-4])$/.exec(p);
  if (q) return `Q${q[2]}/${q[1].slice(2)}`;
  return p;
}

export function yearOf(p: string): number {
  return Number.parseInt(p.slice(0, 4), 10);
}

export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Intl.DateTimeFormat(LOCALE, { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ }).format(new Date(iso));
}

export function formatDate(iso: string | null | undefined): string {
  if (!iso) return '—';
  return new Intl.DateTimeFormat(LOCALE, { dateStyle: 'long', timeZone: TZ }).format(new Date(iso));
}

const UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['day', 86_400_000],
  ['hour', 3_600_000],
  ['minute', 60_000],
];

export function relativeTime(iso: string | null | undefined, now: number = Date.now()): string {
  if (!iso) return '—';
  const diff = new Date(iso).getTime() - now;
  const rtf = new Intl.RelativeTimeFormat('ar', { numeric: 'auto' });
  for (const [unit, ms] of UNITS) {
    if (Math.abs(diff) >= ms) return rtf.format(Math.round(diff / ms), unit);
  }
  return 'الآن';
}

export interface Change {
  /** المقدار رقمًا فقط، وsuffix الكلمة العربية بعده (نقطة مئوية) أو «%»؛ يُعرضان منفصلين لسلامة الاتجاه. */
  amount: string;
  suffix: string;
  direction: 'up' | 'down' | 'flat';
}

export function changeBetween(current: number, previous: number, unit?: string | null): Change | null {
  if (!Number.isFinite(current) || !Number.isFinite(previous)) return null;
  if (unit?.includes('%')) {
    const d = current - previous;
    return { amount: formatNumber(Math.abs(d), 1), suffix: 'نقطة مئوية', direction: Math.abs(d) < 0.05 ? 'flat' : d > 0 ? 'up' : 'down' };
  }
  if (previous === 0) return null;
  const pct = ((current - previous) / Math.abs(previous)) * 100;
  return { amount: formatNumber(Math.abs(pct), 1), suffix: '%', direction: Math.abs(pct) < 0.05 ? 'flat' : pct > 0 ? 'up' : 'down' };
}

/** اسم الكيان للعرض: العربي إن وُجد وإلا الإنجليزي. */
export function entityName(e: { name_ar: string | null; name_en: string }): string {
  return e.name_ar ?? e.name_en;
}

/** الريال العُماني مربوط بالدولار رسميًا منذ 1986: 1 دولار = 0.3845 ريال. */
export const OMR_PER_USD = 0.3845;

/** يستبدل «دولار» بـ«ريال عُماني» في وحدة مؤشر معبَّر عنه بالدولار (مثل «مليار دولار» → «مليار ريال عُماني»). */
export function toOmrUnit(unit: string | null): string | null {
  return unit ? unit.replace(/دولار( دولي)?/g, 'ريال عُماني').replace(/US\$|\$/g, 'ر.ع') : unit;
}
