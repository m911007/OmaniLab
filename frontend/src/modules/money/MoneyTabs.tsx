import { Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { MoneySection } from '@/modules/oman/sections';
import { ScoreTable, type ScoreRow } from '@/modules/fiscal/GulfScorecard';
import { CreditMoneyGrowth, CreditToMoney, RealRates } from './MoneyDerived';

const on = (indicator: string, label: string, color: string) => ({ indicator, entity: 'OMN', label, color });

/** الصورة العامة: هل يتسع الإقراض؟ وكم كلفته الحقيقية؟ وكيف يتوزع النقد؟ */
export function MoneyOverviewTab() {
  return (
    <>
      <Section id="pace" title="هل يتسع الإقراض؟" lead="وتيرة الائتمان والنقد وعمق الوساطة، مشتقة من الأرصدة الشهرية الرسمية.">
        <CreditMoneyGrowth />
        <CreditToMoney />
      </Section>
      <Section id="cost" title="كم تكلّف القروض فعلًا؟" lead="الفائدة الاسمية بعد خصم التضخم، وهامش الوساطة. الريال مربوط بالدولار فتتبع الفائدة المحلية الأمريكية غالبًا.">
        <RealRates />
      </Section>
      <Section id="mix" title="مم يتكوّن النقد؟" lead="النقد الضيق (الأدوات الجارية) وشبه النقد (الادخار والودائع الزمنية)؛ مجموعهما النقد الواسع.">
        <SeriesChart wide kind="stacked" last={36} from="2010" title="مكوّنات النقد الواسع" subtitle="مليون ريال عُماني" lines={[on('ncsi.MON_M1', 'النقد الضيق M1', 'var(--series-1)'), on('ncsi.MON_QUASI_MONEY', 'شبه النقد', 'var(--series-3)')]} />
      </Section>
    </>
  );
}

/** النقد والاحتياطيات: المستكشفات الكاملة للنقد والائتمان وأسعار الفائدة والاحتياطيات وسلامة المصارف. */
export function MoneyDetailTab() {
  return <MoneySection />;
}

const ROWS: ScoreRow[] = [
  { key: 'wb.FS.AST.PRVT.GD.ZS', label: 'الائتمان للقطاع الخاص (% من الناتج)', better: 'high' },
  { key: 'wb.FB.AST.NPER.ZS', label: 'القروض المتعثرة (% من القروض)', better: 'low' },
  { key: 'wb.FB.CBK.BRCH.P5', label: 'فروع البنوك (لكل 100 ألف بالغ)', better: 'high' },
  { key: 'imf.PCPIPCH', label: 'التضخم (%)', better: 'low' },
];

/** الخليج: عمق الائتمان وجودة المحافظ وانتشار الفروع والتضخم مقارنةً بالدول الست. */
export function MoneyGulfTab() {
  return (
    <>
      <Section id="scorecard" title="أين نقف بين الجيران؟" lead="آخر قيمة متاحة لكل دولة؛ الأخضر الأفضل بين الدول الست.">
        <ScoreTable rows={ROWS} title="بطاقة أداء القطاع المصرفي الخليجية" subtitle="آخر قيمة فعلية — البنك الدولي وصندوق النقد" note="الأخضر الأفضل بين الدول الست في كل صف. عمق الائتمان الأعلى يعني وساطة أعمق لكنه قد يعكس مخاطر أعلى؛ تُقرأ مع نسبة القروض المتعثرة." />
      </Section>
      <Section id="history" title="المسار التاريخي" lead="الدول الست معًا.">
        <SwitchChart wide height={340} title="القطاع المالي: عُمان والخليج" variants={[
          { value: 'credit', label: 'الائتمان للقطاع الخاص', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.FS.AST.PRVT.GD.ZS') },
          { value: 'npl', label: 'القروض المتعثرة', subtitle: '% من القروض', from: '2000', lines: gccLines('wb.FB.AST.NPER.ZS') },
        ]} />
      </Section>
    </>
  );
}
