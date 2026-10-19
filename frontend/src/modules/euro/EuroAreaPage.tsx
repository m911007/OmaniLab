import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart, type RangeOption } from '@/core/components/SeriesChart';

const eu = (indicator: string, label: string) => ({ indicator, entity: 'EMU', label });
const us = (indicator: string, label: string) => ({ indicator, entity: 'USA', label });
const uk = (indicator: string, label: string) => ({ indicator, entity: 'GBR', label });

const DAILY: readonly RangeOption[] = [
  { value: '1y', label: 'سنة', months: 12 },
  { value: '5y', label: '5 سنوات', months: 60 },
  { value: '10y', label: '10 سنوات', months: 120 },
  { value: 'all', label: 'الكل', months: 0 },
];
const MONTHLY: readonly RangeOption[] = [
  { value: '3y', label: '3 سنوات', months: 36 },
  { value: '10y', label: '10 سنوات', months: 120 },
  { value: 'all', label: 'الكل', months: 0 },
];
const H = 340;
const BIG = ['DEU', 'FRA', 'ITA', 'ESP', 'NLD', 'POL'];
const ec = (indicator: string, entities: string[]) => entities.map((entity) => ({ indicator, entity }));

export default function EuroAreaPage() {
  return (
    <>
      <PageHead
        title="أوروبا: منطقة اليورو وبريطانيا"
        lead="ثاني أكبر منطقة اقتصادية بعد أمريكا وأهم شريك تجاري لعُمان في الاستيراد: فائدة البنك المركزي الأوروبي، وعوائد السندات، والتضخم وبنوده، والبطالة والنمو، وأسعار صرف اليورو. من بوابة البنك المركزي الأوروبي الرسمية بتاريخها الكامل، ثم المملكة المتحدة من بنك إنجلترا، مع مقارنة مباشرة بأمريكا."
      />

      <div className="grid cols-4">
        <KeyStat trend entity="EMU" indicator="ecb.deposit_rate" label="فائدة الودائع (%)" />
        <KeyStat trend entity="EMU" indicator="ecb.estr" label="€STR (%)" />
        <KeyStat trend entity="EMU" indicator="ecb.yield_10y" label="عائد 10 سنوات (%)" />
        <KeyStat trend entity="EMU" indicator="ecb.eurusd" label="اليورو مقابل الدولار" />
        <KeyStat trend entity="EMU" indicator="ecb.hicp" label="التضخم السنوي (%)" goodWhen="down" />
        <KeyStat trend entity="EMU" indicator="ecb.hicp_core" label="التضخم الأساسي (%)" goodWhen="down" />
        <KeyStat trend entity="EMU" indicator="ecb.unemployment" label="البطالة (%)" goodWhen="down" />
        <KeyStat trend entity="EMU" indicator="ecb.gdp_growth" label="نمو الناتج (% سنويًا)" goodWhen="up" />
      </div>

      <Section title="الفائدة والسياسة النقدية" lead="الفائدة الأوروبية تحدد كلفة التمويل لأكبر شركاء عُمان التجاريين في أوروبا، وقوة اليورو مقابل الدولار (والريال المربوط به).">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="all" title="أسعار فائدة البنك المركزي الأوروبي" subtitle="% — يومي" lines={[eu('ecb.mro_rate', 'إعادة التمويل الرئيسية'), eu('ecb.deposit_rate', 'الودائع'), eu('ecb.lending_rate', 'الإقراض الهامشي')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="€STR ويوريبور 3 أشهر" subtitle="%" lines={[eu('ecb.estr', '€STR (يومي)'), eu('ecb.euribor_3m', 'يوريبور 3 أشهر (شهري)')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="5y" title="فائدة الودائع: أوروبا مقابل أمريكا" subtitle="%" lines={[eu('ecb.deposit_rate', 'أوروبا (ودائع)'), us('us.effr', 'أمريكا (الفدرالية)')]} />
      </Section>

      <Section title="عوائد السندات الحكومية" lead="منحنى عوائد السندات السيادية لمنطقة اليورو (ألمانيا وفرنسا وغيرهما مجتمعة) من ECB.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="10y" title="منحنى العوائد الأوروبي" subtitle="% — يومي" lines={[eu('ecb.yield_1y', 'سنة'), eu('ecb.yield_2y', 'سنتان'), eu('ecb.yield_5y', '5 سنوات'), eu('ecb.yield_10y', '10 سنوات'), eu('ecb.yield_30y', '30 سنة')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="عائد 10 سنوات: أوروبا مقابل أمريكا" subtitle="%" lines={[eu('ecb.yield_10y', 'منطقة اليورو'), us('us.yield_10y', 'أمريكا')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="عائد سنتين: أوروبا مقابل أمريكا" subtitle="%" lines={[eu('ecb.yield_2y', 'منطقة اليورو'), us('us.yield_2y', 'أمريكا')]} />
      </Section>

      <Section title="التضخم والنقود" lead="التضخم العام والأساسي وبنوده، والمعروض النقدي الأوسع M3.">
        <SeriesChart wide height={H} ranges={MONTHLY} defaultRange="all" title="التضخم السنوي: العام والأساسي" subtitle="% — شهري" lines={[eu('ecb.hicp', 'العام'), eu('ecb.hicp_core', 'الأساسي')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم الطاقة والغذاء والخدمات" subtitle="% سنويًا" lines={[eu('ecb.hicp_energy', 'الطاقة'), eu('ecb.hicp_food', 'الغذاء'), eu('ecb.hicp_services', 'الخدمات')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="التضخم: أوروبا مقابل أمريكا" subtitle="% سنويًا" lines={[eu('ecb.hicp', 'منطقة اليورو'), us('us.cpi_yoy', 'أمريكا')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="نمو المعروض النقدي M3" subtitle="% سنويًا" lines={[eu('ecb.m3_growth', 'M3')]} />
      </Section>

      <Section title="النمو والبطالة والضغط المالي">
        <SeriesChart height={H} title="نمو الناتج المحلي الحقيقي" subtitle="% سنويًا — ربع سنوي" kind="columns" last={60} lines={[eu('ecb.gdp_growth', 'نمو الناتج')]} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="معدل البطالة" subtitle="%" lines={[eu('ecb.unemployment', 'البطالة')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="مؤشر الضغط النظامي (CISS)" subtitle="0 هدوء — 1 ضغط شديد" lines={[eu('ecb.ciss', 'الضغط النظامي')]} />
      </Section>

      <Section title="اليورو وأسعار الصرف" lead="أسعار الصرف المرجعية اليومية للبنك المركزي الأوروبي. الريال العُماني مربوط بالدولار، فارتفاع اليورو مقابل الدولار يعني ارتفاعه مقابل الريال.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="all" title="اليورو مقابل الدولار" subtitle="دولار لكل يورو" lines={[eu('ecb.eurusd', 'EUR/USD')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="اليورو مقابل الجنيه والفرنك" lines={[eu('ecb.eurgbp', 'الجنيه الإسترليني'), eu('ecb.eurchf', 'الفرنك السويسري')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="اليورو مقابل الين" subtitle="ين لكل يورو" lines={[eu('ecb.eurjpy', 'الين')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="اليورو مقابل اليوان" subtitle="يوان لكل يورو" lines={[eu('ecb.eurcny', 'اليوان')]} />
      </Section>

      <Section title="المملكة المتحدة" lead="بنك إنجلترا: سعر الفائدة الرسمي وعوائد السندات الحكومية (الجيلتس) وسعر الجنيه الإسترليني، منذ سبعينيات القرن الماضي.">
        <SeriesChart wide height={H} ranges={DAILY} defaultRange="all" title="سعر الفائدة الرسمي لبنك إنجلترا وSONIA" subtitle="% — يومي" lines={[uk('boe.bank_rate', 'Bank Rate'), uk('boe.sonia', 'SONIA')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="عوائد السندات البريطانية" subtitle="%" lines={[uk('boe.gilt_5y', '5 سنوات'), uk('boe.gilt_10y', '10 سنوات'), uk('boe.gilt_20y', '20 سنة')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="عائد 10 سنوات: بريطانيا وأوروبا وأمريكا" subtitle="%" lines={[uk('boe.gilt_10y', 'بريطانيا'), eu('ecb.yield_10y', 'منطقة اليورو'), us('us.yield_10y', 'أمريكا')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="all" title="الجنيه الإسترليني مقابل الدولار" subtitle="دولار لكل جنيه" lines={[uk('boe.gbpusd', 'GBP/USD')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="الجنيه مقابل اليورو" subtitle="يورو لكل جنيه" lines={[uk('boe.gbpeur', 'GBP/EUR')]} />
        <SeriesChart height={H} ranges={DAILY} defaultRange="10y" title="الجنيه مقابل الين" subtitle="ين لكل جنيه" lines={[uk('boe.gbpjpy', 'GBP/JPY')]} />
      </Section>

      <Section title="دول أوروبا دولةً دولة (Eurostat)" lead="التضخم والبطالة والنمو والدين والعوائد لأكبر الاقتصادات الأوروبية من المكتب الإحصائي للاتحاد الأوروبي.">
        <SeriesChart wide height={H} ranges={MONTHLY} defaultRange="10y" title="التضخم السنوي في أكبر الاقتصادات الأوروبية" subtitle="% — HICP" lines={ec('eurostat.hicp', BIG)} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم الغذاء" subtitle="% سنويًا" lines={ec('eurostat.hicp_food', BIG)} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="تضخم الطاقة" subtitle="% سنويًا" lines={ec('eurostat.hicp_energy', BIG)} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="معدل البطالة" subtitle="%" lines={ec('eurostat.unemployment', BIG)} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="بطالة الشباب (تحت 25 سنة)" subtitle="%" lines={ec('eurostat.unemployment_youth', BIG)} />
        <SeriesChart height={H} title="نمو الناتج المحلي الحقيقي (ربع سنوي، سنويًا)" subtitle="%" lines={ec('eurostat.gdp_growth', BIG)} from="2010" />
        <SeriesChart height={H} title="الدين الحكومي" subtitle="% من الناتج" lines={ec('eurostat.gov_debt', BIG)} from="2000" />
        <SeriesChart height={H} title="الناتج المحلي للفرد (يورو بأسعار 2010)" lines={ec('eurostat.gdp_per_capita', BIG)} from="2000" />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="10y" title="الإنتاج الصناعي" subtitle="2021 = 100" lines={ec('eurostat.industrial_production', BIG)} />
        <SeriesChart height={H} ranges={MONTHLY} defaultRange="all" title="عوائد السندات الحكومية طويلة الأجل" subtitle="% — معيار ماستريخت" lines={ec('eurostat.bond_yield_10y', BIG)} />
      </Section>
    </>
  );
}
