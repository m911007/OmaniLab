import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';

const g = (code: string, label: string, kind: 'INDEX' | 'INFL' = 'INDEX') => ({ indicator: `ncsi.CPIG_${code}_${kind}`, entity: 'OMN', label });
const r = (code: string, label: string, kind: 'INDEX' | 'INFL' = 'INFL') => ({ indicator: `ncsi.CPIR_${code}_${kind}`, entity: 'OMN', label });

const REGIONS: [string, string][] = [
  ['MUSCAT', 'مسقط'], ['DHOFAR', 'ظفار'], ['N_BATINAH', 'شمال الباطنة'], ['S_BATINAH', 'جنوب الباطنة'], ['DAKHILIYAH', 'الداخلية'],
  ['N_SHARQIYAH', 'شمال الشرقية'], ['S_SHARQIYAH', 'جنوب الشرقية'], ['DHAHIRAH', 'الظاهرة'], ['BURAIMI', 'البريمي'], ['MUSANDAM', 'مسندم'], ['WUSTA', 'الوسطى'],
];

export default function OmanPricesPage() {
  return (
    <>
      <PageHead
        title="أسعار المنتجات والسلع في السوق العُماني"
        lead="الأرقام القياسية لأسعار المستهلك لسنة الأساس 2018 حسب مجموعات السلع والخدمات (لحوم، أسماك، ألبان، خضروات، فواكه، سكن، نقل…) وحسب المحافظة، شهريًا من المركز الوطني للإحصاء والمعلومات. هذه أدق بيانات رسمية متاحة آليًا؛ مرصد الأسعار لهيئة حماية المستهلك (750 ألف سلعة في مسقط) لا يوفّر واجهة بيانات مفتوحة، فلا نعرض أسعار السلع المفردة بالريال."
      />

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="ncsi.CPIG_ALL_INFL" label="التضخم السنوي — جميع السلع (%)" />
        <KeyStat entity="OMN" indicator="ncsi.CPIG_FOOD_BEV_INFL" label="تضخم الغذاء والمشروبات (%)" />
        <KeyStat entity="OMN" indicator="ncsi.CPIG_VEG_INFL" label="تضخم الخضروات (%)" />
        <KeyStat entity="OMN" indicator="ncsi.CPIG_MEAT_INFL" label="تضخم اللحوم (%)" />
      </div>

      <Section title="أسعار الغذاء بالتفصيل" lead="تضخم أسعار كل فئة غذائية على أساس سنوي: أيها ترتفع أسرع؟">
        <SeriesChart title="تضخم الغذاء: اللحوم والأسماك والألبان" subtitle="% سنويًا" lines={[g('MEAT', 'لحوم', 'INFL'), g('FISH', 'أسماك', 'INFL'), g('DAIRY', 'حليب وجبن وبيض', 'INFL')]} from="2019" />
        <SeriesChart title="تضخم الخضروات والفواكه والحبوب" subtitle="% سنويًا" lines={[g('VEG', 'خضروات', 'INFL'), g('FRUIT', 'فواكه', 'INFL'), g('BREAD', 'خبز وحبوب', 'INFL')]} from="2019" />
        <SeriesChart title="تضخم الزيوت والسكر والمشروبات" subtitle="% سنويًا" lines={[g('OILS', 'زيوت ودهون', 'INFL'), g('SUGAR', 'سكر وحلويات', 'INFL'), g('NONALC', 'مشروبات', 'INFL')]} from="2019" />
        <SeriesChart title="الرقم القياسي لأسعار الغذاء" subtitle="2018 = 100" lines={[g('FOOD_BEV', 'غذاء ومشروبات'), g('MEAT', 'لحوم'), g('VEG', 'خضروات'), g('FRUIT', 'فواكه')]} from="2018" />
        <SeriesChart title="الرقم القياسي: أسماك وألبان وحبوب" subtitle="2018 = 100" lines={[g('FISH', 'أسماك'), g('DAIRY', 'ألبان وبيض'), g('BREAD', 'خبز وحبوب'), g('OILS', 'زيوت')]} from="2018" />
      </Section>

      <Section title="السكن والنقل والخدمات" lead="الإيجار والطاقة والوقود والتعليم والصحة والاتصالات.">
        <SeriesChart title="الرقم القياسي: السكن والنقل" subtitle="2018 = 100" lines={[g('HOUSING', 'سكن ومياه وكهرباء'), g('TRANSPORT', 'نقل'), g('ALL', 'جميع السلع')]} from="2018" />
        <SeriesChart title="الرقم القياسي: التعليم والصحة والاتصالات" subtitle="2018 = 100" lines={[g('EDU', 'تعليم'), g('HEALTH', 'صحة'), g('COMM', 'اتصالات'), g('RESTAURANT', 'مطاعم وفنادق')]} from="2018" />
        <SeriesChart title="الرقم القياسي: الملابس والأثاث والتبغ" subtitle="2018 = 100" lines={[g('CLOTHING', 'ملابس وأحذية'), g('FURNISH', 'أثاث وتجهيزات'), g('TOBACCO', 'تبغ'), g('RECREATION', 'ثقافة وترفيه')]} from="2018" />
        <SeriesChart title="تضخم السكن والنقل" subtitle="% سنويًا" lines={[g('HOUSING', 'سكن', 'INFL'), g('TRANSPORT', 'نقل', 'INFL'), g('ALL', 'جميع السلع', 'INFL')]} from="2019" />
      </Section>

      <Section title="الأسعار حسب المحافظة" lead="أين ترتفع الأسعار أكثر؟ التضخم السنوي والرقم القياسي لكل محافظة.">
        <SeriesChart title="التضخم السنوي: محافظات الكثافة السكانية" subtitle="% سنويًا" lines={REGIONS.slice(0, 5).map(([c, l]) => r(c, l))} from="2019" />
        <SeriesChart title="التضخم السنوي: بقية المحافظات" subtitle="% سنويًا" lines={REGIONS.slice(5, 11).map(([c, l]) => r(c, l))} from="2019" />
        <SeriesChart title="الرقم القياسي: مسقط وظفار والباطنة" subtitle="2018 = 100" lines={[r('MUSCAT', 'مسقط', 'INDEX'), r('DHOFAR', 'ظفار', 'INDEX'), r('N_BATINAH', 'شمال الباطنة', 'INDEX'), r('S_BATINAH', 'جنوب الباطنة', 'INDEX')]} from="2018" />
      </Section>
    </>
  );
}
