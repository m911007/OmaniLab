/**
 * لون كل كيان ثابت ولا يتبع ترتيبه في المرشّح (مبدأ «اللون يتبع الكيان»): حذف دولة من المقارنة لا يعيد تلوين الباقي.
 * الخانات بترتيبها: 1 أزرق، 2 برتقالي، 3 أخضر، 4 وردي، 5 سماوي، 6 أصفر، 7 بنفسجي، 8 أحمر. لا توجد لوحة ثمانية ألوان متباعدة تمامًا،
 * فالخانات 5–8 تحمل نمط خط متقطعًا مميزًا (ترميز ثانوي لا يعتمد على اللون وحده، انظر dashFor).
 */
export const SERIES_VARS = ['--series-1', '--series-2', '--series-3', '--series-4', '--series-5', '--series-6', '--series-7', '--series-8'] as const;

const ENTITY_SLOT: Record<string, number> = {
  OMN: 1,
  ARE: 2,
  SAU: 3,
  QAT: 4,
  BHR: 5,
  KWT: 6,
  NOR: 7,
  NZL: 8,
};

/** نمط الخط لكل خانة: الأربع الأولى متصلة، والبقية متقطعة بأنماط مختلفة كي تتميز حتى لو تقارب لونان. */
const DASH_BY_SLOT: Record<number, string | undefined> = { 5: '8 4', 6: '2 3', 7: '10 3 2 3', 8: '1 4' };

export const dashFor = (color: string | undefined): string | undefined => {
  const m = color?.match(/--series-(\d)/);
  return m ? DASH_BY_SLOT[Number(m[1])] : undefined;
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
