import { Link } from 'react-router-dom';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { formatNumber, relativeTime } from '@/core/format';
import { useDatasets, useOverview } from '@/core/hooks';
import { gcc, lastActualYear } from '@/core/lines';
import { HeadlineBrief } from '@/modules/insights/HeadlineBrief';
import { OilPulse } from './OilPulse';
import { SignalsPanel } from './SignalsPanel';

const IMF_ACTUAL = lastActualYear();

export default function HomePage() {
  const { data: overview } = useOverview();
  const { data: datasets } = useDatasets();
  const sources = new Set(datasets?.map((d) => d.source.key)).size;

  return (
    <>
      <PageHead title="لوحة القيادة" lead="أهم مؤشرات الاقتصاد العُماني الآن، وما يستحق انتباه صانع القرار. كل رقم يحمل مصدره وتاريخ آخر فحص وآخر تغيير، وتتحدث البيانات آليًا من المصادر الرسمية." />

      <div className="grid cols-4">
        <KeyStat trend entity="OMN" indicator="imf.NGDPD" label="الناتج المحلي الإجمالي" maxPeriod={IMF_ACTUAL} goodWhen="up" />
        <KeyStat trend entity="OMN" indicator="imf.NGDP_RPCH" label="نمو الناتج الحقيقي" maxPeriod={IMF_ACTUAL} goodWhen="up" />
        <KeyStat trend entity="OMN" indicator="ncsi.CPI_INFLATION" label="التضخم السنوي (شهري)" />
        <KeyStat trend entity="WLD" indicator="imf.pcps.poildub" label="سعر خام دبي (شهري)" goodWhen="up" />
        <KeyStat trend entity="OMN" indicator="imf.GGXCNL_NGDP" label="رصيد الميزانية (% من الناتج)" maxPeriod={IMF_ACTUAL} goodWhen="up" />
        <KeyStat trend entity="OMN" indicator="imf.GGXWDG_NGDP" label="الدين الحكومي الإجمالي" maxPeriod={IMF_ACTUAL} goodWhen="down" />
        <KeyStat trend entity="OMN" indicator="ilo.unemployment" label="معدل البطالة" goodWhen="down" />
        <KeyStat trend entity="OMN" indicator="manafeth.EXPORTS" label="الصادرات السلعية السنوية" compactValue goodWhen="up" />
      </div>

      <div style={{ marginTop: 20, display: 'grid', gap: 16 }}>
        <SignalsPanel />
        <HeadlineBrief />
      </div>

      <Section title="النفط والمالية العامة" lead="النفط ما زال المحرك الأول لإيرادات الدولة؛ هكذا تتحرك الأسعار، وهكذا تتحرك الميزانية والدين معها.">
        <OilPulse />
        <SeriesChart oil
          title="الميزانية والدين الحكومي"
          subtitle="% من الناتج المحلي"
          lines={[
            { indicator: 'imf.GGXCNL_NGDP', entity: 'OMN', label: 'رصيد الميزانية', color: 'var(--series-1)' },
            { indicator: 'imf.GGXWDG_NGDP', entity: 'OMN', label: 'إجمالي الدين', color: 'var(--series-2)' },
          ]}
          from="2008"
          imfForecast
        />
      </Section>

      <Section title="اتجاهات رئيسية">
        <SeriesChart oil title="نمو الناتج الحقيقي: عُمان مقابل دول الخليج" lines={gcc('imf.NGDP_RPCH')} from="2012" imfForecast />
        <SeriesChart title="التضخم السنوي في عُمان" subtitle="شهريًا" lines={[{ indicator: 'ncsi.CPI_INFLATION', entity: 'OMN', label: 'التضخم السنوي' }]} from="2021" />
        <SeriesChart title="سعر النفط: دبي وبرنت" subtitle="دولار/برميل، متوسط شهري — صندوق النقد (ويقابله البنك الدولي وEIA)" lines={[{ indicator: 'imf.pcps.poildub', entity: 'WLD', label: 'خام دبي (مرجع الخام العُماني)' }, { indicator: 'imf.pcps.poilbre', entity: 'WLD', label: 'خام برنت' }]} from="2018" />
        <SeriesChart
          title="التجارة الخارجية السلعية"
          kind="columns"
          lines={[
            { indicator: 'manafeth.EXPORTS', entity: 'OMN', label: 'الصادرات', color: 'var(--series-1)' },
            { indicator: 'manafeth.IMPORTS', entity: 'OMN', label: 'الواردات', color: 'var(--series-2)' },
            { indicator: 'manafeth.REEXPORTS', entity: 'OMN', label: 'إعادة التصدير', color: 'var(--series-3)' },
          ]}
          last={12}
        />
      </Section>

      {overview && (
        <section className="section">
          <h2>صحة البيانات</h2>
          <div className="card" style={{ marginTop: 12 }}>
            <p>
              <strong className="num">{formatNumber(overview.counts.datasets, 0)}</strong> مجموعة بيانات من <strong className="num">{formatNumber(sources, 0)}</strong> مصدرًا رسميًا،{' '}
              <strong className="num">{formatNumber(overview.counts.indicators, 0)}</strong> مؤشرًا، و<strong className="num">{formatNumber(overview.counts.observations, 0)}</strong> قيمة
              لـ <strong className="num">{formatNumber(overview.counts.entities, 0)}</strong> كيانًا. آخر فحص {relativeTime(overview.last_checked_at)}، وآخر تغيير في البيانات {relativeTime(overview.last_changed_at)}.
            </p>
            <p style={{ marginTop: 8 }}>
              <Link to="/status">تفاصيل كل مجموعة، وسجل التشغيل، ونتائج التدقيق ←</Link>
            </p>
          </div>
        </section>
      )}
    </>
  );
}
