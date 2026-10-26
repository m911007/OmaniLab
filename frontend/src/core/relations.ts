/**
 * شبكة العلاقات بين المؤشرات (المحور 3 من خطة «من الأرقام إلى القرار»).
 * الغاية: أن يرى صانع القرار الصورة الأوسع حول الرقم — ما الذي يحرّكه، وماذا سيتأثر به، وماذا يراقب الآن.
 * العلاقات هنا موثّقة اقتصاديًا (لا ارتباطات عشوائية)، ويُقاس حجمها من التاريخ الفعلي؛ ولا تُعرض «أثرًا» إلا إن كانت دالة إحصائيًا.
 */

/**
 * كيف يُحوَّل المؤشر السنوي قبل القياس:
 * pct = التغيّر السنوي % (للمستويات: الأسعار والمبالغ والأعداد)، diff = الفرق بالنقاط (للنسب والمعدلات)، level = كما هو (معدلات نمو أصلًا).
 */
export type Transform = 'pct' | 'diff' | 'level';

export interface RelNode {
  key: string;
  entity: string;
  label: string;
  transform: Transform;
}

export interface RelEdge {
  from: string;
  to: string;
  /** المحرّك يسبق الأثر بسنة */
  lag?: 0 | 1;
  /** الاتجاه المتوقع اقتصاديًا؛ إن خالفته البيانات يُنبَّه القارئ */
  expect: 1 | -1;
  /** لماذا تهم هذه العلاقة القرار */
  note: string;
}

export interface Story {
  slug: string;
  title: string;
  question: string;
  path: string[];
}

export const NODES: Record<string, RelNode> = {
  'ncsi.OIL_PRICE': { key: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'سعر النفط العُماني', transform: 'pct' },
  'ncsi.OIL_PRODUCTION': { key: 'ncsi.OIL_PRODUCTION', entity: 'OMN', label: 'إنتاج النفط', transform: 'pct' },
  'ncsi.REV_TOTAL': { key: 'ncsi.REV_TOTAL', entity: 'OMN', label: 'الإيرادات العامة', transform: 'pct' },
  'ncsi.EXP_TOTAL': { key: 'ncsi.EXP_TOTAL', entity: 'OMN', label: 'الإنفاق العام', transform: 'pct' },
  'imf.GGXCNL_NGDP': { key: 'imf.GGXCNL_NGDP', entity: 'OMN', label: 'رصيد الميزانية (% من الناتج)', transform: 'diff' },
  'imf.GGXWDG_NGDP': { key: 'imf.GGXWDG_NGDP', entity: 'OMN', label: 'الدين الحكومي (% من الناتج)', transform: 'diff' },
  'imf.NGDPD': { key: 'imf.NGDPD', entity: 'OMN', label: 'الناتج المحلي الجاري', transform: 'pct' },
  'imf.NGDP_RPCH': { key: 'imf.NGDP_RPCH', entity: 'OMN', label: 'نمو الناتج الحقيقي', transform: 'level' },
  'imf.BCA_NGDPD': { key: 'imf.BCA_NGDPD', entity: 'OMN', label: 'رصيد الحساب الجاري (% من الناتج)', transform: 'diff' },
  'ncsi.MON_RESERVES_FX': { key: 'ncsi.MON_RESERVES_FX', entity: 'OMN', label: 'احتياطيات النقد الأجنبي', transform: 'pct' },
  'gccstat.POP_NON_CITIZEN': { key: 'gccstat.POP_NON_CITIZEN', entity: 'OMN', label: 'عدد الوافدين', transform: 'pct' },
  'gccstat.EMPLOYED': { key: 'gccstat.EMPLOYED', entity: 'OMN', label: 'عدد العاملين', transform: 'pct' },
  'gccstat.UNEMPLOYMENT': { key: 'gccstat.UNEMPLOYMENT', entity: 'OMN', label: 'معدل البطالة', transform: 'diff' },
  'imf.PCPIPCH': { key: 'imf.PCPIPCH', entity: 'OMN', label: 'التضخم (متوسط سنوي)', transform: 'level' },
  'fao.ffpi': { key: 'fao.ffpi', entity: 'WLD', label: 'أسعار الغذاء العالمية (الفاو)', transform: 'pct' },
  'us.effr': { key: 'us.effr', entity: 'USA', label: 'الفائدة الأمريكية (الأموال الفيدرالية)', transform: 'diff' },
  'ncsi.MON_RATE_REPO': { key: 'ncsi.MON_RATE_REPO', entity: 'OMN', label: 'سعر الريبو العُماني', transform: 'diff' },
  'ncsi.MON_RATE_LENDING_RO': { key: 'ncsi.MON_RATE_LENDING_RO', entity: 'OMN', label: 'سعر الإقراض بالريال', transform: 'diff' },
  'ncsi.MON_CREDIT_PRIVATE': { key: 'ncsi.MON_CREDIT_PRIVATE', entity: 'OMN', label: 'ائتمان القطاع الخاص', transform: 'pct' },
};

export const EDGES: RelEdge[] = [
  { from: 'ncsi.OIL_PRICE', to: 'ncsi.REV_TOTAL', expect: 1, note: 'النفط والغاز مصدر معظم إيرادات الدولة؛ تقلّب السعر ينتقل مباشرة إلى ما تجنيه الخزينة.' },
  { from: 'ncsi.REV_TOTAL', to: 'ncsi.EXP_TOTAL', expect: 1, note: 'الإنفاق يتبع الإيرادات: ارتفاعها يفتح المجال للإنفاق الإنمائي، وتراجعها يفرض الترشيد.' },
  { from: 'ncsi.OIL_PRICE', to: 'imf.GGXCNL_NGDP', expect: 1, note: 'سعر النفط أهم محدد لعجز الميزانية أو فائضها؛ لذا تُبنى الميزانية على سعر افتراضي للبرميل.' },
  { from: 'imf.GGXCNL_NGDP', to: 'imf.GGXWDG_NGDP', lag: 1, expect: -1, note: 'العجز يُموَّل بالاقتراض فيرفع الدين في السنة التالية، والفائض يتيح سداده.' },
  { from: 'ncsi.OIL_PRICE', to: 'imf.NGDPD', expect: 1, note: 'الناتج بالأسعار الجارية يرتفع وينخفض مع قيمة النفط المنتج.' },
  { from: 'ncsi.OIL_PRODUCTION', to: 'imf.NGDP_RPCH', expect: 1, note: 'الكميات المنتجة (وفق حصص أوبك+) تحرك النمو الحقيقي مباشرة، لا السعر.' },
  { from: 'ncsi.OIL_PRICE', to: 'imf.BCA_NGDPD', expect: 1, note: 'صادرات النفط والغاز تحدد فائض الحساب الجاري أو عجزه مع العالم.' },
  { from: 'imf.BCA_NGDPD', to: 'ncsi.MON_RESERVES_FX', expect: 1, note: 'فائض الحساب الجاري يدخل عملة صعبة تعزز الاحتياطيات التي تحمي ربط الريال.' },
  { from: 'ncsi.EXP_TOTAL', to: 'gccstat.POP_NON_CITIZEN', lag: 1, expect: 1, note: 'المشاريع الحكومية تستقدم عمالة وافدة؛ تقليص الإنفاق يخفض أعدادهم بعد فترة.' },
  { from: 'gccstat.POP_NON_CITIZEN', to: 'imf.PCPIPCH', expect: 1, note: 'زيادة الوافدين ترفع الطلب على السكن والخدمات فتضغط على الأسعار.' },
  { from: 'gccstat.POP_NON_CITIZEN', to: 'gccstat.EMPLOYED', expect: 1, note: 'معظم الوظائف الجديدة في القطاع الخاص يشغلها وافدون؛ أعدادهم تعكس حركة التوظيف.' },
  { from: 'imf.NGDP_RPCH', to: 'gccstat.EMPLOYED', expect: 1, note: 'النمو الحقيقي يخلق وظائف؛ ضعفه يبطئ التوظيف.' },
  { from: 'gccstat.EMPLOYED', to: 'gccstat.UNEMPLOYMENT', expect: -1, note: 'نمو التوظيف أسرع من نمو الباحثين عن عمل يخفض البطالة.' },
  { from: 'fao.ffpi', to: 'imf.PCPIPCH', expect: 1, note: 'عُمان تستورد معظم غذائها؛ أسعار الغذاء العالمية تنتقل إلى التضخم المحلي.' },
  { from: 'us.effr', to: 'ncsi.MON_RATE_REPO', expect: 1, note: 'ربط الريال بالدولار يجعل البنك المركزي يتبع الفيدرالي الأمريكي في أسعار الفائدة.' },
  { from: 'ncsi.MON_RATE_REPO', to: 'ncsi.MON_RATE_LENDING_RO', expect: 1, note: 'كلفة اقتراض البنوك من المركزي تنتقل إلى ما تتقاضاه من الأفراد والشركات.' },
  { from: 'ncsi.MON_RATE_LENDING_RO', to: 'ncsi.MON_CREDIT_PRIVATE', lag: 1, expect: -1, note: 'ارتفاع كلفة الاقتراض يبطئ نمو القروض للقطاع الخاص والاستثمار.' },
];

export const STORIES: Story[] = [
  { slug: 'oil-budget', title: 'النفط والميزانية والدين', question: 'إذا تغيّر سعر النفط، ماذا يحدث للإيرادات والعجز والدين؟', path: ['ncsi.OIL_PRICE', 'ncsi.REV_TOTAL', 'ncsi.EXP_TOTAL'] },
  { slug: 'oil-balance-debt', title: 'النفط ورصيد الميزانية والدين', question: 'كم يتحسن الرصيد مع كل ارتفاع في النفط، وكم ينخفض الدين بعده؟', path: ['ncsi.OIL_PRICE', 'imf.GGXCNL_NGDP', 'imf.GGXWDG_NGDP'] },
  { slug: 'oil-people-prices', title: 'الإنفاق والوافدون والأسعار', question: 'هل يجلب الإنفاق الحكومي الوافدين، وهل يرفع ذلك الأسعار؟', path: ['ncsi.EXP_TOTAL', 'gccstat.POP_NON_CITIZEN', 'imf.PCPIPCH'] },
  { slug: 'us-rates-credit', title: 'الفائدة الأمريكية والائتمان', question: 'إذا رفع الفيدرالي الفائدة، كم ترتفع كلفة الاقتراض في عُمان وكيف يتأثر الائتمان؟', path: ['us.effr', 'ncsi.MON_RATE_REPO', 'ncsi.MON_RATE_LENDING_RO', 'ncsi.MON_CREDIT_PRIVATE'] },
  { slug: 'food-inflation', title: 'الغذاء العالمي والتضخم', question: 'كم ينتقل غلاء الغذاء العالمي إلى التضخم في عُمان؟', path: ['fao.ffpi', 'imf.PCPIPCH'] },
  { slug: 'people-jobs', title: 'السكان والعمل والبطالة', question: 'هل يواكب التوظيف نمو السكان والاقتصاد؟', path: ['imf.NGDP_RPCH', 'gccstat.EMPLOYED', 'gccstat.UNEMPLOYMENT'] },
  { slug: 'oil-external', title: 'النفط والحساب الجاري والاحتياطيات', question: 'كيف يحمي النفط احتياطيات ربط الريال؟', path: ['ncsi.OIL_PRICE', 'imf.BCA_NGDPD', 'ncsi.MON_RESERVES_FX'] },
];

/** مؤشرات تُعرض في البطاقات بمفتاح وتُقاس علاقاتها بسلسلة أطول تقيس الشيء نفسه. */
const ALIASES: Record<string, string> = {
  'ncsi.CPI_INFLATION': 'imf.PCPIPCH',
  'ilo.unemployment': 'gccstat.UNEMPLOYMENT',
  'ncsi.POP_EXPAT': 'gccstat.POP_NON_CITIZEN',
};

export const nodeFor = (key: string): string => ALIASES[key] ?? key;

/** المحرّكات (ما يؤثر في المؤشر) والآثار (ما يتأثر به). */
export function relatedTo(indicator: string): { drivers: RelEdge[]; effects: RelEdge[] } {
  const key = nodeFor(indicator);
  return { drivers: EDGES.filter((e) => e.to === key), effects: EDGES.filter((e) => e.from === key) };
}

export function edgeBetween(from: string, to: string): RelEdge | undefined {
  return EDGES.find((e) => e.from === from && e.to === to);
}

/** وحدة قراءة التغيّر بعد التحويل، وخطوة العرض («كل 10%» أو «كل نقطة»). */
export function transformUnit(t: Transform): { unit: string; step: number; stepText: string } {
  return t === 'pct' ? { unit: '%', step: 10, stepText: '+10%' } : { unit: 'نقطة', step: 1, stepText: '+1 نقطة' };
}

/** يحوّل سلسلة سنوية بحسب التحويل المطلوب. */
export function transformAnnual(annual: Map<string, number>, t: Transform): Map<string, number | null> {
  const out = new Map<string, number | null>();
  for (const [y, v] of annual) {
    if (t === 'level') {
      out.set(y, v);
      continue;
    }
    const prev = annual.get(String(Number(y) - 1));
    if (prev == null) continue;
    out.set(y, t === 'diff' ? v - prev : prev !== 0 ? (v / prev - 1) * 100 : null);
  }
  return out;
}
