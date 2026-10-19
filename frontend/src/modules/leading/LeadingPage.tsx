import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart, type RangeOption } from '@/core/components/SeriesChart';

const lines = (indicator: string, entities: string[]) => entities.map((entity) => ({ indicator, entity }));

const RANGES: readonly RangeOption[] = [
  { value: '3y', label: '3y', months: 36 },
  { value: '10y', label: '10y', months: 120 },
  { value: '25y', label: '25y', months: 300 },
  { value: 'all', label: 'الكل', months: 0 },
];
const H = 340;

export default function LeadingPage() {
  return (
    <>
      <PageHead
        title="المؤشرات الاستشرافية: إلى أين يتجه الاقتصاد العالمي؟"
        lead="مؤشرات منظمة التعاون والتنمية (OECD) التي تسبق دورة النشاط بشهور: المؤشر المركب الرائد (CLI)، وثقة الأعمال، وثقة المستهلك. القيمة 100 هي المتوسط طويل الأجل؛ فوقها نشاط أقوى من المعتاد، وتحتها أضعف، والاتجاه الصاعد أو الهابط أهم من الرقم نفسه. تغطي أكبر اقتصادات العالم، وتهمّ عُمان لأنها تقود الطلب على النفط والصادرات."
      />
      <Section title="المؤشر المركب الرائد (CLI)" lead="يستبق نقاط التحول في الناتج بنحو 6–9 أشهر.">
        <SeriesChart wide height={H} ranges={RANGES} defaultRange="10y" title="الرائد: أمريكا والصين والهند وأوروبا واليابان" subtitle="100 = المتوسط" lines={lines('oecd.cli', ['USA', 'CHN', 'IND', 'DEU', 'JPN', 'GBR'])} />
        <SeriesChart height={H} ranges={RANGES} defaultRange="10y" title="الرائد: مجموعة العشرين والسبع" lines={lines('oecd.cli', ['G20', 'G7', 'NAFTA'])} />
        <SeriesChart height={H} ranges={RANGES} defaultRange="10y" title="الرائد: الأسواق الصاعدة الكبرى" lines={lines('oecd.cli', ['BRA', 'IDN', 'ZAF', 'TUR', 'MEX'])} />
      </Section>
      <Section title="ثقة الأعمال" lead="مسوح الشركات الصناعية: الطلبات والإنتاج والتوقعات.">
        <SeriesChart wide height={H} ranges={RANGES} defaultRange="10y" title="ثقة الأعمال المركبة" subtitle="100 = المتوسط" lines={lines('oecd.business_confidence', ['USA', 'DEU', 'JPN', 'GBR', 'FRA', 'CHN'])} />
        <SeriesChart height={H} ranges={RANGES} defaultRange="10y" title="ثقة الأعمال: دول أخرى" lines={lines('oecd.business_confidence', ['KOR', 'ITA', 'ESP', 'CAN', 'AUS', 'BRA'])} />
      </Section>
      <Section title="ثقة المستهلك" lead="مسوح الأسر: النظرة إلى الدخل والإنفاق والعمل.">
        <SeriesChart wide height={H} ranges={RANGES} defaultRange="10y" title="ثقة المستهلك المركبة" subtitle="100 = المتوسط" lines={lines('oecd.consumer_confidence', ['USA', 'DEU', 'JPN', 'GBR', 'FRA', 'CHN'])} />
        <SeriesChart height={H} ranges={RANGES} defaultRange="10y" title="ثقة المستهلك: دول أخرى" lines={lines('oecd.consumer_confidence', ['KOR', 'ITA', 'ESP', 'CAN', 'AUS', 'BRA'])} />
      </Section>
    </>
  );
}
