import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart, type RangeOption } from '@/core/components/SeriesChart';

const us = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'USA', label, color });

/** نطاقات الرسوم اليومية: السنة الأخيرة ثم 5 و10 و25 سنة والتاريخ كله. */
const DAILY: readonly RangeOption[] = [
  { value: '1y', label: 'سنة', months: 12 },
  { value: '5y', label: '5 سنوات', months: 60 },
  { value: '10y', label: '10 سنوات', months: 120 },
  { value: '25y', label: '25 سنة', months: 300 },
  { value: 'all', label: 'الكل', months: 0 },
];
/** الرسوم الشهرية تبدأ بعشر سنوات. */
const MONTHLY: readonly RangeOption[] = [
  { value: '3y', label: '3 سنوات', months: 36 },
  { value: '10y', label: '10 سنوات', months: 120 },
  { value: '25y', label: '25 سنة', months: 300 },
  { value: 'all', label: 'الكل', months: 0 },
];
const H = 340;

export default function UsMarketsPage() {
  return (
    <>
      <PageHead
        title="السوق الأمريكية"
        lead="نبض أكبر اقتصاد وسوق مالية في العالم بتاريخ طويل (الأسهم منذ 1975 والتقلب منذ 1990 والعوائد منذ 1990 والدين منذ 1993 والتضخم والتوظيف منذ 1970): مؤشرات الأسهم وعائلة التقلب، وأسعار الفائدة والسندات والتضخم المتوقع، والتضخم وبنوده، وسوق العمل، والدين العام والإنفاق والإيرادات. كل رسم يتيح اختيار المدى الزمني. مصادرها رسمية وتُحدَّث تلقائيًا (كل ساعة للأسهم والفائدة)، وهي إغلاقات يومية رسمية لا أسعار لحظية."
      />

      <div className="grid cols-4">
        <KeyStat trend entity="USA" indicator="us.sp500" label="مؤشر S&P 500" goodWhen="up" />
        <KeyStat trend entity="USA" indicator="us.djia" label="مؤشر داو جونز" goodWhen="up" />
        <KeyStat trend entity="USA" indicator="us.russell2000" label="راسل 2000 (الشركات الصغيرة)" goodWhen="up" />
        <KeyStat trend entity="USA" indicator="us.vix" label="مؤشر التقلب VIX" goodWhen="down" />
        <KeyStat trend entity="USA" indicator="us.yield_10y" label="عائد سندات 10 سنوات (%)" />
        <KeyStat trend entity="USA" indicator="us.yield_2y" label="عائد سندات سنتين (%)" />
        <KeyStat trend entity="USA" indicator="us.spread_10y_2y" label="فارق 10 سنوات − سنتين" />
        <KeyStat trend entity="USA" indicator="us.breakeven_10y" label="التضخم المتوقع 10 سنوات (%)" />
        <KeyStat trend entity="USA" indicator="us.effr" label="الفائدة الفدرالية الفعلية (%)" />
        <KeyStat trend entity="USA" indicator="us.cpi_yoy" label="التضخم السنوي (CPI)" goodWhen="down" />
        <KeyStat trend entity="USA" indicator="us.unemployment_rate" label="معدل البطالة (%)" goodWhen="down" />
        <KeyStat trend entity="USA" indicator="us.public_debt" label="الدين العام (دولار)" compactValue />
      </div>

      <Section title="سوق الأسهم" lead="مؤشرات الأسهم الأمريكية الكبرى بمداها الكامل: لاحظ مراحل الصعود والانهيار (2000 و2008 و2020) وما بعدها.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="10y" title="مؤشر S&P 500" subtitle="الشركات الـ500 الأكبر — إغلاق يومي" lines={[us('us.sp500', 'S&P 500')]} />
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="10y" title="مؤشر داو جونز الصناعي" subtitle="30 شركة كبرى — إغلاق يومي" lines={[us('us.djia', 'داو جونز')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="مؤشر S&P 100" subtitle="أكبر 100 شركة" lines={[us('us.sp100', 'S&P 100')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="مؤشر راسل 2000" subtitle="الشركات الصغيرة" lines={[us('us.russell2000', 'راسل 2000')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="25y" title="استراتيجيات الخيارات: البيع المغطى وبيع الأوتاد" subtitle="مؤشرات Cboe على S&P 500" lines={[us('us.bxm', 'البيع المغطى BXM'), us('us.putwrite', 'بيع الأوتاد PUT')]} />
      </Section>

      <Section title="التقلب والمخاطر" lead="مقاييس الخوف المستخلصة من أسعار الخيارات: ارتفاع VIX فوق 30 يعني توترًا شديدًا، وفوق 50 ذعرًا نادرًا.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="5y" title="مؤشر التقلب VIX (إغلاق وأعلى وأدنى اليوم)" lines={[us('us.vix', 'إغلاق'), us('us.vix_high', 'أعلى'), us('us.vix_low', 'أدنى')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="هيكل التقلب: 9 أيام إلى 6 أشهر" subtitle="عندما يرتفع القصير فوق الطويل فهذا إنذار" lines={[us('us.vix9d', '9 أيام'), us('us.vix', '30 يومًا (VIX)'), us('us.vix3m', '3 أشهر'), us('us.vix6m', '6 أشهر')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="تقلب التقلب (VVIX) ومخاطر الذيل (SKEW)" lines={[us('us.vvix', 'VVIX'), us('us.skew', 'SKEW')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="التقلب حسب الأصل" subtitle="ناسداك وراسل والنفط والذهب" lines={[us('us.vxn', 'ناسداك 100'), us('us.rvx', 'راسل 2000'), us('us.ovx', 'النفط'), us('us.gvz', 'الذهب')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="تقلب الأسواق الناشئة وسندات الخزانة" lines={[us('us.vxeem', 'الأسواق الناشئة'), us('us.vxtlt', 'سندات الخزانة الطويلة')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="الارتباط الضمني بين الأسهم (3 أشهر)" subtitle="ارتفاعه يعني أن الأسهم تتحرك معًا" lines={[us('us.cor3m', 'الارتباط الضمني')]} />
      </Section>

      <Section title="سعر الفائدة والسندات" lead="الريال العُماني مربوط بالدولار، فتنتقل إليه الفائدة الأمريكية وتحدد كلفة دين عُمان وتمويلها.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="10y" title="عوائد الخزانة الأمريكية: القصيرة والطويلة" subtitle="% — يومي منذ 1990" lines={[us('us.yield_3m', '3 أشهر'), us('us.yield_2y', 'سنتان'), us('us.yield_10y', '10 سنوات'), us('us.yield_30y', '30 سنة')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="باقي المنحنى" subtitle="% — شهر وسنة و3 و5 و7 و20 سنة" lines={[us('us.yield_1m', 'شهر'), us('us.yield_1y', 'سنة'), us('us.yield_3y', '3 سنوات'), us('us.yield_5y', '5 سنوات'), us('us.yield_7y', '7 سنوات'), us('us.yield_20y', '20 سنة')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="25y" title="انعكاس منحنى العائد" subtitle="نقطة مئوية — تحت الصفر = انعكاس، وغالبًا يسبق الركود" lines={[us('us.spread_10y_2y', '10 سنوات − سنتان'), us('us.spread_10y_3m', '10 سنوات − 3 أشهر')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="العائد الاسمي مقابل الحقيقي (10 سنوات)" subtitle="% — الحقيقي من سندات TIPS منذ 2003" lines={[us('us.yield_10y', 'اسمي'), us('us.real_yield_10y', 'حقيقي')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="التضخم المتوقع من السوق" subtitle="% — الفرق بين العائد الاسمي والحقيقي" lines={[us('us.breakeven_5y', '5 سنوات'), us('us.breakeven_10y', '10 سنوات')]} />
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="25y" title="الفائدة الفدرالية والمدى المستهدف" subtitle="% — يومي" lines={[us('us.effr', 'الفائدة الفعلية (EFFR)'), us('us.fed_target_upper', 'الحد الأعلى للمدى'), us('us.fed_target_lower', 'الحد الأدنى للمدى')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="أسعار الريبو والتمويل بين عشية وضحاها" subtitle="% — منذ 2018" lines={[us('us.sofr', 'SOFR'), us('us.tgcr', 'الريبو الثلاثي TGCR'), us('us.bgcr', 'الريبو الواسع BGCR'), us('us.obfr', 'البنكي OBFR')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="حجم التداول اليومي" subtitle="مليار دولار" lines={[us('us.sofr_volume', 'SOFR'), us('us.effr_volume', 'الأموال الفدرالية')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="متوسط الفائدة على الدين الأمريكي القابل للتداول" subtitle="% — شهري" lines={[us('us.avg_rate_bills', 'أذونات'), us('us.avg_rate_notes', 'سندات متوسطة'), us('us.avg_rate_bonds', 'سندات طويلة'), us('us.avg_rate_marketable', 'الإجمالي')]} />
      </Section>

      <Section title="التضخم" lead="من أهم ما يحدد قرار الاحتياطي الفدرالي بشأن الفائدة: التضخم العام والأساسي وبنوده الكبرى.">
        <SeriesChart wide height={H} ranges={MONTHLY} defaultRange="25y" title="التضخم السنوي: العام والأساسي" subtitle="% — شهري" lines={[us('us.cpi_yoy', 'العام'), us('us.core_cpi_yoy', 'الأساسي (دون الغذاء والطاقة)')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم الغذاء والطاقة" subtitle="% سنويًا" lines={[us('us.cpi_food_yoy', 'الغذاء'), us('us.cpi_energy_yoy', 'الطاقة')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم السكن والإيجارات" subtitle="% سنويًا" lines={[us('us.cpi_shelter_yoy', 'السكن'), us('us.cpi_rent_yoy', 'الإيجار')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم البنزين والكهرباء" subtitle="% سنويًا" lines={[us('us.cpi_gasoline_yoy', 'البنزين'), us('us.cpi_electricity_yoy', 'الكهرباء')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم السيارات" subtitle="% سنويًا" lines={[us('us.cpi_new_vehicles_yoy', 'جديدة'), us('us.cpi_used_vehicles_yoy', 'مستعملة'), us('us.cpi_transport_yoy', 'النقل عمومًا')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم الرعاية الطبية" subtitle="% سنويًا" lines={[us('us.cpi_medical_yoy', 'الرعاية الطبية')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="أسعار المنتجين والواردات" subtitle="مؤشرات" lines={[us('us.ppi_final_demand', 'منتجون (طلب نهائي)'), us('us.import_prices', 'الواردات')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="25y" title="مؤشر أسعار المنتجين لكل السلع" lines={[us('us.ppi_all_commodities', 'كل السلع')]} />
      </Section>

      <Section title="سوق العمل والأجور" lead="البطالة والوظائف والأجور والوظائف الشاغرة: أساس قرار الفدرالي.">
        <SeriesChart wide height={H} ranges={MONTHLY} defaultRange="25y" title="معدل البطالة: الرسمي والموسّع" subtitle="% — شهري" lines={[us('us.unemployment_rate', 'الرسمي U-3'), us('us.u6_rate', 'الموسّع U-6')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="25y" title="المشاركة ونسبة التوظيف إلى السكان" subtitle="%" lines={[us('us.participation_rate', 'المشاركة'), us('us.employment_population', 'التوظيف/السكان')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="الوظائف غير الزراعية" subtitle="ألف وظيفة" lines={[us('us.nonfarm_payrolls', 'الإجمالي')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="25y" title="وظائف التصنيع والحكومة" subtitle="ألف وظيفة" lines={[us('us.manufacturing_jobs', 'التصنيع'), us('us.government_jobs', 'الحكومة')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="الوظائف الشاغرة (JOLTS)" subtitle="ألف وظيفة" lines={[us('us.job_openings', 'وظائف شاغرة')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="متوسط الأجر بالساعة" subtitle="دولار — القطاع الخاص" lines={[us('us.avg_hourly_earnings', 'الأجر بالساعة')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="25y" title="متوسط ساعات العمل الأسبوعية" subtitle="ساعة" lines={[us('us.avg_weekly_hours', 'ساعات الأسبوع')]} />
      </Section>

      <Section title="الدين العام والموازنة" lead="الدين الأمريكي بالدولار منذ 1993 ومن يملكه، وإنفاق الحكومة الفدرالية وإيراداتها وعجز الموازنة وكلفة فوائد الدين شهريًا.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="all" title="إجمالي الدين العام الأمريكي" subtitle="دولار — يومي منذ 1993" lines={[us('us.public_debt', 'الدين العام')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="all" title="من يملك الدين؟" subtitle="دولار — الجمهور مقابل الجهات الحكومية" lines={[us('us.debt_held_public', 'مملوك للجمهور'), us('us.debt_intragov', 'بين الجهات الحكومية')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="كلفة فوائد الدين العام شهريًا" subtitle="دولار" kind="columns" last={120} lines={[us('us.interest_expense_public', 'مصروف الفوائد')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="الإنفاق مقابل الإيرادات الفدرالية" subtitle="دولار — شهري منذ 2015" lines={[us('us.outlays_total', 'الإنفاق'), us('us.receipts_total', 'الإيرادات')]} />
        <SeriesChart height={H} title="فائض/عجز الموازنة الشهري" subtitle="دولار — السالب عجز" kind="columns" last={96} lines={[us('us.budget_balance', 'الفائض/العجز')]} />
        <SeriesChart wide height={H} title="مصادر إيرادات الحكومة الفدرالية (شهريًا)" subtitle="دولار — آخر 48 شهرًا" kind="stacked" last={48} lines={[us('us.receipts_individual', 'ضرائب الأفراد', 'var(--series-1)'), us('us.receipts_social_insurance', 'التأمينات الاجتماعية', 'var(--series-3)'), us('us.receipts_corporate', 'ضرائب الشركات', 'var(--series-2)'), us('us.receipts_excise', 'ضرائب انتقائية', 'var(--series-4)'), us('us.receipts_customs', 'رسوم جمركية', 'var(--series-5)')]} />
        <SeriesChart height={H} title="الرسوم الجمركية الأمريكية شهريًا" subtitle="دولار" kind="columns" last={72} lines={[us('us.receipts_customs', 'الرسوم الجمركية')]} />
      </Section>
    </>
  );
}
