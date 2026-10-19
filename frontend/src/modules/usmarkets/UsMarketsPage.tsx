import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart, type RangeOption } from '@/core/components/SeriesChart';
import { SwitchChart } from '@/core/components/SwitchChart';

const us = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'USA', label, color });

/** نطاقات الرسوم اليومية: السنة الأخيرة ثم 5 و10 و25 سنة والتاريخ كله. */
const DAILY: readonly RangeOption[] = [
  { value: '1y', label: '1y', months: 12 },
  { value: '5y', label: '5y', months: 60 },
  { value: '10y', label: '10y', months: 120 },
  { value: '25y', label: '25y', months: 300 },
  { value: 'all', label: 'الكل', months: 0 },
];
/** الرسوم الشهرية تبدأ بعشر سنوات. */
const MONTHLY: readonly RangeOption[] = [
  { value: '3y', label: '3y', months: 36 },
  { value: '10y', label: '10y', months: 120 },
  { value: '25y', label: '25y', months: 300 },
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

      <Section title="سوق الأسهم" lead="مؤشرات الأسهم الأمريكية بمداها الكامل في رسم واحد: بدّل بين المؤشرات من الأزرار، واختر المدى الزمني. لاحظ مراحل الصعود والانهيار (2000 و2008 و2020).">
        <SwitchChart wide height={H} ranges={DAILY} defaultRange="10y" title="مؤشرات الأسهم الأمريكية" variants={[
          { value: 'spx', label: 'S&P 500', subtitle: 'الشركات الـ500 الأكبر — إغلاق يومي', lines: [us('us.sp500', 'S&P 500')] },
          { value: 'djia', label: 'داو جونز', subtitle: '30 شركة كبرى — إغلاق يومي', lines: [us('us.djia', 'داو جونز')] },
          { value: 'oex', label: 'S&P 100', subtitle: 'أكبر 100 شركة', lines: [us('us.sp100', 'S&P 100')] },
          { value: 'rut', label: 'راسل 2000', subtitle: 'الشركات الصغيرة', lines: [us('us.russell2000', 'راسل 2000')] },
          { value: 'bxm', label: 'استراتيجيات الخيارات', subtitle: 'البيع المغطى BXM وبيع الأوتاد PUT', lines: [us('us.bxm', 'البيع المغطى BXM'), us('us.putwrite', 'بيع الأوتاد PUT')] },
        ]} />
      </Section>

      <Section title="التقلب والمخاطر" lead="مقاييس الخوف المستخلصة من أسعار الخيارات: ارتفاع VIX فوق 30 يعني توترًا شديدًا، وفوق 50 ذعرًا نادرًا.">
        <SwitchChart wide height={H} ranges={DAILY} defaultRange="5y" title="مؤشرات التقلب والمخاطر" variants={[
          { value: 'vix', label: 'VIX', subtitle: 'إغلاق وأعلى وأدنى اليوم', lines: [us('us.vix', 'إغلاق'), us('us.vix_high', 'أعلى'), us('us.vix_low', 'أدنى')] },
          { value: 'term', label: 'هيكل التقلب', subtitle: '9 أيام إلى 6 أشهر — ارتفاع القصير فوق الطويل إنذار', lines: [us('us.vix9d', '9 أيام'), us('us.vix', '30 يومًا (VIX)'), us('us.vix3m', '3 أشهر'), us('us.vix6m', '6 أشهر')] },
          { value: 'vvix', label: 'VVIX وSKEW', subtitle: 'تقلب التقلب ومخاطر الذيل', lines: [us('us.vvix', 'VVIX'), us('us.skew', 'SKEW')] },
          { value: 'asset', label: 'حسب الأصل', subtitle: 'ناسداك وراسل والنفط والذهب', lines: [us('us.vxn', 'ناسداك 100'), us('us.rvx', 'راسل 2000'), us('us.ovx', 'النفط'), us('us.gvz', 'الذهب')] },
          { value: 'em', label: 'ناشئة وسندات', subtitle: 'الأسواق الناشئة وسندات الخزانة الطويلة', lines: [us('us.vxeem', 'الأسواق الناشئة'), us('us.vxtlt', 'سندات الخزانة الطويلة')] },
          { value: 'cor', label: 'الارتباط', subtitle: 'الارتباط الضمني بين الأسهم (3 أشهر)', lines: [us('us.cor3m', 'الارتباط الضمني')] },
        ]} />
      </Section>

      <Section title="سعر الفائدة والسندات" lead="الريال العُماني مربوط بالدولار، فتنتقل إليه الفائدة الأمريكية وتحدد كلفة دين عُمان وتمويلها.">
        <SwitchChart wide height={H} ranges={DAILY} defaultRange="10y" title="عوائد الخزانة الأمريكية" subtitle="%" variants={[
          { value: 'main', label: 'القصيرة والطويلة', subtitle: '% — يومي منذ 1990', lines: [us('us.yield_3m', '3 أشهر'), us('us.yield_2y', 'سنتان'), us('us.yield_10y', '10 سنوات'), us('us.yield_30y', '30 سنة')] },
          { value: 'rest', label: 'باقي المنحنى', subtitle: '% — شهر وسنة و3 و5 و7 و20 سنة', lines: [us('us.yield_1m', 'شهر'), us('us.yield_1y', 'سنة'), us('us.yield_3y', '3 سنوات'), us('us.yield_5y', '5 سنوات'), us('us.yield_7y', '7 سنوات'), us('us.yield_20y', '20 سنة')] },
          { value: 'spread', label: 'انعكاس المنحنى', subtitle: 'نقطة مئوية — تحت الصفر = انعكاس، وغالبًا يسبق الركود', lines: [us('us.spread_10y_2y', '10 سنوات − سنتان'), us('us.spread_10y_3m', '10 سنوات − 3 أشهر')] },
          { value: 'real', label: 'اسمي وحقيقي', subtitle: '% — الحقيقي من سندات TIPS منذ 2003', lines: [us('us.yield_10y', 'اسمي'), us('us.real_yield_10y', 'حقيقي')] },
          { value: 'be', label: 'التضخم المتوقع', subtitle: '% — الفرق بين العائد الاسمي والحقيقي', lines: [us('us.breakeven_5y', '5 سنوات'), us('us.breakeven_10y', '10 سنوات')] },
        ]} />
        <SwitchChart wide height={H} ranges={DAILY} defaultRange="25y" title="الفائدة وأسواق التمويل" variants={[
          { value: 'fed', label: 'الفدرالية', subtitle: '% — الفائدة الفعلية والمدى المستهدف', lines: [us('us.effr', 'الفائدة الفعلية (EFFR)'), us('us.fed_target_upper', 'الحد الأعلى للمدى'), us('us.fed_target_lower', 'الحد الأدنى للمدى')] },
          { value: 'repo', label: 'الريبو', subtitle: '% — منذ 2018', lines: [us('us.sofr', 'SOFR'), us('us.tgcr', 'الريبو الثلاثي TGCR'), us('us.bgcr', 'الريبو الواسع BGCR'), us('us.obfr', 'البنكي OBFR')] },
          { value: 'vol', label: 'حجم التداول', subtitle: 'مليار دولار يوميًا', lines: [us('us.sofr_volume', 'SOFR'), us('us.effr_volume', 'الأموال الفدرالية')] },
        ]} />
        <SeriesChart wide height={H} ranges={MONTHLY} defaultRange="10y" title="متوسط الفائدة على الدين الأمريكي القابل للتداول" subtitle="% — شهري" lines={[us('us.avg_rate_bills', 'أذونات'), us('us.avg_rate_notes', 'سندات متوسطة'), us('us.avg_rate_bonds', 'سندات طويلة'), us('us.avg_rate_marketable', 'الإجمالي')]} />
      </Section>

      <Section title="التضخم" lead="من أهم ما يحدد قرار الاحتياطي الفدرالي بشأن الفائدة: التضخم العام والأساسي وبنوده الكبرى.">
        <SwitchChart wide height={H} ranges={MONTHLY} defaultRange="25y" title="التضخم" variants={[
          { value: 'cpi', label: 'العام والأساسي', subtitle: '% سنويًا — شهري', lines: [us('us.cpi_yoy', 'العام'), us('us.core_cpi_yoy', 'الأساسي (دون الغذاء والطاقة)')] },
          { value: 'fe', label: 'غذاء وطاقة', subtitle: '% سنويًا', lines: [us('us.cpi_food_yoy', 'الغذاء'), us('us.cpi_energy_yoy', 'الطاقة')] },
          { value: 'housing', label: 'سكن وإيجار', subtitle: '% سنويًا', lines: [us('us.cpi_shelter_yoy', 'السكن'), us('us.cpi_rent_yoy', 'الإيجار')] },
          { value: 'fuel', label: 'بنزين وكهرباء', subtitle: '% سنويًا', lines: [us('us.cpi_gasoline_yoy', 'البنزين'), us('us.cpi_electricity_yoy', 'الكهرباء')] },
          { value: 'cars', label: 'سيارات ونقل', subtitle: '% سنويًا', lines: [us('us.cpi_new_vehicles_yoy', 'جديدة'), us('us.cpi_used_vehicles_yoy', 'مستعملة'), us('us.cpi_transport_yoy', 'النقل عمومًا')] },
          { value: 'med', label: 'رعاية طبية', subtitle: '% سنويًا', lines: [us('us.cpi_medical_yoy', 'الرعاية الطبية')] },
          { value: 'ppi', label: 'منتجون وواردات', subtitle: 'مؤشرات', lines: [us('us.ppi_final_demand', 'منتجون (طلب نهائي)'), us('us.import_prices', 'الواردات'), us('us.ppi_all_commodities', 'كل السلع')] },
        ]} />
      </Section>

      <Section title="سوق العمل والأجور" lead="البطالة والوظائف والأجور والوظائف الشاغرة: أساس قرار الفدرالي.">
        <SwitchChart wide height={H} ranges={MONTHLY} defaultRange="25y" title="سوق العمل" variants={[
          { value: 'unemp', label: 'البطالة', subtitle: '% — الرسمي والموسّع', lines: [us('us.unemployment_rate', 'الرسمي U-3'), us('us.u6_rate', 'الموسّع U-6')] },
          { value: 'part', label: 'المشاركة', subtitle: '%', lines: [us('us.participation_rate', 'المشاركة'), us('us.employment_population', 'التوظيف/السكان')] },
          { value: 'jobs', label: 'الوظائف', subtitle: 'ألف وظيفة — غير الزراعية', lines: [us('us.nonfarm_payrolls', 'الإجمالي')] },
          { value: 'sector', label: 'تصنيع وحكومة', subtitle: 'ألف وظيفة', lines: [us('us.manufacturing_jobs', 'التصنيع'), us('us.government_jobs', 'الحكومة')] },
          { value: 'openings', label: 'الشاغرة', subtitle: 'ألف وظيفة (JOLTS)', lines: [us('us.job_openings', 'وظائف شاغرة')] },
          { value: 'wage', label: 'الأجور', subtitle: 'دولار بالساعة — القطاع الخاص', lines: [us('us.avg_hourly_earnings', 'الأجر بالساعة')] },
          { value: 'hours', label: 'ساعات العمل', subtitle: 'ساعة أسبوعيًا', lines: [us('us.avg_weekly_hours', 'ساعات الأسبوع')] },
        ]} />
      </Section>

      <Section title="الدين العام والموازنة" lead="الدين الأمريكي بالدولار منذ 1993 ومن يملكه، وإنفاق الحكومة الفدرالية وإيراداتها وعجز الموازنة وكلفة فوائد الدين شهريًا.">
        <SwitchChart wide height={H} ranges={DAILY} defaultRange="all" title="الدين العام" variants={[
          { value: 'total', label: 'الإجمالي', subtitle: 'دولار — يومي منذ 1993', lines: [us('us.public_debt', 'الدين العام')] },
          { value: 'holders', label: 'من يملكه', subtitle: 'دولار — الجمهور مقابل الجهات الحكومية', lines: [us('us.debt_held_public', 'مملوك للجمهور'), us('us.debt_intragov', 'بين الجهات الحكومية')] },
        ]} />
        <SwitchChart wide height={H} ranges={MONTHLY} defaultRange="all" title="الموازنة الفدرالية" last={400} variants={[
          { value: 'flows', label: 'إنفاق وإيراد', subtitle: 'دولار — شهري منذ 2015', lines: [us('us.outlays_total', 'الإنفاق'), us('us.receipts_total', 'الإيرادات')] },
          { value: 'balance', label: 'الفائض/العجز', subtitle: 'دولار — السالب عجز', lines: [us('us.budget_balance', 'الفائض/العجز')] },
          { value: 'interest', label: 'فوائد الدين', subtitle: 'دولار شهريًا', lines: [us('us.interest_expense_public', 'مصروف الفوائد')] },
          { value: 'customs', label: 'الرسوم الجمركية', subtitle: 'دولار شهريًا', lines: [us('us.receipts_customs', 'الرسوم الجمركية')] },
        ]} />
        <SwitchChart wide height={H} ranges={MONTHLY} defaultRange="10y" title="مصادر إيرادات الحكومة الفدرالية" last={400} variants={[
          { value: 'cmp', label: 'مقارنة المصادر', subtitle: 'دولار — شهري', lines: [us('us.receipts_individual', 'ضرائب الأفراد'), us('us.receipts_social_insurance', 'التأمينات الاجتماعية'), us('us.receipts_corporate', 'ضرائب الشركات'), us('us.receipts_excise', 'ضرائب انتقائية')] },
          { value: 'ind', label: 'ضرائب الأفراد', kind: 'columns', subtitle: 'دولار شهريًا', lines: [us('us.receipts_individual', 'ضرائب الأفراد')] },
          { value: 'soc', label: 'التأمينات', kind: 'columns', subtitle: 'دولار شهريًا', lines: [us('us.receipts_social_insurance', 'التأمينات الاجتماعية والتقاعد')] },
          { value: 'corp', label: 'ضرائب الشركات', kind: 'columns', subtitle: 'دولار شهريًا', lines: [us('us.receipts_corporate', 'ضرائب الشركات')] },
          { value: 'excise', label: 'انتقائية', kind: 'columns', subtitle: 'دولار شهريًا', lines: [us('us.receipts_excise', 'الضرائب الانتقائية')] },
          { value: 'tot', label: 'الإجمالي', kind: 'columns', subtitle: 'دولار شهريًا', lines: [us('us.receipts_total', 'إجمالي الإيرادات')] },
        ]} />
      </Section>
    </>
  );
}
