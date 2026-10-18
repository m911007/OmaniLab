/**
 * التصنيف الائتماني السيادي لعُمان عبر أكبر ثلاث وكالات، وتقييم نهاية كل سنة (أو آخر تحديث خلالها).
 * مجمَّع يدويًا من البيانات الصحفية الرسمية للوكالات (S&P Global وMoody's وFitch) ومن تغطية إخبارية لتحديثات 2023–2025؛ لا واجهة آلية مجانية لها.
 * عند صدور قرار جديد يُضاف عمود سنته هنا. آخر تحديث: 2025.
 */
export const RATING_YEARS = ['2015', '2016', '2017', '2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025'];

export type Rating = [grade: string | null, outlook: string | null];

export const OMAN_RATINGS: Record<'sp' | 'moodys' | 'fitch', Rating[]> = {
  sp: [['BBB+', 'سلبي'], ['BBB-', 'مستقر'], ['BB', 'مستقر'], ['BB', 'مستقر'], ['BB', 'مستقر'], ['B+', 'مستقر'], ['B+', 'إيجابي'], ['BB', 'مستقر'], ['BB+', 'مستقر'], ['BBB-', 'مستقر'], ['BBB-', 'مستقر']],
  moodys: [['A1', 'سلبي'], ['Baa1', 'مستقر'], ['Baa2', 'سلبي'], ['Baa3', 'سلبي'], ['Ba1', 'سلبي'], ['Ba3', 'سلبي'], ['Ba3', 'مستقر'], ['Ba3', 'إيجابي'], ['Ba1', 'مستقر'], ['Ba1', 'إيجابي'], ['Baa3', 'مستقر']],
  fitch: [[null, null], [null, null], ['BBB-', 'سلبي'], ['BB+', null], ['BB+', null], ['BB-', 'سلبي'], ['BB-', 'سلبي'], ['BB', 'مستقر'], ['BB+', 'مستقر'], ['BB+', 'إيجابي'], ['BBB-', 'مستقر']],
};

/** قسيمة السند السيادي الدولي القياسي الذي أصدرته عُمان في كل سنة (%)، وفجوات حيث لا إصدار دولي مؤكَّد. */
export const BORROWING_RATE: (number | null)[] = [null, 3.5, 5.375, 5.625, 6.0, 7.375, 6.25, null, null, 4.875, 4.525];
export const BORROWING_NOTE: (string | null)[] = [null, 'صكوك سيادية دولية، يونيو 2016', 'سند دولي 10 سنوات، مارس 2017', 'سند دولي 10 سنوات، يناير 2018', 'سند دولي نحو 10 سنوات، أغسطس 2019', 'سند دولي 12 سنة، أكتوبر 2020', 'سند دولي 10 سنوات، يناير 2021', null, null, 'سند دولي 6 سنوات، مايو 2024', 'سند دولي 8 سنوات، أكتوبر 2025'];

/** درجة موحدة (0 أدنى … 21 أعلى) لمقارنة سلّمي الوكالات الأبجدي وموديز. */
export const NOTCH: Record<string, number> = {
  AAA: 21, 'AA+': 20, AA: 19, 'AA-': 18, 'A+': 17, A: 16, 'A-': 15, 'BBB+': 14, BBB: 13, 'BBB-': 12, 'BB+': 11, BB: 10, 'BB-': 9, 'B+': 8, B: 7, 'B-': 6, 'CCC+': 5, CCC: 4, 'CCC-': 3, CC: 2, C: 1, D: 0,
  Aaa: 21, Aa1: 20, Aa2: 19, Aa3: 18, A1: 17, A2: 16, A3: 15, Baa1: 14, Baa2: 13, Baa3: 12, Ba1: 11, Ba2: 10, Ba3: 9, B1: 8, B2: 7, B3: 6, Caa1: 5, Caa2: 4, Caa3: 3, Ca: 1,
};

export const isInvestmentGrade = (grade: string | null): boolean => grade != null && (NOTCH[grade] ?? -1) >= 12;

/** أحمر للمضاربة، كهرماني عند العتبة، تركوازي للدرجة الاستثمارية. */
export function gradeColor(grade: string | null): string {
  const n = grade ? NOTCH[grade] : undefined;
  if (n === undefined) return 'var(--surface-2)';
  const stops: [number, number[]][] = [[0, [196, 68, 65]], [9, [196, 68, 65]], [11, [214, 158, 63]], [12, [39, 150, 121]], [17, [30, 190, 180]], [21, [26, 216, 206]]];
  let lo = stops[0]!;
  let hi = stops[stops.length - 1]!;
  for (let i = 0; i < stops.length - 1; i++) {
    if (n >= stops[i]![0] && n <= stops[i + 1]![0]) {
      lo = stops[i]!;
      hi = stops[i + 1]!;
      break;
    }
  }
  const t = Math.max(0, Math.min(1, (n - lo[0]) / (hi[0] - lo[0] || 1)));
  const c = lo[1].map((v, i) => Math.round(v + (hi[1]![i]! - v) * t));
  return `rgb(${c[0]},${c[1]},${c[2]})`;
}
