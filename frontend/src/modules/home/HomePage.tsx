import { PageHead, Section } from '@/core/components/PageHead';
import { KeyStat } from '@/core/components/KeyStat';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gcc, lastActualYear } from '@/core/lines';
import { Link } from 'react-router-dom';
import { formatNumber, relativeTime } from '@/core/format';
import { useOverview } from '@/core/hooks';

const IMF_ACTUAL = lastActualYear();

export default function HomePage() {
  const { data: overview } = useOverview();

  return (
    <>
      <PageHead title="لوحة القيادة" lead="أهم مؤشرات الاقتصاد العُماني الآن. كل رقم يحمل مصدره وتاريخ آخر فحص وآخر تغيير، وتتحدث البيانات آليًا من المصادر الرسمية." />

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="imf.NGDPD" label="الناتج المحلي الإجمالي" maxPeriod={IMF_ACTUAL} />
        <KeyStat entity="OMN" indicator="imf.NGDP_RPCH" label="نمو الناتج الحقيقي" maxPeriod={IMF_ACTUAL} goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.CPI_INFLATION" label="التضخم السنوي (شهري)" />
        <KeyStat entity="OMN" indicator="ncsi.OIL_PRICE" label="سعر النفط العُماني (شهري)" />
        <KeyStat entity="OMN" indicator="imf.GGXWDG_NGDP" label="الدين الحكومي الإجمالي" maxPeriod={IMF_ACTUAL} goodWhen="down" />
        <KeyStat entity="OMN" indicator="gccstat.UNEMPLOYMENT" label="معدل البطالة" goodWhen="down" />
        <KeyStat entity="OMN" indicator="gccstat.POP" label="عدد السكان" compactValue />
        <KeyStat entity="OMN" indicator="manafeth.EXPORTS" label="الصادرات السلعية السنوية" compactValue />
      </div>

      <Section title="اتجاهات رئيسية">
        <SeriesChart title="نمو الناتج الحقيقي: عُمان مقابل دول الخليج" lines={gcc('imf.NGDP_RPCH')} from="2012" imfForecast />
        <SeriesChart title="التضخم السنوي في عُمان" subtitle="شهريًا" lines={[{ indicator: 'ncsi.CPI_INFLATION', entity: 'OMN', label: 'التضخم السنوي' }]} from="2021" />
        <SeriesChart title="سعر النفط العُماني" subtitle="متوسط شهري" lines={[{ indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'دولار/برميل' }]} from="2018" />
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
              <strong className="num">{formatNumber(overview.counts.datasets, 0)}</strong> مجموعة بيانات من 5 مصادر رسمية،{' '}
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
