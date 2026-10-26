import { ReadingGrid } from '@/core/components/ReadingGrid';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SwitchChart } from '@/core/components/SwitchChart';
import { NationalSelfSufficiency } from './NationalSelfSufficiency';
import { FoodPricesTable } from './FoodPricesTable';
import { WaterForCrops } from './WaterForCrops';
import { gccLines, peerLines } from '@/core/groups';

const om = (code: string, label: string, color?: string) => ({ indicator: `ncsi.${code}`, entity: 'OMN', label, color });
const H = 340;
/** البيانات المفتوحة لوزارة الثروة الزراعية والسمكية وموارد المياه */
const mw = (key: string, label: string, color?: string) => ({ indicator: `mafwr.${key}`, entity: 'OMN', label, color });
const wb = (code: string, label: string, color?: string) => ({ indicator: `wb.${code}`, entity: 'OMN', label, color });
const sdg = (key: string, label: string, color?: string) => ({ indicator: `sdg.water_eff.${key}`, entity: 'OMN', label, color });

/** أسعار الأسماك (وزارة الثروة الزراعية): نوع ومقياس، لعُمان (الوسيط الوطني) أو لمحافظة */
const fish = (slug: string, label: string, kind: 'retail' | 'wholesale' = 'retail', entity = 'OMN', color?: string) => ({ indicator: `mafwr.fish.${slug}.${kind}`, entity, label, color });
/** المحافظات الأكثر تغطية بأسواق مُبلِغة */
const GOVS: [string, string][] = [['OM-BS', 'شمال الباطنة'], ['OM-BJ', 'جنوب الباطنة'], ['OM-MA', 'مسقط'], ['OM-ZU', 'ظفار']];
const byGov = (slug: string) => GOVS.map(([code, name]) => fish(slug, name, 'retail', code));
const ncsiGdp = (code: string, label: string, color?: string) => ({ indicator: `ncsi.${code}`, entity: 'OMN', label, color });

const TOC: [string, string][] = [
  ['gdp', 'مساهمة القطاع في الناتج'], ['crops', 'المحاصيل'], ['livestock', 'الثروة الحيوانية'], ['fish', 'الثروة السمكية'], ['prices', 'الأسعار'], ['consumption', 'الأمن الغذائي'],
  ['water', 'المياه'], ['water-crops', 'المياه والمحاصيل'], ['dams', 'السدود والأفلاج'], ['related', 'قطاعات مرتبطة'],
];
/** الموازين الغذائية (FAO): سلعة ومقياس لعُمان */
const fbs = (item: string, metric: string, label: string, color?: string) => ({ indicator: `fao.fbs.${item}.${metric}`, entity: 'OMN', label, color });
/** إنتاج السلعة مقابل استهلاكها ووارداتها وصادراتها */
const balance = (item: string) => [
  fbs(item, 'prod', 'الإنتاج المحلي', 'var(--series-3)'),
  fbs(item, 'supply', 'المتاح للاستهلاك', 'var(--series-1)'),
  fbs(item, 'imp', 'الواردات', 'var(--series-2)'),
  fbs(item, 'exp', 'الصادرات', 'var(--series-4)'),
];
/** سلع لها لعُمان إنتاج مُبلغ أو مقدَّر (لا مُسنَد) في الموازين الغذائية، فيصح فيها «الإنتاج مقابل الاستهلاك» */
const COMMODITIES: [string, string][] = [
  ['vegetables', 'الخضروات'], ['fruits', 'الفواكه'], ['cereals', 'الحبوب'], ['rice', 'الأرز'],
  ['milk', 'الألبان'], ['eggs', 'البيض'], ['fish', 'الأسماك'], ['sugar', 'السكر'], ['oils', 'الزيوت النباتية'],
];
/** سلع لا تُبلغ عُمان المنظمة بإنتاجها: تُعرض تجارتها فقط */
const TRADE_ONLY: [string, string][] = [['wheat', 'القمح'], ['poultry', 'الدواجن'], ['mutton', 'الضأن والماعز'], ['beef', 'الأبقار'], ['dates', 'التمور']];

export default function AgriculturePage() {
  return (
    <>
      <PageHead
        title="الزراعة والثروة السمكية والمياه"
        lead="ماذا تُنتج عُمان من المحاصيل والماشية والأسماك، وكم تستهلك منها، وكم ماءً يكلّف ذلك. من البيانات المفتوحة لوزارة الثروة الزراعية والسمكية وموارد المياه والمركز الوطني للإحصاء، مع الموازين الغذائية لمنظمة الأغذية والزراعة، وبيانات المياه من AQUASTAT وأهداف التنمية المستدامة."
      />

      <nav className="brief-toc" aria-label="أقسام الصفحة">
        {TOC.map(([id, label]) => <a key={id} href={`#${id}`} className="chip neutral">{label}</a>)}
      </nav>

      <ReadingGrid>
        <KeyStat entity="OMN" indicator="mafwr.crop.total.prod" label="الإنتاج الزراعي" goodWhen="up" />
        <KeyStat entity="OMN" indicator="mafwr.livestock.total" label="أعداد الماشية" />
        <KeyStat entity="OMN" indicator="ncsi.FISH_AQUACULTURE" label="الاستزراع السمكي" goodWhen="up" />
        <KeyStat entity="OMN" indicator="fao.fbs.vegetables.ssr" label="الاكتفاء الذاتي من الخضروات" goodWhen="up" />
        <KeyStat entity="OMN" indicator="wb.ER.H2O.FWTL.ZS" label="سحب المياه إلى الموارد المتجددة" goodWhen="down" />
        <KeyStat entity="OMN" indicator="sdg.water_eff.agriculture" label="قيمة المتر المكعب في الزراعة" goodWhen="up" />
      </ReadingGrid>

      <Section id="gdp" title="مساهمة القطاع في الناتج المحلي الإجمالي" lead="كم يشكّل قطاع الزراعة والصيد من اقتصاد عُمان، وكيف تغيّرت حصته منذ 2010؟ وهل تتناسب مساهمته في الناتج مع عدد العاملين فيه؟ (الحسابات القومية — المركز الوطني للإحصاء، وآخر سنة منشورة 2021).">
        <ReadingGrid>
          <KeyStat entity="OMN" indicator="ncsi.GDP_SHARE_AGRI_FISH" label="مساهمة الزراعة والصيد في الناتج" />
          <KeyStat entity="OMN" indicator="ncsi.GDP_SHARE_AGRI" label="مساهمة الزراعة والغابات" />
          <KeyStat entity="OMN" indicator="ncsi.GDP_SHARE_FISH" label="مساهمة الصيد" />
          <KeyStat entity="OMN" indicator="ncsi.VA_AGRI" label="القيمة المضافة: الزراعة والغابات" />
          <KeyStat entity="OMN" indicator="ncsi.VA_FISH" label="القيمة المضافة: الصيد" />
          <KeyStat entity="OMN" indicator="wb.SL.AGR.EMPL.ZS" label="حصة الزراعة من العاملين" />
        </ReadingGrid>
        <SwitchChart wide height={H} title="مساهمة القطاع في الناتج المحلي" variants={[
          { value: 'current', label: 'الحصة بالأسعار الجارية', subtitle: '% من الناتج المحلي الإجمالي — المركز الوطني للإحصاء', kind: 'columns', lines: [ncsiGdp('GDP_SHARE_AGRI', 'الزراعة والغابات', 'var(--series-3)'), ncsiGdp('GDP_SHARE_FISH', 'الصيد', 'var(--series-1)')] },
          { value: 'total', label: 'الحصة الإجمالية', subtitle: '% من الناتج — الزراعة والغابات والصيد معًا', lines: [ncsiGdp('GDP_SHARE_AGRI_FISH', 'بالأسعار الجارية', 'var(--series-3)'), ncsiGdp('GDP_SHARE_AGRI_FISH_CONST', 'بالأسعار الثابتة', 'var(--series-1)')] },
          { value: 'constant', label: 'الحصة بالأسعار الثابتة', subtitle: '% من الناتج الحقيقي: حجم النشاط بعد استبعاد الأسعار', kind: 'columns', lines: [ncsiGdp('GDP_SHARE_AGRI_CONST', 'الزراعة والغابات', 'var(--series-3)'), ncsiGdp('GDP_SHARE_FISH_CONST', 'الصيد', 'var(--series-1)')] },
          { value: 'va', label: 'القيمة المضافة بالريال', subtitle: 'مليون ر.ع بالأسعار الجارية (منشورة منذ 2018)', kind: 'columns', lines: [ncsiGdp('VA_AGRI', 'الزراعة والغابات', 'var(--series-3)'), ncsiGdp('VA_FISH', 'الصيد', 'var(--series-1)')] },
          { value: 'va-const', label: 'القيمة المضافة الحقيقية', subtitle: 'مليون ر.ع بالأسعار الثابتة', kind: 'columns', lines: [ncsiGdp('VA_AGRI_CONST', 'الزراعة والغابات', 'var(--series-3)'), ncsiGdp('VA_FISH_CONST', 'الصيد', 'var(--series-1)')] },
          { value: 'employment', label: 'الناتج مقابل العاملين', subtitle: '% — حصة الزراعة من الناتج مقابل حصتها من العاملين (البنك الدولي)', from: '1991', lines: [wb('NV.AGR.TOTL.ZS', 'حصتها من الناتج', 'var(--series-3)'), wb('SL.AGR.EMPL.ZS', 'حصتها من العاملين', 'var(--series-2)')] },
          { value: 'gcc', label: 'مقارنة بالخليج', subtitle: 'القيمة المضافة للزراعة، % من الناتج — البنك الدولي', from: '1995', lines: gccLines('wb.NV.AGR.TOTL.ZS') },
        ]} />
      </Section>

      <Section id="crops" title="المحاصيل" lead="الإنتاج والمساحة حسب المجموعة والمحصول من بيانات الوزارة (منذ 2016)، ونخيل التمور، ومقارنة بالخليج.">
        <SwitchChart wide height={H} title="إنتاج المحاصيل" variants={[
          { value: 'groups', label: 'حسب المجموعة', subtitle: 'طن — وزارة الثروة الزراعية', lines: [mw('crop.fodder.prod', 'الأعلاف', 'var(--series-4)'), mw('crop.veg.prod', 'الخضروات', 'var(--series-3)'), mw('crop.fruit.prod', 'الفاكهة', 'var(--series-2)'), mw('crop.field.prod', 'المحاصيل الحقلية', 'var(--series-1)')] },
          { value: 'total', label: 'الإجمالي', subtitle: 'طن — وزارة الثروة الزراعية', lines: [mw('crop.total.prod', 'إجمالي الإنتاج')] },
          { value: 'main', label: 'المحاصيل الأكبر', subtitle: 'طن', lines: [mw('crop.rhodes.prod', 'حشيشة الرودس'), mw('crop.dates.prod', 'نخيل التمور'), mw('crop.alfalfa.prod', 'البرسيم'), mw('crop.tomato.prod', 'الطماطم')] },
          { value: 'veg', label: 'الخضروات', subtitle: 'طن', lines: [mw('crop.tomato.prod', 'الطماطم'), mw('crop.pepper.prod', 'الفلفل'), mw('crop.cucumber.prod', 'الخيار'), mw('crop.watermelon.prod', 'البطيخ')] },
          { value: 'fruit', label: 'الفاكهة', subtitle: 'طن', lines: [mw('crop.banana.prod', 'الموز'), mw('crop.mango.prod', 'المانجو'), mw('crop.lime.prod', 'الليمون')] },
          { value: 'grain', label: 'القمح والأعلاف الحبية', subtitle: 'طن', lines: [mw('crop.wheat.prod', 'القمح'), mw('crop.barley_fodder.prod', 'الشعير (علف)'), mw('crop.maize_fodder.prod', 'الذرة الشامية (علف)'), mw('crop.sorghum_fodder.prod', 'المسيبلو (علف)')] },
          { value: 'ncsi', label: 'المركز الوطني للإحصاء', subtitle: 'طن — سلسلة المركز', lines: [om('AGR_CROP_VEG', 'خضروات', 'var(--series-3)'), om('AGR_CROP_FRUIT', 'فواكه', 'var(--series-2)'), om('AGR_CROP_FIELD', 'محاصيل حقلية', 'var(--series-1)'), om('AGR_CROP_FODDER', 'علفية معمرة', 'var(--series-4)')] },
        ]} />
        <SwitchChart wide height={H} title="المساحة والإنتاجية" variants={[
          { value: 'groups', label: 'المساحة حسب المجموعة', subtitle: 'فدان — وزارة الثروة الزراعية', lines: [mw('crop.fodder.area', 'الأعلاف', 'var(--series-4)'), mw('crop.veg.area', 'الخضروات', 'var(--series-3)'), mw('crop.fruit.area', 'الفاكهة', 'var(--series-2)'), mw('crop.field.area', 'المحاصيل الحقلية', 'var(--series-1)')] },
          { value: 'total', label: 'إجمالي المساحة', subtitle: 'فدان', lines: [mw('crop.total.area', 'المساحة المحصولية'), om('AGR_CULTIVATED_AREA', 'المساحة المزروعة (المركز الوطني)')] },
          { value: 'yield', label: 'إنتاجية الفدان', subtitle: 'طن لكل فدان', lines: [mw('crop.cucumber.yield', 'الخيار'), mw('crop.pepper.yield', 'الفلفل'), mw('crop.tomato.yield', 'الطماطم'), mw('crop.rhodes.yield', 'حشيشة الرودس')] },
          { value: 'land', label: 'الأراضي الزراعية (% من اليابسة)', subtitle: 'مع دول الخليج — البنك الدولي', from: '1995', lines: gccLines('wb.AG.LND.AGRI.ZS') },
          { value: 'irrigated', label: 'الأراضي المروية', subtitle: '% من الأرض الزراعية — البنك الدولي', from: '1995', lines: gccLines('wb.AG.LND.IRIG.AG.ZS') },
        ]} />
        <SwitchChart wide height={H} title="نخيل التمور" variants={[
          { value: 'prod', label: 'إنتاج التمور', subtitle: 'طن — مسح الوزارة حسب الولايات', lines: [mw('dates.prod', 'إنتاج التمور')] },
          { value: 'palms', label: 'النخيل المنتجة', subtitle: 'نخلة', lines: [mw('dates.palms', 'عدد النخيل المنتجة')] },
          { value: 'yield', label: 'متوسط إنتاج النخلة', subtitle: 'كغم للنخلة', lines: [mw('dates.yield', 'متوسط الإنتاج')] },
        ]} />
        <SwitchChart wide height={H} title="الإنتاج الغذائي مقارنةً بالخليج" variants={[
          { value: 'index', label: 'مؤشر إنتاج الغذاء', subtitle: '2014-2016 = 100 — البنك الدولي', from: '1995', lines: gccLines('wb.AG.PRD.FOOD.XD') },
          { value: 'cereals', label: 'إنتاج الحبوب', subtitle: 'طن — البنك الدولي', from: '1995', lines: gccLines('wb.AG.PRD.CREL.MT') },
        ]} />
      </Section>

      <Section id="livestock" title="الثروة الحيوانية" lead="أعداد الماشية حسب النوع منذ 2005، وما تنتجه من لحوم وحليب وبيض وعسل (وزارة الثروة الزراعية).">
        <SwitchChart wide height={H} title="الثروة الحيوانية" variants={[
          { value: 'types', label: 'أعداد الماشية حسب النوع', subtitle: 'رأس — وزارة الثروة الزراعية', lines: [mw('livestock.goats', 'الماعز'), mw('livestock.sheep', 'الضأن'), mw('livestock.cattle', 'الأبقار'), mw('livestock.camels', 'الإبل')] },
          { value: 'total', label: 'إجمالي الماشية', subtitle: 'رأس', lines: [mw('livestock.total', 'الإجمالي')] },
          { value: 'meat', label: 'اللحوم', subtitle: 'طن', lines: [mw('meat.poultry', 'لحوم الدواجن'), mw('meat.red', 'اللحوم الحمراء')] },
          { value: 'red', label: 'اللحوم الحمراء حسب النوع', subtitle: 'طن', lines: [mw('meat.beef', 'الأبقار'), mw('meat.goat', 'الماعز'), mw('meat.camel', 'الإبل'), mw('meat.mutton', 'الضأن')] },
          { value: 'milk', label: 'الحليب', subtitle: 'طن', lines: [mw('milk.total', 'الإجمالي'), mw('milk.cows', 'الأبقار'), mw('milk.camels', 'الإبل')] },
          { value: 'eggs', label: 'بيض المائدة', subtitle: 'طن', lines: [mw('eggs.prod', 'بيض المائدة')] },
          { value: 'honey', label: 'العسل', subtitle: 'طن', lines: [mw('honey.prod', 'إنتاج العسل')] },
        ]} />
      </Section>

      <Section id="fish" title="الثروة السمكية" lead="الأسماك المنزلة شهريًا حسب نوع الصيد والاستزراع (المركز الوطني للإحصاء)، ومقارنة بالخليج.">
        <SwitchChart wide height={H} title="الثروة السمكية" variants={[
          { value: 'types', label: 'التقليدي والتجاري', subtitle: 'طن شهريًا', lines: [om('FISH_ARTISANAL', 'صيد تقليدي'), om('FISH_COMMERCIAL', 'صيد تجاري')] },
          { value: 'total', label: 'إجمالي المنزل', subtitle: 'طن شهريًا', lines: [om('FISH_LANDED', 'الإجمالي')] },
          { value: 'aqua', label: 'الاستزراع السمكي', subtitle: 'طن', lines: [om('FISH_AQUACULTURE', 'استزراع')] },
          { value: 'gcc-capture', label: 'الصيد: مقارنة بالخليج', subtitle: 'طن سنويًا — البنك الدولي', from: '1990', lines: gccLines('wb.ER.FSH.CAPT.MT') },
          { value: 'gcc-total', label: 'الصيد والاستزراع: الخليج', subtitle: 'طن سنويًا — البنك الدولي', from: '1990', lines: gccLines('wb.ER.FSH.PROD.MT') },
        ]} />
      </Section>

      <Section id="prices" title="أسعار الأسماك والسلع الغذائية" lead="أسعار الأسماك في أسواق السلطنة يوميًا (وزارة الثروة الزراعية والسمكية وموارد المياه، من يوليو 2025): وسيط الأسعار بين الأسواق لكل نوع ومحافظة، وفرق الجملة عن التجزئة، ومؤشر سلة لقياس غلاء الأسماك عمومًا. ثم أسعار الأرز والسكر والعدس. أخطاء الإدخال في ملف الوزارة (كالأسعار السالبة أو المسجّلة بالبيسة) تُستبعد وتُوثَّق في سجل التصحيحات.">
        <ReadingGrid>
          <KeyStat entity="OMN" indicator="mafwr.fish.hamour.retail" label="سعر الهامور" goodWhen="down" />
          <KeyStat entity="OMN" indicator="mafwr.fish.kanad.retail" label="سعر الكنعد" goodWhen="down" />
          <KeyStat entity="OMN" indicator="mafwr.fish.shaari.retail" label="سعر الشعري" goodWhen="down" />
          <KeyStat entity="OMN" indicator="mafwr.fish.sahwa.retail" label="سعر السهوة" goodWhen="down" />
          <KeyStat entity="OMN" indicator="mafwr.fish.index" label="مؤشر أسعار الأسماك" goodWhen="down" />
          <KeyStat entity="OMN" indicator="mafwr.food.sugar.perkg" label="سعر كيلو السكر" goodWhen="down" />
        </ReadingGrid>
        <SwitchChart wide height={H} title="أسعار الأسماك بالتجزئة" variants={[
          { value: 'popular', label: 'الأنواع الشعبية', subtitle: 'ريال للكيلو — وسيط أسواق السلطنة اليومي', lines: [fish('sahwa', 'سهوة'), fish('shaari', 'شعري'), fish('jidhar', 'جيذر'), fish('saal', 'صال')] },
          { value: 'premium', label: 'الأنواع الفاخرة', subtitle: 'ريال للكيلو', lines: [fish('hamour', 'هامور'), fish('kanad', 'كنعد'), fish('kofar', 'كوفر'), fish('sultan_ibrahim', 'سلطان براهيم')] },
          { value: 'cheap', label: 'الأنواع الأرخص', subtitle: 'ريال للكيلو', lines: [fish('ooma', 'عومة'), fish('anfaloos', 'عنفلوص'), fish('qishran', 'قشران'), fish('saima', 'صيمة')] },
          { value: 'other', label: 'الحبار والروبيان وغيرها', subtitle: 'ريال للكيلو', lines: [fish('hubar', 'حبار'), fish('shrimp', 'روبيان'), fish('jarjoor', 'جرجور'), fish('dhilaa', 'ضلعة')] },
          { value: 'index', label: 'مؤشر السلة', subtitle: 'وسيط فترة الملف = 100: متوسط نسب أسعار الأنواع إلى وسيطها', lines: [{ indicator: 'mafwr.fish.index', entity: 'OMN', label: 'مؤشر أسعار الأسماك' }] },
          { value: 'markets', label: 'الأسواق المُبلِغة', subtitle: 'عدد الأسواق التي سُجّلت أسعارها يوميًا (يبيّن موثوقية الوسيط)', kind: 'columns', last: 90, lines: [{ indicator: 'mafwr.fish.markets', entity: 'OMN', label: 'أسواق مُبلِغة' }] },
        ]} />
        <SwitchChart wide height={H} title="أسعار الأسماك بين المحافظات" variants={[
          { value: 'hamour', label: 'الهامور', subtitle: 'ريال للكيلو — وسيط أسواق كل محافظة', lines: byGov('hamour') },
          { value: 'kanad', label: 'الكنعد', subtitle: 'ريال للكيلو', lines: byGov('kanad') },
          { value: 'shaari', label: 'الشعري', subtitle: 'ريال للكيلو', lines: byGov('shaari') },
          { value: 'sahwa', label: 'السهوة', subtitle: 'ريال للكيلو', lines: byGov('sahwa') },
          { value: 'jidhar', label: 'الجيذر', subtitle: 'ريال للكيلو', lines: byGov('jidhar') },
          { value: 'saal', label: 'الصال', subtitle: 'ريال للكيلو', lines: byGov('saal') },
          { value: 'kofar', label: 'الكوفر', subtitle: 'ريال للكيلو', lines: byGov('kofar') },
        ]} />
        <SwitchChart wide height={H} title="الجملة والتجزئة" variants={['jidhar:الجيذر', 'anfaloos:العنفلوص', 'saima:الصيمة', 'dhilaa:الضلعة', 'sahwa:السهوة', 'sansool:السنسول'].map((v) => {
          const [slug, name] = v.split(':');
          return { value: slug, label: name, subtitle: `ريال للكيلو — ${name}: الفرق بين الجملة والتجزئة هامش التاجر والنقل`, lines: [fish(slug, 'التجزئة', 'retail', 'OMN', 'var(--series-2)'), fish(slug, 'الجملة', 'wholesale', 'OMN', 'var(--series-3)')] };
        })} />
        <FoodPricesTable />
      </Section>

      <Section id="consumption" title="الأمن الغذائي: الاستهلاك والاكتفاء الذاتي" lead="الإنتاج وحده لا يكفي للحكم: كم يستهلك المجتمع من كل سلعة؟ وكم منه يُنتج محليًا وكم يُستورد؟ الموازين الغذائية لمنظمة الأغذية والزراعة (FAO): المتاح للاستهلاك = الإنتاج + الواردات − الصادرات ± المخزون، ونسبة الاكتفاء الذاتي = الإنتاج ÷ المتاح للاستهلاك. حين لا تُبلغ عُمان المنظمة بإنتاج سلعة (القمح واللحوم والتمور) تُسنده المنظمة تقديرًا، فلا نعرضه ولا ما بُني عليه، ونحسب اكتفاء اللحوم من الإنتاج الرسمي للمركز الوطني.">
        <ReadingGrid>
          <KeyStat entity="OMN" indicator="fao.fbs.vegetables.ssr" label="الاكتفاء الذاتي من الخضروات" goodWhen="up" />
          <KeyStat entity="OMN" indicator="fao.fbs.fruits.ssr" label="الاكتفاء الذاتي من الفواكه" goodWhen="up" />
          <KeyStat entity="OMN" indicator="fao.fbs.eggs.ssr" label="الاكتفاء الذاتي من البيض" goodWhen="up" />
          <KeyStat entity="OMN" indicator="fao.fbs.fish.ssr" label="الاكتفاء الذاتي من الأسماك" goodWhen="up" />
          <KeyStat entity="OMN" indicator="fao.fbs.milk.ssr" label="الاكتفاء الذاتي من الألبان" goodWhen="up" />
          <KeyStat entity="OMN" indicator="fao.fbs.cereals.ssr" label="الاكتفاء الذاتي من الحبوب" goodWhen="up" />
        </ReadingGrid>
        <SwitchChart wide height={H} title="الإنتاج مقابل الاستهلاك" variants={[
          ...COMMODITIES.map(([item, label]) => ({ value: item, label, subtitle: `ألف طن — ${label}: الإنتاج المحلي والمتاح للاستهلاك والواردات والصادرات (FAO)`, lines: balance(item) })),
          ...TRADE_ONLY.map(([item, label]) => ({ value: item, label: `${label} (تجارة)`, subtitle: `ألف طن — ${label}: الواردات والصادرات فقط؛ عُمان لا تُبلغ المنظمة بإنتاجه فتُسنده، فلا يُعرض`, lines: [fbs(item, 'imp', 'الواردات', 'var(--series-2)'), fbs(item, 'exp', 'الصادرات', 'var(--series-4)')] })),
        ]} />
        <SwitchChart wide height={H} title="نسبة الاكتفاء الذاتي" variants={[
          { value: 'produce', label: 'الخضروات والفواكه', subtitle: '% — الإنتاج ÷ المتاح للاستهلاك (100% = اكتفاء تام)', lines: [fbs('vegetables', 'ssr', 'الخضروات'), fbs('fruits', 'ssr', 'الفواكه')] },
          { value: 'protein', label: 'الأسماك والبيض والألبان', subtitle: '%', lines: [fbs('fish', 'ssr', 'الأسماك'), fbs('eggs', 'ssr', 'البيض'), fbs('milk', 'ssr', 'الألبان')] },
          { value: 'staples', label: 'الحبوب والسكر والزيوت', subtitle: '%', lines: [fbs('cereals', 'ssr', 'الحبوب'), fbs('sugar', 'ssr', 'السكر'), fbs('oils', 'ssr', 'الزيوت النباتية')] },
          { value: 'gcc', label: 'الخضروات: مقارنة بالخليج', subtitle: '% — الاكتفاء الذاتي من الخضروات', lines: gccLines('fao.fbs.vegetables.ssr') },
        ]} />
        <NationalSelfSufficiency />
        <SwitchChart wide height={H} title="استهلاك الفرد" variants={[
          { value: 'produce', label: 'الخضروات والفواكه', subtitle: 'كغم للفرد في السنة', lines: [fbs('vegetables', 'kgcap', 'الخضروات'), fbs('fruits', 'kgcap', 'الفواكه')] },
          { value: 'staples', label: 'الحبوب والأرز والسكر والزيوت', subtitle: 'كغم للفرد في السنة', lines: [fbs('cereals', 'kgcap', 'الحبوب'), fbs('rice', 'kgcap', 'الأرز'), fbs('sugar', 'kgcap', 'السكر'), fbs('oils', 'kgcap', 'الزيوت النباتية')] },
          { value: 'protein', label: 'الأسماك والبيض والألبان', subtitle: 'كغم للفرد في السنة', lines: [fbs('fish', 'kgcap', 'الأسماك'), fbs('eggs', 'kgcap', 'البيض'), fbs('milk', 'kgcap', 'الألبان')] },
          { value: 'kcal', label: 'السعرات اليومية', subtitle: 'سعرة للفرد يوميًا — مع دول الخليج (يشمل تقديرات المنظمة)', lines: gccLines('fao.fbs.kcal') },
          { value: 'nutrients', label: 'البروتين والدهون', subtitle: 'غرام للفرد يوميًا', lines: [{ indicator: 'fao.fbs.protein', entity: 'OMN', label: 'البروتين' }, { indicator: 'fao.fbs.fat', entity: 'OMN', label: 'الدهون' }] },
        ]} />
      </Section>

      <Section id="water" title="المياه: الموارد والسحب والقيمة الاقتصادية" lead="كم ماءً تسحب عُمان مقارنةً بما يتجدد طبيعيًا؟ ومن يستهلكه؟ وكم يُنتج المتر المكعب في كل قطاع؟ السحب فوق 100% من الموارد المتجددة يعني استنزاف المخزون الجوفي.">
        <SwitchChart wide height={H} title="الموارد المائية والسحب" variants={[
          { value: 'balance', label: 'السحب مقابل الموارد المتجددة', subtitle: 'مليار م³ سنويًا — AQUASTAT عبر البنك الدولي', from: '1990', lines: [wb('ER.H2O.FWTL.K3', 'إجمالي السحب', 'var(--series-2)'), wb('ER.H2O.INTR.K3', 'الموارد الداخلية المتجددة', 'var(--series-3)')] },
          { value: 'ratio', label: 'السحب إلى الموارد المتجددة', subtitle: '% — فوق 100% استنزاف للمخزون الجوفي', from: '1990', lines: gccLines('wb.ER.H2O.FWTL.ZS') },
          { value: 'sectors', label: 'السحب حسب القطاع', subtitle: '% من إجمالي السحب', from: '1990', lines: [wb('ER.H2O.FWAG.ZS', 'الزراعة'), wb('ER.H2O.FWDM.ZS', 'المنزلي'), wb('ER.H2O.FWIN.ZS', 'الصناعة')] },
          { value: 'stress', label: 'الإجهاد المائي', subtitle: '% — مع دول المقارنة', from: '2000', lines: peerLines('wb.ER.H2O.FWST.ZS') },
          { value: 'percap', label: 'الموارد المتجددة للفرد', subtitle: 'م³ للفرد — مع الخليج', from: '1990', lines: gccLines('wb.ER.H2O.INTR.PC') },
        ]} />
        <SwitchChart wide height={H} title="القيمة الاقتصادية للمياه" variants={[
          { value: 'sectors', label: 'حسب القطاع', subtitle: 'دولار من القيمة المضافة لكل م³ — هدف التنمية 6.4.1 (الأمم المتحدة)', lines: [sdg('industry', 'الصناعة'), sdg('services', 'الخدمات'), sdg('total', 'كل القطاعات'), sdg('agriculture', 'الزراعة')] },
          { value: 'agri', label: 'الزراعة', subtitle: 'دولار لكل م³ — قيمة المتر المكعب في الزراعة', lines: [sdg('agriculture', 'الزراعة')] },
          { value: 'agri-gcc', label: 'الزراعة: مقارنة بالخليج', subtitle: 'دولار لكل م³', lines: gccLines('sdg.water_eff.agriculture') },
          { value: 'productivity', label: 'إنتاجية المياه الكلية', subtitle: 'دولار (أسعار 2015) من الناتج لكل م³ — البنك الدولي', from: '1990', lines: gccLines('wb.ER.GDP.FWTL.M3.KD') },
        ]} />
        <SwitchChart wide height={H} title="مياه الشرب: الإنتاج والاستهلاك" variants={[
          { value: 'prod', label: 'الإنتاج الشهري', subtitle: 'متر مكعب — المركز الوطني للإحصاء', kind: 'columns', last: 60, lines: [om('WATER_PROD_M', 'إنتاج المياه')] },
          { value: 'cons', label: 'الاستهلاك الشهري', subtitle: 'مليون متر مكعب', kind: 'columns', last: 60, lines: [om('WATER_CONS_M', 'استهلاك المياه')] },
          { value: 'sectors', label: 'الاستهلاك حسب القطاع', subtitle: 'مليون متر مكعب', kind: 'stacked', lines: [om('WATER_CONS_RES', 'سكني', 'var(--series-1)'), om('WATER_CONS_COM', 'تجاري', 'var(--series-2)'), om('WATER_CONS_GOV', 'حكومي', 'var(--series-3)')] },
          { value: 'connections', label: 'التوصيلات', subtitle: 'توصيلة', kind: 'columns', lines: [om('WATER_CONNECTIONS', 'عدد التوصيلات')] },
          { value: 'sanitation', label: 'الصرف الصحي', subtitle: '% من السكان', lines: [om('WATER_SANITATION', 'الصرف الصحي المحسّن')] },
        ]} />
      </Section>

      <Section id="water-crops" title="المياه والمحاصيل" lead="كم ماءً يكلّف إنتاج طن من المحاصيل في عُمان؟ ولماذا تستهلك الأعلاف معظم مياه الزراعة؟">
        <WaterForCrops />
      </Section>

      <Section id="dams" title="السدود والأفلاج والاستمطار" lead="ما يحفظ مياه الأمطار ويغذي المخزون الجوفي: السدود حسب النوع وسنة التنفيذ، والأفلاج الحية والميتة، ومحطات الاستمطار الصناعي (وزارة الثروة الزراعية والسمكية وموارد المياه).">
        <ReadingGrid>
          <KeyStat entity="OMN" indicator="mafwr.dams.count" label="عدد السدود المنفذة" />
          <KeyStat entity="OMN" indicator="mafwr.dams.capacity" label="السعة التخزينية للسدود" />
          <KeyStat entity="OMN" indicator="mafwr.aflaj.alive" label="الأفلاج الحية" />
          <KeyStat entity="OMN" indicator="mafwr.aflaj.dead" label="الأفلاج الميتة" />
          <KeyStat entity="OMN" indicator="mafwr.seeding.enhancement" label="تعزيز الهطول بالاستمطار" />
        </ReadingGrid>
        <SwitchChart wide height={H} title="السدود" variants={[
          { value: 'capacity', label: 'السعة حسب النوع (تراكمي)', subtitle: 'مليون م³ — بسنة التنفيذ', lines: [mw('dams.protection.capacity', 'الحماية من الفيضانات'), mw('dams.recharge.capacity', 'التغذية الجوفية'), mw('dams.storage.capacity', 'التخزين السطحي')] },
          { value: 'count', label: 'العدد حسب النوع (تراكمي)', subtitle: 'سد', lines: [mw('dams.storage.count', 'التخزين السطحي'), mw('dams.recharge.count', 'التغذية الجوفية'), mw('dams.protection.count', 'الحماية من الفيضانات')] },
          { value: 'total', label: 'إجمالي السعة', subtitle: 'مليون م³ (تراكمي)', lines: [mw('dams.capacity', 'السعة التخزينية')] },
        ]} />
        <SwitchChart wide height={H} title="الأفلاج والاستمطار" variants={[
          { value: 'aflaj-status', label: 'الأفلاج: حية وميتة', subtitle: 'فلج — آخر حصر منشور', lines: [mw('aflaj.alive', 'حية'), mw('aflaj.dead', 'ميتة')] },
          { value: 'aflaj-type', label: 'الأفلاج حسب النوع', subtitle: 'فلج', lines: [mw('aflaj.ghaili', 'غيلية'), mw('aflaj.aini', 'عينية'), mw('aflaj.daoudi', 'داوودية')] },
          { value: 'seeding', label: 'محطات الاستمطار', subtitle: 'محطة (تراكمي بسنة التشغيل)', kind: 'columns', lines: [mw('seeding.stations', 'المحطات')] },
        ]} />
      </Section>

      <Section id="related" title="قطاعات مرتبطة" lead="الأراضي والإسكان، والمنشآت والعاملون في الزراعة والتعدين والصناعة (المركز الوطني للإحصاء، سنوات منشورة محدودة).">
        <SwitchChart wide height={H} title="الأراضي والإسكان" variants={[
          { value: 'land', label: 'قطع الأراضي', subtitle: 'قطعة', kind: 'columns', lines: [om('LAND_PLANNED', 'مخططة'), om('LAND_GRANTED', 'ممنوحة'), om('LAND_REGISTERED', 'مسجلة لأول مرة')] },
          { value: 'permits', label: 'إباحات البناء', subtitle: 'إباحة', kind: 'columns', lines: [om('BUILDING_PERMITS', 'إباحات البناء')] },
        ]} />
        <SwitchChart wide height={H} title="المنشآت والعاملون" variants={[
          { value: 'est', label: 'عدد المنشآت', kind: 'columns', lines: [om('EST_AGRI', 'زراعة وصيد'), om('EST_MINING', 'تعدين ومحاجر'), om('EST_MANUFACTURING', 'صناعة تحويلية'), om('EST_CONSTRUCTION', 'تشييد')] },
          { value: 'workers', label: 'العاملون', kind: 'columns', lines: [om('EST_AGRI_WORKERS', 'زراعة وصيد'), om('EST_MINING_WORKERS', 'تعدين ومحاجر'), om('EST_MANUF_WORKERS', 'صناعة تحويلية')] },
        ]} />
      </Section>
    </>
  );
}
