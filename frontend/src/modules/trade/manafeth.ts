export const MONTHS = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

/** إصدار منافذ نصًا «منافذ — حتى 2026-07» → آخر سنة وشهر منشورين. */
export function parseManafethVersion(version: string | null | undefined): { year: number; month: number } | null {
  const m = version?.match(/(\d{4})-(\d{1,2})/);
  return m ? { year: Number(m[1]), month: Number(m[2]) } : null;
}
