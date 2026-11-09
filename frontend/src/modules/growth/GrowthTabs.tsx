import { Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { oman } from '@/core/lines';
import { GrowthSection, StructureSection } from '@/modules/oman/sections';
import { ScoreTable, type ScoreRow } from '@/modules/fiscal/GulfScorecard';
import { ActivityMix, NonOilGrowth, PetroSplit } from './GdpStructure';

const H = 340;
const on = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'OMN', label, color });

/** الصورة العامة: هل ينمو الاقتصاد؟ النمو بألوان الارتفاع والانكماش، ثم مستكشف الناتج وتحليل الارتباط، ثم هيكل الناتج (كان تبويبًا مستقلًا). */
export function GrowthOverviewTab() {
  return (
    <>
      <Section id="speed" title="هل ينمو الاقتصاد؟" lead="النمو الحقيقي بعد استبعاد الأسعار: الأخضر نمو والأحمر انكماش (الخط المتقطع توقعات صندوق النقد)، مع إمكان إظهار سعر النفط.">
        <SeriesChart wide oil kind="columns" last={21} height={H} title="نمو الناتج المحلي الحقيقي" subtitle="% — صندوق النقد" imfForecast lines={oman('imf.NGDP_RPCH', 'عُمان')} />
      </Section>
      <GrowthSection />
      <GrowthStructureSections />
    </>
  );
}

/** هيكل الناتج: النفطي وغير النفطي، وأنشطة الناتج، ومن يقود النمو، ثم هيكل البنك الدولي والاستثمار. */
function GrowthStructureSections() {
  return (
    <>
      <Section id="petro" title="كم يعتمد الناتج على النفط؟" lead="حصة الأنشطة النفطية مقابل غير النفطية وتفصيل الأنشطة، من الحسابات القومية الرسمية.">
        <PetroSplit />
        <ActivityMix />
        <NonOilGrowth />
      </Section>
      <StructureSection />
    </>
  );
}

/** الطلب والاستثمار: على ماذا يُنفق الناتج؟ وهل تكفي المدخرات لتمويل الاستثمار؟ */
export function GrowthDemandTab() {
  return (
    <>
      <Section id="demand" title="على ماذا يُنفق الناتج؟" lead="أوزان الاستهلاك الخاص والحكومي والاستثمار والتجارة في الناتج، ومقارنتها بالخليج (البنك الدولي).">
        <SwitchChart wide height={H} title="مكوّنات الإنفاق على الناتج" variants={[
          { value: 'mix', label: 'عُمان', subtitle: '% من الناتج', from: '2000', lines: [on('wb.NE.CON.PRVT.ZS', 'الاستهلاك الخاص', 'var(--series-1)'), on('wb.NE.CON.GOVT.ZS', 'الاستهلاك الحكومي', 'var(--series-5)'), on('wb.NE.GDI.TOTL.ZS', 'تكوين رأس المال', 'var(--series-3)'), on('wb.NE.EXP.GNFS.ZS', 'الصادرات', 'var(--series-2)'), on('wb.NE.IMP.GNFS.ZS', 'الواردات', 'var(--series-4)')] },
          { value: 'priv', label: 'الاستهلاك الخاص: الخليج', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NE.CON.PRVT.ZS') },
          { value: 'gov', label: 'الاستهلاك الحكومي: الخليج', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NE.CON.GOVT.ZS') },
          { value: 'inv', label: 'الاستثمار: الخليج', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NE.GDI.TOTL.ZS') },
          { value: 'open', label: 'الانفتاح التجاري: الخليج', subtitle: 'الصادرات + الواردات % من الناتج', from: '2000', lines: gccLines('wb.NE.TRD.GNFS.ZS') },
        ]} />
      </Section>
    </>
  );
}

const ROWS: ScoreRow[] = [
  { key: 'imf.NGDP_RPCH', label: 'نمو الناتج الحقيقي (%)', better: 'high' },
  { key: 'imf.NGDPDPC', label: 'نصيب الفرد من الناتج ($)', better: 'high', digits: 0 },
  { key: 'imf.NGDPD', label: 'الناتج الجاري (مليار $)', better: 'high', digits: 0 },
  { key: 'imf.PCPIPCH', label: 'التضخم (%)', better: 'low' },
  { key: 'imf.BCA_NGDPD', label: 'الحساب الجاري (% من الناتج)', better: 'high' },
  { key: 'imf.GGXWDG_NGDP', label: 'الدين الحكومي (% من الناتج)', better: 'low' },
  { key: 'wb.NE.GDI.TOTL.ZS', label: 'الاستثمار (% من الناتج)', better: 'high' },
];

/** الخليج: أين تقف عُمان بين الدول الست في النمو والدخل والتضخم والاستثمار. */
export function GrowthGulfTab() {
  return (
    <>
      <Section id="scorecard" title="أين نقف بين الجيران؟" lead="آخر سنة فعلية متاحة لكل دولة؛ الأخضر الأفضل بين الدول الست في كل صف.">
        <ScoreTable rows={ROWS} title="بطاقة الأداء الاقتصادي الخليجية" subtitle="آخر سنة فعلية — صندوق النقد والبنك الدولي" note="الأخضر الأفضل بين الدول الست في كل صف (الأعلى أفضل في النمو والدخل والاستثمار والحساب الجاري، والأدنى أفضل في التضخم والدين). الترتيب نسبي لا مطلق." />
      </Section>
      <Section id="history" title="المسار التاريخي" lead="الدول الست معًا (الخط المتقطع توقعات صندوق النقد).">
        <SwitchChart wide height={H} title="الاقتصاد: عُمان والخليج" imfForecast variants={[
          { value: 'growth', label: 'نمو الناتج', subtitle: '% حقيقي', from: '2005', imfForecast: true, oil: true, lines: gccLines('imf.NGDP_RPCH') },
          { value: 'pc', label: 'نصيب الفرد', subtitle: 'دولار', from: '2005', imfForecast: true, lines: gccLines('imf.NGDPDPC') },
          { value: 'gdp', label: 'الناتج الجاري', subtitle: 'مليار دولار', from: '2005', imfForecast: true, lines: gccLines('imf.NGDPD') },
          { value: 'infl', label: 'التضخم', subtitle: '%', from: '2005', imfForecast: true, lines: gccLines('imf.PCPIPCH') },
        ]} />
      </Section>
    </>
  );
}
