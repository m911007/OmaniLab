import { Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { ReadingGrid } from '@/core/components/ReadingGrid';
import { KeyStat } from '@/core/components/KeyStat';
import { gccLines } from '@/core/groups';
import { MinWageCard } from '@/modules/labour/MinWageCard';
import { OmanisationCard } from '@/modules/labour/OmanisationCard';
import { ScoreTable, type ScoreRow } from '@/modules/fiscal/GulfScorecard';
import { PopulationExplorer } from '@/modules/oman/PopulationExplorer';
import { PopulationShares, WorkforceShares } from './PopulationDerived';

/** الصورة العامة: كم عدد السكان وكيف يتركّبون؟ وما نصيب الوافدين من السكان والعاملين؟ */
export function PopulationOverviewTab() {
  return (
    <>
      <Section id="composition" title="من هم سكان عُمان؟" lead="تركيبة السكان بين المواطنين وغير المواطنين والذكور والإناث (المركز الإحصائي الخليجي)، وحصة الوافدين من السكان ومن العاملين.">
        <SeriesChart wide kind="stacked" title="السكان: مواطنون وغير مواطنين" subtitle="نسمة — المركز الإحصائي الخليجي" lines={[{ indicator: 'gccstat.POP_CITIZEN', entity: 'OMN', label: 'مواطنون', color: 'var(--series-1)' }, { indicator: 'gccstat.POP_NON_CITIZEN', entity: 'OMN', label: 'غير مواطنين', color: 'var(--series-2)' }]} />
        <PopulationShares />
        <WorkforceShares />
      </Section>
      <Section id="explorer" title="مستكشف السكان" lead="اختر الجنسية والجنس والفترة: أعداد ونسب شهرية من المركز الوطني للإحصاء، والمواليد والوفيات.">
        <PopulationExplorer />
      </Section>

    </>
  );
}

/** التعمين والعاملون: من يعمل في الحكومة والقطاع الخاص، ومن العُمانيون بين العاملين؟ */
export function LabourWorkforceTab() {
  return (
    <>
      <Section title="تركيبة العاملين">
        <OmanisationCard />
        <SeriesChart
          wide
          title="العاملون حسب الجنسية"
          kind="stacked"
          lines={[
            { indicator: 'ilo.emp_citizen', entity: 'OMN', label: 'العُمانيون', color: 'var(--series-1)' },
            { indicator: 'ilo.emp_noncitizen', entity: 'OMN', label: 'غير العُمانيين', color: 'var(--series-2)' },
          ]}
        />
      </Section>
      <Section title="العاملون المسجَّلون حسب القطاع والجنسية" lead="سجلات التأمينات الاجتماعية ووزارة العمل عبر المركز الوطني للإحصاء، حتى 2025. سلاسل العُمانيين من 2020 فقط: قبلها تغطية أضيق فلا تُوصل بها.">
        <OmanisationCard
          title="التعمين في القطاع الخاص"
          subtitle="% من العاملين في القطاع الخاص"
          omani="ncsi.LAB_PRIVATE_OMANI"
          expat="ncsi.LAB_PRIVATE_EXPAT"
          of="من العاملين في القطاع الخاص؛ والباقي وافدون"
          note="العاملون المسجَّلون في التأمينات الاجتماعية، القطاع الخاص."
        />
        <SeriesChart
          wide
          title="العاملون حسب القطاع والجنسية"
          kind="stacked"
          lines={[
            { indicator: 'ncsi.LAB_PRIVATE_OMANI', entity: 'OMN', label: 'عُمانيون — خاص', color: 'var(--series-3)' },
            { indicator: 'ncsi.LAB_PRIVATE_EXPAT', entity: 'OMN', label: 'وافدون — خاص', color: 'var(--series-2)' },
            { indicator: 'ncsi.LAB_PUBLIC_OMANI', entity: 'OMN', label: 'عُمانيون — حكومي', color: 'var(--series-1)' },
            { indicator: 'ncsi.LAB_PUBLIC_EXPAT', entity: 'OMN', label: 'وافدون — حكومي', color: 'var(--series-4)' },
          ]}
          from="2020"
        />
        <SeriesChart wide title="العُمانيات العاملات" lines={[{ indicator: 'ncsi.LAB_FEMALE_OMANI', entity: 'OMN', label: 'عاملات عُمانيات' }]} />
        <SeriesChart wide title="إجمالي العاملين المسجَّلين" lines={[{ indicator: 'ncsi.LAB_WORKERS_TOTAL', entity: 'OMN', label: 'إجمالي العاملين' }]} />
      </Section>
      <Section title="العُمانيون وغير العُمانيين" lead="أحدث مقارنة منشورة بين الفئتين (قد تسبق السنة الجارية بسنوات).">
        <ReadingGrid>
          <KeyStat entity="OMN" indicator="ilo.lfp_citizen" label="مشاركة العُمانيين في القوى العاملة" />
          <KeyStat entity="OMN" indicator="ilo.lfp_noncitizen" label="مشاركة غير العُمانيين في القوى العاملة" />
          <KeyStat entity="OMN" indicator="ilo.unemployment_citizen" label="بطالة العُمانيين" goodWhen="down" />
        </ReadingGrid>
      </Section>
    </>
  );
}

/** البطالة والمشاركة: بطالة الشباب والمرأة والمواطنين ومقارنة المواطنين بغيرهم. */
export function LabourUnemploymentTab() {
  return (
    <>
      <Section title="مواطنون ووافدون: الأسكوا (بوابة التنمية العربية)" lead="مقارنة مؤشرات القوى العاملة بين المواطنين وغير المواطنين لعُمان والدول العربية، من إحصاءات الأسكوا.">
        <SeriesChart wide title="معدل البطالة (15+): المواطنون وغير المواطنين" lines={[{ indicator: 'adp.labour.unemployment-rate-both-sexes-15-years-nationals-d4f103', entity: 'OMN', label: 'المواطنون' }, { indicator: 'adp.labour.unemployment-rate-both-sexes-15-years-non-nationals-852fec', entity: 'OMN', label: 'غير المواطنين' }]} />
        <SeriesChart wide title="معدل المشاركة (15+): المواطنون وغير المواطنين" lines={[{ indicator: 'adp.labour.labor-force-participation-rate-both-sexes-15-years-nationals-09c1df', entity: 'OMN', label: 'المواطنون' }, { indicator: 'adp.labour.labor-force-participation-rate-both-sexes-15-years-non-nationals-0350b5', entity: 'OMN', label: 'غير المواطنين' }]} />
        <SeriesChart wide title="نسبة التوظيف إلى السكان (15+)" lines={[{ indicator: 'adp.labour.employment-to-population-ratio-both-sexes-15-years-nationals-368245', entity: 'OMN', label: 'المواطنون' }, { indicator: 'adp.labour.employment-to-population-ratio-both-sexes-15-years-non-nationals-59fbce', entity: 'OMN', label: 'غير المواطنين' }]} />
        <SeriesChart wide title="توزّع توظيف المواطنين حسب القطاع (%)" lines={[{ indicator: 'adp.labour.employment-by-sector-percent-both-sexes-nationals-public-c3a264', entity: 'OMN', label: 'حكومي' }, { indicator: 'adp.labour.employment-by-sector-percent-both-sexes-nationals-private-f5253d', entity: 'OMN', label: 'خاص' }, { indicator: 'adp.labour.employment-by-sector-percent-both-sexes-nationals-mixed-1106a7', entity: 'OMN', label: 'مختلط' }]} />
      </Section>
    </>
  );
}

/** الأجور وحصة العمل: الحد الأدنى للأجور وكم يذهب من الناتج إلى العاملين. */
export function LabourWagesTab() {
  return (
    <>
      <Section title="الأجور وحصة العمل" lead="الحد الأدنى الرسمي للأجور (منظمة العمل الدولية) وحصة العمل من الناتج: كم يذهب من الناتج إلى أجور العاملين؟">
        <div style={{ gridColumn: '1 / -1' }}>
          <MinWageCard />
        </div>
        <SeriesChart wide title="الحد الأدنى الشهري للأجور في عُمان" subtitle="ريال عُماني" lines={[{ indicator: 'ilo.min_wage', entity: 'OMN', label: 'الحد الأدنى' }]} />
        <SeriesChart wide title="حصة العمل من الناتج المحلي" subtitle="% — تقديرات منظمة العمل" lines={gccLines('ilo.labour_income_share')} from="2004" />
      </Section>
    </>
  );
}

const ROWS: ScoreRow[] = [
  { key: 'ilo.unemployment', label: 'البطالة (%)', better: 'low' },
  { key: 'ilo.unemployment_youth', label: 'بطالة الشباب 15–24 (%)', better: 'low' },
  { key: 'ilo.lfp_total', label: 'المشاركة في القوى العاملة (%)', better: 'high' },
  { key: 'ilo.lfp_female', label: 'مشاركة المرأة (%)', better: 'high' },
  { key: 'ilo.labour_income_share', label: 'حصة العمل من الناتج (%)', better: 'high' },
  { key: 'ilo.neet', label: 'الشباب خارج العمل والتعليم (%)', better: 'low' },
];

/** الخليج: بطاقة أداء سوق العمل ثم المسارات التاريخية. */
export function LabourGulfTab() {
  return (
    <>
      <Section id="scorecard" title="أين نقف بين الجيران؟" lead="آخر قيمة متاحة لكل دولة؛ الأخضر الأفضل بين الدول الست.">
        <ScoreTable rows={ROWS} title="بطاقة أداء سوق العمل الخليجية" subtitle="آخر قيمة فعلية — منظمة العمل الدولية" note="الأخضر الأفضل بين الدول الست في كل صف (البطالة والشباب خارج العمل الأدنى أفضل، والمشاركة وحصة العمل الأعلى أفضل). بعض السنوات تسبق الحالية بسنوات في تقديرات المنظمة." />
      </Section>
      <Section title="مقارنة خليجية">
        <SeriesChart wide title="معدل البطالة" lines={gccLines('ilo.unemployment')} from="2005" />
        <SeriesChart wide title="بطالة الشباب (15–24)" lines={gccLines('ilo.unemployment_youth')} from="2005" />
        <SeriesChart wide title="الشباب خارج العمل والتعليم والتدريب" lines={gccLines('ilo.neet')} from="2005" />
        <SeriesChart wide title="مشاركة المرأة في القوى العاملة" lines={gccLines('ilo.lfp_female')} from="2005" />
        <SeriesChart wide title="معدل المشاركة في القوى العاملة" lines={gccLines('ilo.lfp_total')} from="2005" />
        <SeriesChart wide title="معدل البطالة (المركز الإحصائي الخليجي)" lines={gccLines('gccstat.UNEMPLOYMENT')} />
      </Section>
    </>
  );
}
