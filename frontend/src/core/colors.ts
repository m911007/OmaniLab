/**
 * لون كل كيان ثابت ولا يتبع ترتيبه في المرشّح (مبدأ «اللون يتبع الكيان»): حذف دولة من المقارنة لا يعيد تلوين الباقي.
 * الخانات من اللوحة الفئوية المعتمدة بترتيبها (1 أزرق، 2 برتقالي، 3 تركوازي، 4 أصفر، 5 أرجواني فاتح، 6 أخضر، 7 بنفسجي، 8 أحمر).
 */
export const SERIES_VARS = ['--series-1', '--series-2', '--series-3', '--series-4', '--series-5', '--series-6', '--series-7', '--series-8'] as const;

const ENTITY_SLOT: Record<string, number> = {
  OMN: 1,
  ARE: 2,
  NOR: 3,
  KWT: 5,
  SAU: 6,
  QAT: 7,
  BHR: 8,
  NZL: 4,
};

export const cssVar = (slot: number): string => `var(${SERIES_VARS[(slot - 1) % SERIES_VARS.length]})`;

/**
 * ألوان كيانات المخطط الواحد: المعروف بخانته الثابتة، وغير المعروف (دولة تُختار في صفحة الأسواق) بأول خانة لم يشغلها
 * كيان آخر في المخطط نفسه، بترتيب ثابت (لا توليد ألوان جديدة، ولا دوران بعد الثماني).
 */
export function assignColors(codes: string[]): Record<string, string> {
  const result: Record<string, string> = {};
  const used = new Set<number>();
  for (const c of codes) {
    if (ENTITY_SLOT[c]) {
      result[c] = cssVar(ENTITY_SLOT[c]);
      used.add(ENTITY_SLOT[c]);
    }
  }
  for (const c of codes) {
    if (result[c]) continue;
    let slot = 1;
    while (used.has(slot) && slot < 8) slot++;
    result[c] = slot <= 8 && !used.has(slot) ? cssVar(slot) : 'var(--series-other)';
    used.add(slot);
  }
  return result;
}

export const GCC_CODES = ['OMN', 'SAU', 'ARE', 'QAT', 'KWT', 'BHR'] as const;
export const PEER_CODES = ['OMN', 'SAU', 'ARE', 'QAT', 'KWT', 'BHR', 'NOR', 'NZL'] as const;
