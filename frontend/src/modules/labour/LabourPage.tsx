import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines } from '@/core/groups';
import { OmanisationCard } from './OmanisationCard';

export default function LabourPage() {
  return (
    <>
      <PageHead
        title="سوق العمل والتعمين"
        lead="من يعمل في الاقتصاد العُماني؟ تركيبة العاملين بين العُمانيين والوافدين، والبطالة والمشاركة والشباب والمرأة، ومقارنتها بدول الخليج. المصدر الأساسي منظمة العمل الدولية والمركز الإحصائي الخليجي."
      />

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="ilo.emp_citizen" label="العاملون العُمانيون" compactValue />
        <KeyStat entity="OMN" indicator="ilo.emp_noncitizen" label="العاملون غير العُمانيين" compactValue />
        <KeyStat entity="OMN" indicator="ilo.unemployment" label="معدل البطالة" goodWhen="down" />
        <KeyStat entity="OMN" indicator="ilo.unemployment_youth" label="بطالة الشباب (15–24)" goodWhen="down" />
      </div>

      <Section title="تركيبة العاملين">
        <OmanisationCard />
        <SeriesChart
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
        <SeriesChart title="العُمانيات العاملات" lines={[{ indicator: 'ncsi.LAB_FEMALE_OMANI', entity: 'OMN', label: 'عاملات عُمانيات' }]} />
        <SeriesChart title="إجمالي العاملين المسجَّلين" lines={[{ indicator: 'ncsi.LAB_WORKERS_TOTAL', entity: 'OMN', label: 'إجمالي العاملين' }]} />
      </Section>

      <Section title="العُمانيون وغير العُمانيين" lead="أحدث مقارنة منشورة بين الفئتين (قد تسبق السنة الجارية بسنوات).">
        <div className="grid cols-3" style={{ gridColumn: '1 / -1' }}>
          <KeyStat entity="OMN" indicator="ilo.lfp_citizen" label="مشاركة العُمانيين في القوى العاملة" />
          <KeyStat entity="OMN" indicator="ilo.lfp_noncitizen" label="مشاركة غير العُمانيين في القوى العاملة" />
          <KeyStat entity="OMN" indicator="ilo.unemployment_citizen" label="بطالة العُمانيين" goodWhen="down" />
        </div>
      </Section>

      <Section title="مقارنة خليجية">
        <SeriesChart title="معدل البطالة" lines={gccLines('ilo.unemployment')} from="2005" />
        <SeriesChart title="بطالة الشباب (15–24)" lines={gccLines('ilo.unemployment_youth')} from="2005" />
        <SeriesChart title="الشباب خارج العمل والتعليم والتدريب" lines={gccLines('ilo.neet')} from="2005" />
        <SeriesChart title="مشاركة المرأة في القوى العاملة" lines={gccLines('ilo.lfp_female')} from="2005" />
        <SeriesChart title="معدل المشاركة في القوى العاملة" lines={gccLines('ilo.lfp_total')} from="2005" />
        <SeriesChart title="معدل البطالة (المركز الإحصائي الخليجي)" lines={gccLines('gccstat.UNEMPLOYMENT')} />
      </Section>
    </>
  );
}
