import type { IndicatorMeta } from './api';
import type { Explanation } from './explain';
import { MACRO_AND_MONEY, TERMS, type Entry } from './explanations';
import { TRADE_OIL_LABOUR_ENERGY } from './explanations2';
import { RICH_OVERRIDES } from './explanationsRich';
import { SOCIETY_GOVERNANCE_DEV } from './explanations3';

export const EXPLANATION_REGISTRY: Record<string, Entry> = { ...MACRO_AND_MONEY, ...TRADE_OIL_LABOUR_ENERGY, ...SOCIETY_GOVERNANCE_DEV, ...RICH_OVERRIDES };

/** عائلات مؤشرات تتشارك شرحًا عامًا حين لا يوجد مدخل خاص بالمؤشر نفسه. */
const FAMILIES: { test: RegExp; entry: Entry }[] = [
  { test: /^imf\.pcps\./, entry: { meaning: 'سعر سلعة عالمية شهري بالدولار من صندوق النقد (طاقة أو معدن أو غذاء).', read: ['الارتفاع يعني غلاء السلعة عالميًا.', 'تتأثر الأسعار بالعرض والطلب والدولار والأحداث الجيوسياسية.'], why: 'أسعار الطاقة تحدد إيرادات عُمان، وأسعار الغذاء والمعادن تؤثر على الواردات والتضخم.' } },
  { test: /^wbpink\./, entry: { meaning: 'سعر سلعة شهري من الورقة الوردية للبنك الدولي، مصدر مستقل عن صندوق النقد.', read: ['قارنه بالمصادر الأخرى أسفل الرسم.'], why: 'تعدد المصادر يكشف الفروق في تقدير الأسعار.' } },
  { test: /^eia\./, entry: { meaning: 'سعر فوري من إدارة معلومات الطاقة الأمريكية.', read: ['اليومي أسرع من الشهري، والمتوسط الشهري محسوب من الأيام.'], why: 'يعكس لحظة السوق.' } },
  { test: /^wb\.GOV_WGI/, entry: { meaning: 'مقياس حوكمة من 0 إلى 100 من مؤشرات الحوكمة العالمية للبنك الدولي.', read: ['100 هو الأفضل.', 'قارن عُمان بالخليج والمعيار العالمي.'], why: 'الحوكمة الجيدة تخفض كلفة الأعمال وتجذب الاستثمار.', terms: [TERMS.percentile] } },
  { test: /^manafeth\./, entry: { meaning: 'إحصاءات الجمارك الرسمية لعُمان من منظومة «منافذ» (بالريال).', read: ['حسب وسيلة النقل: بحري وبري وجوي.', 'السنة الجارية غير مكتملة: تُعرض حتى آخر شهر منشور.'], why: 'تبيّن مسار التجارة الخارجية السلعية.' } },
  { test: /^ncsi\.BOP_/, entry: { meaning: 'بند من ميزان المدفوعات لعُمان بحسب البنك المركزي (مليون ريال).', read: ['الموجب يعني مدخولات؛ السالب مدفوعات.'], why: 'يفصّل تعاملات عُمان مع العالم.', terms: [TERMS.bop] } },
  { test: /^ncsi\.MON_/, entry: { meaning: 'مؤشر نقدي أو مصرفي لعُمان من البنك المركزي (شهري).', read: ['راقب الاتجاه لا الشهر الواحد.'], why: 'يحدد كلفة المال والسيولة في الاقتصاد.' } },
  { test: /^ncsi\.ELEC_/, entry: { meaning: 'مؤشر من قطاع الكهرباء في عُمان.', read: ['يرتفع مع السكان والتكييف والمشاريع.'], why: 'يتصل بأمن الطاقة والتنمية.' } },
  { test: /^ncsi\.LAB_/, entry: { meaning: 'عدد العاملين المسجلين في التأمينات الاجتماعية حسب القطاع والجنسية.', read: ['قارن بين المواطنين والوافدين والحكومي والخاص.'], why: 'يقيس بنية سوق العمل الرسمية.', terms: [TERMS.omanisation] } },
  { test: /^ncsi\.TOUR_/, entry: { meaning: 'مؤشر من قطاع الفنادق والسياحة في عُمان.', read: ['يتأثر بالمواسم.'], why: 'السياحة ركن التنويع.' } },
  { test: /^ncsi\.FOOD_/, entry: { meaning: 'مكوّن من مكوّنات مؤشر الأمن الغذائي الوطني (0–1).', read: ['الأعلى أفضل.'], why: 'يبيّن أي حلقة في سلسلة الغذاء أضعف.', terms: [TERMS.index01] } },
  { test: /^wb\.NW\./, entry: { meaning: 'نوع من رأس المال في حسابات الثروة للبنك الدولي (بالدولار الثابت للفرد).', read: ['قارن بين أنواع الثروة: بشرية ومنتجة وطبيعية وأجنبية.'], why: 'تكشف هل يتحول النفط إلى ثروة دائمة.', terms: [TERMS.perCapita] } },
  { test: /^wb\.GFDD\./, entry: { meaning: 'مؤشر لسلامة القطاع المصرفي من قاعدة التطور المالي العالمية.', read: ['قارن بدول الخليج.'], why: 'سلامة البنوك أساس الاستقرار المالي.' } },
  { test: /^who\./, entry: { meaning: 'مؤشر صحي من منظمة الصحة العالمية.', read: ['قارن مع الخليج والعالم.'], why: 'يقيس صحة السكان ونظام الرعاية.' } },
  { test: /^ilo\./, entry: { meaning: 'مؤشر سوق عمل من منظمة العمل الدولية.', read: ['قارن مع الخليج.'], why: 'يقيس التوظيف والبطالة.', terms: [TERMS.unemp] } },
  { test: /^owid\./, entry: { meaning: 'مؤشر مجمَّع من مصادر أصلية (Ember وGlobal Carbon Project…) عبر Our World in Data.', read: ['الأصل مذكور تحت الرسم.'], why: 'يتيح مقارنة عالمية موحّدة.' } },
  { test: /^unesco\./, entry: { meaning: 'مؤشر تعليمي من معهد اليونسكو للإحصاء.', read: ['قارن مع البنك الدولي في الشريط أسفل الرسم.'], why: 'يقيس تطور التعليم.' } },
  { test: /^undp\./, entry: { meaning: 'مؤشر تنمية بشرية من برنامج الأمم المتحدة الإنمائي.', read: ['قارن مع الخليج والنرويج (مرجع عالمي).'], why: 'يقيس الرفاه لا الدخل وحده.' } },
];

const FREQ: Record<IndicatorMeta['frequency'], string> = { A: 'سنويًا', Q: 'فصليًا', M: 'شهريًا', D: 'يوميًا' };

export interface ResolveInput {
  indicators: readonly IndicatorMeta[];
  /** أسماء الخطوط الظاهرة في الرسم */
  labels: readonly string[];
  /** هل الرسم يقارن دولًا متعددة */
  multi: boolean;
  /** هل فيه توقعات (خط متقطع) */
  hasForecast?: boolean;
}

function entryFor(indicator: IndicatorMeta): Entry | null {
  return EXPLANATION_REGISTRY[indicator.key] ?? FAMILIES.find((f) => f.test.test(indicator.key))?.entry ?? null;
}

/**
 * شرح الرسم: شرح المؤشر المكتوب يدويًا حين يوجد (وعائلته حين لا يوجد)، وإلا شرح عام من بيانات المؤشر.
 * «ماذا يوضح الرسم» دائمًا مولَّد من اسم المؤشر ووحدته وتردده وعدد الخطوط، فيطابق ما على الشاشة.
 */
export function resolveExplanation({ indicators, labels, multi, hasForecast }: ResolveInput): Explanation | null {
  if (indicators.length === 0) return null;
  const first = indicators[0];
  const entries = indicators.map((i) => ({ meta: i, entry: entryFor(i) }));
  const known = entries.filter((e) => e.entry !== null);

  const names = indicators.map((i) => `«${i.name_ar}»`).join(' و');
  const unit = first.unit ? ` بوحدة ${first.unit}` : '';
  const shows =
    `يعرض الرسم ${names}${unit}، ${FREQ[first.frequency]}` +
    (multi ? '، لعُمان ومقارنةً بدول أخرى (غيّر العرض من الأزرار أعلى الرسم)' : '') +
    (labels.length > 1 ? `. الخطوط: ${labels.join('، ')}` : '') +
    '.';

  const meaning =
    known.length > 0
      ? known.length === 1 && indicators.length === 1
        ? (known[0].entry as Entry).meaning
        : entries.map((e) => (e.entry ? `${e.meta.name_ar}: ${e.entry.meaning}` : `${e.meta.name_ar}: مؤشر تنشره الجهة المذكورة تحت الرسم.`)).join(' ')
      : `هذا الرسم يقيس «${first.name_ar}»${unit}. التعريف التفصيلي والمنهجية عند الجهة المصدِّرة (رابطها تحت الرسم).`;

  const read = [
    ...(known[0]?.entry?.read ?? []),
    'مرّر المؤشر فوق الخط لقراءة القيمة الدقيقة لأي فترة.',
    ...(labels.length > 1 ? ['اضغط اسم أي خط في مفتاح الرسم لإخفائه أو إظهاره.'] : []),
    ...(hasForecast ? ['الخط المتقطع توقعات وليس أرقامًا فعلية.'] : []),
  ].slice(0, 7);

  const termSeen = new Set<string>();
  const terms = known
    .flatMap((e) => e.entry?.terms ?? [])
    .filter((x) => (termSeen.has(x.term) ? false : (termSeen.add(x.term), true)))
    .slice(0, 6);

  return {
    meaning,
    shows,
    read,
    why: known[0]?.entry?.why ?? 'يتيح تتبّع تطور هذا المؤشر عبر الزمن ومقارنته بالدول المجاورة لاتخاذ قرار مبنيّ على الأرقام.',
    terms: terms.length > 0 ? terms : undefined,
    caution: known[0]?.entry?.caution,
  };
}
