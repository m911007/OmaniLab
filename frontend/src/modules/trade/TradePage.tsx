import { ReadingGrid } from '@/core/components/ReadingGrid';
import { Empty, Loading } from '@/core/components/States';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { formatDate } from '@/core/format';
import { peerLines } from '@/core/groups';
import { useDatasets } from '@/core/hooks';
import { MonthlyPartners } from './MonthlyPartners';
import { PartnerBalance } from './PartnerBalance';
import { PartnersExplorer } from './PartnersExplorer';
import { ProductMix } from './ProductMix';
import { MONTHS, parseManafethVersion } from './manafeth';

export default function TradePage() {
  const { data: datasets, isLoading } = useDatasets();
  const totals = datasets?.find((d) => d.key === 'manafeth.totals');
  const partners = datasets?.find((d) => d.key === 'manafeth.partners');
  const v = parseManafethVersion(totals?.freshness.source_version);
  const ytdYear = v && v.month < 12 ? v.year : null;

  return (
    <>
      <PageHead title="التجارة الخارجية" lead="الإحصاءات الجمركية الرسمية لسلطنة عُمان من منافذ (المركز الوطني للإحصاء والمعلومات): الإجماليات، ووسائل النقل، والشركاء التجاريون." />

      {isLoading && <Loading height={200} />}

      <Section title="التجارة السلعية الشهرية (صندوق النقد)" lead="أحدث أرقام التجارة بأشهر: تلتقط صدمات الأسعار والممرات قبل نشر الحسابات السنوية. الصادرات فوب والواردات سيف بالدولار.">
        <SeriesChart oil
          title="صادرات وواردات عُمان السلعية الشهرية"
          lines={[
            { indicator: 'imf.imts_exports', entity: 'OMN', label: 'الصادرات' },
            { indicator: 'imf.imts_imports', entity: 'OMN', label: 'الواردات' },
          ]}
        />
        <SeriesChart oil title="الرصيد التجاري السلعي الشهري لعُمان" kind="columns" lines={[{ indicator: 'imf.imts_balance', entity: 'OMN', label: 'الرصيد' }]} last={36} />
        <MonthlyPartners />
        <SeriesChart oil title="الصادرات السلعية الشهرية: الخليج" lines={['OMN', 'SAU', 'ARE', 'QAT', 'KWT', 'BHR'].map((entity) => ({ indicator: 'imf.imts_exports', entity }))} />
        <SeriesChart title="الواردات السلعية الشهرية: الخليج" lines={['OMN', 'SAU', 'ARE', 'QAT', 'KWT', 'BHR'].map((entity) => ({ indicator: 'imf.imts_imports', entity }))} />
      </Section>

      {ytdYear && v && totals && (
        <section className="section" style={{ marginTop: 0 }}>
          <h2>السنة الجارية {ytdYear} — غير مكتملة</h2>
          <div className="callout" style={{ margin: '10px 0 14px' }}>
            الأرقام التالية تغطي من بداية {ytdYear} حتى <strong>{MONTHS[v.month - 1]} {v.year}</strong> فقط، وليست سنة كاملة. آخر تغيير في بياناتنا من المصدر: {formatDate(totals.freshness.last_changed_at)}.
          </div>
          <ReadingGrid>
            <KeyStat entity="OMN" indicator="manafeth.EXPORTS_YTD" label={`الصادرات منذ بداية ${ytdYear}`} compactValue />
            <KeyStat entity="OMN" indicator="manafeth.IMPORTS_YTD" label={`الواردات منذ بداية ${ytdYear}`} compactValue />
            <KeyStat entity="OMN" indicator="manafeth.REEXPORTS_YTD" label={`إعادة التصدير منذ بداية ${ytdYear}`} compactValue />
          </ReadingGrid>
        </section>
      )}

      <Section title="التعقيد الاقتصادي" lead="مؤشر ECI لا يقيس حجم الصادرات بل تنوعها وتعقيدها: الدول الأعلى أقدر على النمو والتنويع. (مرصد التعقيد الاقتصادي)">
        <SeriesChart title="مؤشر التعقيد الاقتصادي (ECI)" lines={peerLines('oec.eci')} from="2012" />
        <SeriesChart title="معدل التبادل التجاري للسلع" subtitle="ارتفاعه = تحسّن القوة الشرائية للصادرات مقابل الواردات" lines={peerLines('imf.ctot_net_exports')} from="2000" />
      </Section>

      <Section title="الإجماليات السنوية" lead="السنوات الكاملة فقط (بالريال العُماني). الصادرات دون إعادة التصدير.">
        <SeriesChart
          title="الصادرات والواردات وإعادة التصدير"
          kind="columns"
          lines={[
            { indicator: 'manafeth.EXPORTS', entity: 'OMN', label: 'الصادرات', color: 'var(--series-1)' },
            { indicator: 'manafeth.IMPORTS', entity: 'OMN', label: 'الواردات', color: 'var(--series-2)' },
            { indicator: 'manafeth.REEXPORTS', entity: 'OMN', label: 'إعادة التصدير', color: 'var(--series-3)' },
          ]}
          last={14}
        />
        <SeriesChart
          title="الواردات بحسب وسيلة النقل"
          kind="stacked"
          lines={[
            { indicator: 'manafeth.IMPORTS_SEA', entity: 'OMN', label: 'بحري', color: 'var(--series-1)' },
            { indicator: 'manafeth.IMPORTS_LAND', entity: 'OMN', label: 'بري', color: 'var(--series-2)' },
            { indicator: 'manafeth.IMPORTS_AIR', entity: 'OMN', label: 'جوي', color: 'var(--series-3)' },
          ]}
          last={14}
        />
        <SeriesChart
          title="الصادرات بحسب وسيلة النقل"
          kind="stacked"
          callout="لا يوزّع المصدر صادرات النفط الخام على منافذ أو وسائل نقل، فمجموع هذا المخطط أقل بكثير من إجمالي الصادرات الرسمي."
          lines={[
            { indicator: 'manafeth.EXPORTS_SEA', entity: 'OMN', label: 'بحري', color: 'var(--series-1)' },
            { indicator: 'manafeth.EXPORTS_LAND', entity: 'OMN', label: 'بري', color: 'var(--series-2)' },
            { indicator: 'manafeth.EXPORTS_AIR', entity: 'OMN', label: 'جوي', color: 'var(--series-3)' },
          ]}
          last={14}
        />
        <SeriesChart
          title="إعادة التصدير بحسب وسيلة النقل"
          kind="stacked"
          lines={[
            { indicator: 'manafeth.REEXPORTS_SEA', entity: 'OMN', label: 'بحري', color: 'var(--series-1)' },
            { indicator: 'manafeth.REEXPORTS_LAND', entity: 'OMN', label: 'بري', color: 'var(--series-2)' },
            { indicator: 'manafeth.REEXPORTS_AIR', entity: 'OMN', label: 'جوي', color: 'var(--series-3)' },
          ]}
          last={14}
        />
      </Section>

      <section className="section">
        <h2>تركيبة التجارة حسب المنتج</h2>
        <p className="lead">ماذا تبيع عُمان وماذا تشتري؟ وهل صادراتها متنوعة أم متركّزة؟ قارن بدول الخليج (الأمم المتحدة Comtrade).</p>
        <ProductMix />
      </section>

      <section className="section">
        <h2>الشركاء التجاريون</h2>
        <p className="lead">بحسب الدولة (ريال عُماني). مجموع الدول يطابق الإجمالي الرسمي بإضافة بند «غير محدد / غير موزّع» حين لا ينشر المصدر وجهة جزء من التدفق.</p>
        {partners ? <PartnersExplorer dataset={partners} /> : isLoading ? null : <Empty>بيانات الشركاء غير متاحة بعد.</Empty>}
        {partners && <div style={{ gridColumn: '1 / -1' }}><PartnerBalance year={String(v ? (v.month >= 12 ? v.year : v.year - 1) : new Date().getFullYear() - 1)} /></div>}
      </section>
    </>
  );
}
