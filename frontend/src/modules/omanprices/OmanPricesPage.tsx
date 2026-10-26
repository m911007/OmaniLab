import { ReadingGrid } from '@/core/components/ReadingGrid';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { oman } from '@/core/lines';

const H = 340;
const on = (indicator: string, label: string) => ({ indicator, entity: 'OMN', label });
const g = (code: string, label: string, kind: 'INDEX' | 'INFL' = 'INDEX') => ({ indicator: `ncsi.CPIG_${code}_${kind}`, entity: 'OMN', label });
const r = (code: string, label: string, kind: 'INDEX' | 'INFL' = 'INFL') => ({ indicator: `ncsi.CPIR_${code}_${kind}`, entity: 'OMN', label });
const imfCpi = (entity: string, label: string) => ({ indicator: 'imf.cpi_yoy', entity, label });

const REGIONS: [string, string][] = [
  ['MUSCAT', 'مسقط'], ['DHOFAR', 'ظفار'], ['N_BATINAH', 'شمال الباطنة'], ['S_BATINAH', 'جنوب الباطنة'], ['DAKHILIYAH', 'الداخلية'],
  ['N_SHARQIYAH', 'شمال الشرقية'], ['S_SHARQIYAH', 'جنوب الشرقية'], ['DHAHIRAH', 'الظاهرة'], ['BURAIMI', 'البريمي'], ['MUSANDAM', 'مسندم'], ['WUSTA', 'الوسطى'],
];

/**
 * التضخم والأسعار في السوق العُماني: رسوم قليلة بمبدّل (كما في «النمو والناتج») بدل رسوم كثيرة متفرقة —
 * التضخم العام، والغذاء، والسكن والخدمات، والمحافظات، والأرقام القياسية.
 */
export default function OmanPricesPage() {
  return (
    <>
      <PageHead
        title="أسعار المنتجات والسلع في السوق العُماني"
        lead="الأرقام القياسية لأسعار المستهلك (سنة الأساس 2018) حسب مجموعات السلع والخدمات والمحافظة، شهريًا من المركز الوطني للإحصاء والمعلومات، مع مقارنات صندوق النقد. مرصد أسعار هيئة حماية المستهلك لا يوفّر واجهة بيانات مفتوحة، فلا تُعرض أسعار السلع المفردة بالريال."
      />

      <ReadingGrid>
        <KeyStat entity="OMN" indicator="ncsi.CPIG_FOOD_BEV_INFL" label="تضخم الغذاء والمشروبات" goodWhen="down" />
        <KeyStat entity="OMN" indicator="ncsi.CPIG_VEG_INFL" label="تضخم الخضروات" goodWhen="down" />
        <KeyStat entity="OMN" indicator="ncsi.CPIG_MEAT_INFL" label="تضخم اللحوم" goodWhen="down" />
        <KeyStat entity="OMN" indicator="ncsi.CPIG_HOUSING_INFL" label="تضخم السكن والمرافق" goodWhen="down" />
      </ReadingGrid>

      <Section id="inflation" title="التضخم" lead="كم ترتفع الأسعار؟ وكيف نقارن بالخليج؟ وأي بنود الإنفاق تقود الغلاء؟">
        <SwitchChart wide height={H} title="التضخم السنوي" variants={[
          { value: 'monthly', label: 'شهري', subtitle: '% سنويًا — المركز الوطني', lines: [on('ncsi.CPI_INFLATION', 'التضخم السنوي')] },
          { value: 'gcc', label: 'مع الخليج', subtitle: '% سنويًا — صندوق النقد', from: '2021', lines: [imfCpi('OMN', 'عُمان'), imfCpi('SAU', 'السعودية'), imfCpi('ARE', 'الإمارات'), imfCpi('QAT', 'قطر'), imfCpi('KWT', 'الكويت'), imfCpi('BHR', 'البحرين')] },
          { value: 'items', label: 'حسب البند', subtitle: 'أي بنود الإنفاق تقود الغلاء؟ % — صندوق النقد', from: '2021', lines: [{ indicator: 'imf.cpi_yoy', entity: 'OMN', label: 'العام' }, { indicator: 'imf.cpi_yoy_food', entity: 'OMN', label: 'الغذاء' }, { indicator: 'imf.cpi_yoy_housing', entity: 'OMN', label: 'السكن والمرافق' }, { indicator: 'imf.cpi_yoy_transport', entity: 'OMN', label: 'النقل' }] },
          { value: 'avg', label: 'المتوسط السنوي', subtitle: '% — صندوق النقد (الخط المتقطع توقعات)', from: '2005', imfForecast: true, lines: oman('imf.PCPIPCH', 'عُمان') },
        ]} />
      </Section>

      <Section id="food" title="أسعار الغذاء" lead="أي الأطعمة ترتفع أسرع؟ التضخم السنوي لكل فئة، والرقم القياسي منذ 2018.">
        <SwitchChart wide height={H} title="الغذاء بالتفصيل" variants={[
          { value: 'protein', label: 'لحوم وأسماك وألبان', subtitle: '% سنويًا', from: '2019', lines: [g('MEAT', 'لحوم', 'INFL'), g('FISH', 'أسماك', 'INFL'), g('DAIRY', 'حليب وجبن وبيض', 'INFL')] },
          { value: 'fresh', label: 'خضروات وفواكه وحبوب', subtitle: '% سنويًا', from: '2019', lines: [g('VEG', 'خضروات', 'INFL'), g('FRUIT', 'فواكه', 'INFL'), g('BREAD', 'خبز وحبوب', 'INFL')] },
          { value: 'pantry', label: 'زيوت وسكر ومشروبات', subtitle: '% سنويًا', from: '2019', lines: [g('OILS', 'زيوت ودهون', 'INFL'), g('SUGAR', 'سكر وحلويات', 'INFL'), g('NONALC', 'مشروبات', 'INFL')] },
          { value: 'index', label: 'الرقم القياسي', subtitle: '2018 = 100', from: '2018', lines: [g('FOOD_BEV', 'غذاء ومشروبات'), g('MEAT', 'لحوم'), g('VEG', 'خضروات'), g('FRUIT', 'فواكه')] },
          { value: 'index2', label: 'الرقم القياسي: أسماك وألبان وحبوب', subtitle: '2018 = 100', from: '2018', lines: [g('FISH', 'أسماك'), g('DAIRY', 'ألبان وبيض'), g('BREAD', 'خبز وحبوب'), g('OILS', 'زيوت')] },
        ]} />
      </Section>

      <Section id="services" title="السكن والنقل والخدمات" lead="الإيجار والطاقة والوقود والتعليم والصحة والاتصالات وغيرها.">
        <SwitchChart wide height={H} title="السكن والنقل والخدمات" variants={[
          { value: 'infl', label: 'تضخم السكن والنقل', subtitle: '% سنويًا', from: '2019', lines: [g('HOUSING', 'سكن', 'INFL'), g('TRANSPORT', 'نقل', 'INFL'), g('ALL', 'جميع السلع', 'INFL')] },
          { value: 'housing', label: 'السكن والنقل', subtitle: '2018 = 100', from: '2018', lines: [g('HOUSING', 'سكن ومياه وكهرباء'), g('TRANSPORT', 'نقل'), g('ALL', 'جميع السلع')] },
          { value: 'services', label: 'تعليم وصحة واتصالات', subtitle: '2018 = 100', from: '2018', lines: [g('EDU', 'تعليم'), g('HEALTH', 'صحة'), g('COMM', 'اتصالات'), g('RESTAURANT', 'مطاعم وفنادق')] },
          { value: 'goods', label: 'ملابس وأثاث وتبغ', subtitle: '2018 = 100', from: '2018', lines: [g('CLOTHING', 'ملابس وأحذية'), g('FURNISH', 'أثاث وتجهيزات'), g('TOBACCO', 'تبغ'), g('RECREATION', 'ثقافة وترفيه')] },
        ]} />
      </Section>

      <Section id="regions" title="الأسعار حسب المحافظة" lead="أين ترتفع الأسعار أكثر؟">
        <SwitchChart wide height={H} title="المحافظات" variants={[
          { value: 'big', label: 'التضخم: محافظات الكثافة السكانية', subtitle: '% سنويًا', from: '2019', lines: REGIONS.slice(0, 5).map(([c, l]) => r(c, l)) },
          { value: 'rest', label: 'التضخم: بقية المحافظات', subtitle: '% سنويًا', from: '2019', lines: REGIONS.slice(5, 11).map(([c, l]) => r(c, l)) },
          { value: 'index', label: 'الرقم القياسي', subtitle: '2018 = 100', from: '2018', lines: [r('MUSCAT', 'مسقط', 'INDEX'), r('DHOFAR', 'ظفار', 'INDEX'), r('N_BATINAH', 'شمال الباطنة', 'INDEX'), r('S_BATINAH', 'جنوب الباطنة', 'INDEX')] },
        ]} />
      </Section>

      <Section id="indices" title="الأرقام القياسية للأسعار" lead="مستوى الأسعار نفسه لا نسبة تغيّره: للمستهلك وللمنتج، ومقارنةً بالخليج.">
        <SwitchChart wide height={H} title="الأرقام القياسية" variants={[
          { value: 'cpi', label: 'المستهلك', subtitle: '2018 = 100', lines: [on('ncsi.CPI_INDEX', 'الرقم القياسي')] },
          { value: 'ppi', label: 'المنتج', subtitle: '2018 = 100', lines: [on('ncsi.PPI_INDEX', 'أسعار المنتج')] },
          { value: 'ppi-inf', label: 'تضخم المنتج', subtitle: '% سنويًا، فصلي', lines: [on('ncsi.PPI_INFLATION', 'تضخم أسعار المنتج')] },
          { value: 'gcc', label: 'المستهلك مع الخليج', subtitle: '2010 = 100، سنوي — البنك الدولي', from: '2000', lines: gccLines('wb.FP.CPI.TOTL') },
        ]} />
      </Section>
    </>
  );
}
