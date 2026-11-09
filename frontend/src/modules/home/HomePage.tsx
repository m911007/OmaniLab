import { ReadingGrid } from '@/core/components/ReadingGrid';
import { Link } from 'react-router-dom';
import { ReadingCard } from '@/core/components/ReadingCard';
import { Loading } from '@/core/components/States';
import { PageHead, Section } from '@/core/components/PageHead';
import { OilFiscalSensitivity } from '@/modules/commodities/OilFiscalSensitivity';
import { ForecastStrip } from './ForecastStrip';
import { ScorecardHighlights } from './ScorecardHighlights';
import { formatNumber, relativeTime } from '@/core/format';
import { useDatasets, useOverview, useReadings } from '@/core/hooks';
import { HeadlineBrief } from '@/modules/insights/HeadlineBrief';
import { OilPulse } from './OilPulse';
import { SignalsPanel } from './SignalsPanel';

/** صفحات المواضيع: من الخلاصة إلى التفاصيل (كانت بوابة «الاقتصاد العُماني في لمحة»). */
const TOPICS: { to: string; title: string; lead: string }[] = [
  { to: '/growth', title: 'النمو والناتج', lead: 'حجم الاقتصاد ونموه وهيكله، وتحليل أثر النفط والسكان.' },
  { to: '/prices-living', title: 'الأسعار والمعيشة', lead: 'التضخم وبنوده والقوة الشرائية.' },
  { to: '/fiscal', title: 'المالية العامة', lead: 'الإيرادات والإنفاق والعجز والدين.' },
  { to: '/money', title: 'النقد والمصارف', lead: 'السيولة والائتمان والفائدة والاحتياطيات والبنوك.' },
  { to: '/external', title: 'القطاع الخارجي', lead: 'ميزان المدفوعات والاستثمار الدولي والاحتياطيات.' },
  { to: '/energy', title: 'الطاقة والنفط', lead: 'سعر النفط العُماني وإنتاجه والغاز.' },
  { to: '/population', title: 'السكان والعمل', lead: 'السكان والتعمين والبطالة والأجور.' },
  { to: '/investment', title: 'الاستثمار', lead: 'الاستثمار الأجنبي والمحلي.' },
];

/** قراءات مؤشرات النبض (config/indicator_meta.php → pulse). */
function PulseGrid() {
  const { data, isLoading, isError } = useReadings({ set: 'pulse' });
  if (isLoading) return <Loading height={260} />;
  if (isError || !data) return <p className="muted">تعذّر تحميل القراءات.</p>;
  return <ReadingGrid>{data.map((r) => <ReadingCard key={r.key} reading={r} />)}</ReadingGrid>;
}

export default function HomePage() {
  const { data: overview } = useOverview();
  const { data: datasets } = useDatasets();
  const sources = new Set(datasets?.map((d) => d.source.key)).size;

  return (
    <>
      <PageHead title="الموجز التنفيذي" lead="ما يحتاجه صانع القرار في دقائق: ما الذي تغيّر، وكيف حال أهم المؤشرات مقارنةً بتاريخها وبالخليج، وأين نتقدم وأين نتأخر، وماذا بعد. كل رقم بمصدره، ويتحدث آليًا." />

      <nav className="brief-toc" aria-label="أقسام الموجز">
        {[['pulse', 'نبض الاقتصاد'], ['changed', 'ما الذي تغيّر؟'], ['standing', 'أين نتقدم وأين نتأخر'], ['oil', 'حساسية النفط'], ['next', 'ماذا بعد؟'], ['brief', 'الموجز المكتوب'], ['topics', 'المواضيع']].map(([id, label]) => (
          <a key={id} href={`#${id}`} className="chip neutral">{label}</a>
        ))}
      </nav>

      <section className="section" id="pulse">
        <h2>نبض الاقتصاد</h2>
        <p className="lead">كل مؤشر بحكم مبني على مرجع صريح: موقعه بين قراءات آخر عشر سنوات وترتيبه بين دول الخليج. افتح «ما الذي يحرّكه» في أي بطاقة لرؤية الصورة الأوسع.</p>
        <PulseGrid />
      </section>

      <section className="section" id="changed">
        <h2>ما الذي تغيّر؟ إشارات السوق</h2>
        <p className="lead">تغيّرات في أحدث البيانات المعتمدة تجاوزت عتبات معلنة. اضغط «؟» على أي إشارة لترى ماذا حدث ولماذا يهم وماذا نراقب بعدها.</p>
        <SignalsPanel showEmpty />
      </section>

      <section className="section" id="standing">
        <h2>أين نتقدم وأين نتأخر</h2>
        <ScorecardHighlights />
      </section>

      <Section id="oil" title="حساسية الميزانية لسعر النفط" lead="النفط ما زال المحرك الأول لإيرادات الدولة: كم يتحسن رصيد الميزانية مع كل ارتفاع في سعر البرميل؟ وللأثر عبر الإيرادات والدين والاحتياطيات افتح «سلاسل الأثر».">
        <OilFiscalSensitivity title="رصيد الميزانية وسعر النفط" indicator="imf.GGXCNL_NGDP" yLabel="رصيد الميزانية (% من الناتج)" />
        <OilPulse />
      </Section>

      <section className="section" id="next">
        <h2>ماذا بعد؟</h2>
        <ForecastStrip />
        <p style={{ marginTop: 8 }}><Link to="/impact">سيناريوهات: ماذا لو تغيّر النفط أو الفائدة الأمريكية أو أسعار الغذاء؟ ←</Link></p>
      </section>

      <section className="section" id="brief">
        <h2>الموجز المكتوب</h2>
        <HeadlineBrief />
      </section>

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
      <section className="section" id="topics">
        <h2>المواضيع</h2>
        <p className="lead">لكل موضوع صفحة تنزل فيها من الخلاصة إلى الرسوم والتحليل والعلاقات.</p>
        <ReadingGrid className="topic-grid">
          {TOPICS.map((t) => (
            <Link key={t.to} to={t.to} className="card topic-card">
              <strong>{t.title}</strong>
              <span className="muted">{t.lead}</span>
              <span className="topic-go" aria-hidden="true">←</span>
            </Link>
          ))}
        </ReadingGrid>
      </section>

    </>
  );
}
