import { useMemo, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { FreshnessBadge } from '@/core/components/FreshnessBadge';
import { PageHead } from '@/core/components/PageHead';
import { ReadingCard } from '@/core/components/ReadingCard';
import { RelatedPanel } from '@/core/components/RelatedPanel';
import { Segmented } from '@/core/components/Segmented';
import { SeriesChart } from '@/core/components/SeriesChart';
import { Empty, Loading } from '@/core/components/States';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { entityName, formatPeriod } from '@/core/format';
import { gccLines, peerLines } from '@/core/groups';
import { useIndicator, useReadings } from '@/core/hooks';
import { lastActualYear } from '@/core/lines';
import { relatedTo } from '@/core/relations';

type Compare = 'one' | 'gcc' | 'peers';

const FREQ: Record<string, string> = { A: 'سنوي', Q: 'فصلي', M: 'شهري', D: 'يومي' };

/**
 * صفحة المؤشر الواحد (البحث العميق): الخلاصة ← الرسم الكامل مع المقارنة ← الترتيب بين الدول ← ما يحرّكه وما يتأثر به ← المصدر والمنهجية.
 * تعمل لأي مؤشر من القاموس؛ الحكم يظهر فقط حيث تُعرَّف قطبيته.
 */
export default function IndicatorPage() {
  const { key = '' } = useParams();
  const [params, setParams] = useSearchParams();
  const { data: ind, isLoading, isError } = useIndicator(key);
  const entity = params.get('entity') ?? ind?.default_entity ?? 'OMN';
  const [compare, setCompare] = useState<Compare>('one');
  const readings = useReadings({ indicators: ind ? [key] : [], entity });
  const rel = relatedTo(key);
  const gccAvailable = useMemo(() => (ind ? ind.entities.filter((e) => ['SAU', 'ARE', 'QAT', 'KWT', 'BHR'].includes(e.code)).length >= 2 : false), [ind]);
  const peersAvailable = useMemo(() => (ind ? ind.entities.filter((e) => ['NOR', 'NZL'].includes(e.code)).length >= 1 : false), [ind]);

  if (isLoading) return <Loading height={400} />;
  if (isError || !ind) return <Empty>لم يُعثر على هذا المؤشر. <Link to="/indicators">ابحث في قاموس المؤشرات</Link></Empty>;

  const isImf = ind.key.startsWith('imf.');
  const lines =
    compare === 'gcc' ? gccLines(ind.key) : compare === 'peers' ? peerLines(ind.key) : [{ indicator: ind.key, entity, label: entityName(ind.entities.find((e) => e.code === entity) ?? { name_ar: entity, name_en: entity }) }];
  const reading = readings.data?.[0];

  return (
    <>
      <nav className="breadcrumb" aria-label="مسار التنقل">
        <Link to="/">الموجز</Link> <span aria-hidden="true">›</span> <Link to="/indicators">قاموس المؤشرات</Link> <span aria-hidden="true">›</span> <span aria-current="page">{ind.name_ar}</span>
      </nav>
      <PageHead title={ind.name_ar} lead={ind.description ?? `${ind.dataset.name_ar} — ${ind.dataset.source.name_ar}`}>
        <p className="muted indicator-meta">
          <span>{ind.name_en}</span> · <span>{ind.unit ?? 'بلا وحدة'}</span> · <span>{FREQ[ind.frequency] ?? ind.frequency}</span> · <span>{ind.countries} دولة</span>
          {ind.coverage.last_period && <> · <span>آخر فترة {formatPeriod(ind.coverage.last_period)}</span></>}
        </p>
      </PageHead>

      {ind.entities.length > 1 && (
        <div className="controls">
          <label className="indicator-entity">
            <span>الكيان</span>
            <select value={entity} onChange={(e) => setParams({ entity: e.target.value }, { replace: true })}>
              {ind.entities.map((e) => <option key={e.code} value={e.code}>{entityName(e)}</option>)}
            </select>
          </label>
          {(gccAvailable || peersAvailable) && (
            <Segmented label="المقارنة" value={compare} onChange={setCompare} options={[{ value: 'one', label: 'وحده' }, ...(gccAvailable ? [{ value: 'gcc' as const, label: 'مع الخليج' }] : []), ...(peersAvailable ? [{ value: 'peers' as const, label: 'مع دول المقارنة' }] : [])]} />
          )}
        </div>
      )}

      <section className="section" aria-label="الخلاصة">
        <h2>الخلاصة</h2>
        {readings.isLoading ? <Loading height={200} /> : reading ? <div className="indicator-reading"><ReadingCard reading={reading} variant="full" /></div> : <p className="muted">لا قراءة متاحة لهذا الكيان.</p>}
      </section>

      <section className="section" aria-label="الرسم">
        <h2>عبر الزمن</h2>
        <div className="grid cols-2" style={{ marginTop: 12 }}>
          <SeriesChart wide title={ind.name_ar} subtitle={ind.unit ?? undefined} lines={lines} imfForecast={isImf} />
          {ind.countries >= 8 && <WorldRankCard title={`ترتيب الدول: ${ind.name_ar}`} indicator={ind.key} highlight={entity} limit={10} maxPeriod={isImf ? lastActualYear() : undefined} />}
        </div>
      </section>

      {rel.drivers.length + rel.effects.length > 0 && (
        <section className="section" aria-label="المؤشرات المرتبطة">
          <h2>ما الذي يحرّكه وماذا يتأثر به؟</h2>
          <p className="lead">لاتخاذ القرار تلزم الصورة الأوسع: أسباب حركة المؤشر، وما سيتأثر به بعده، وما يرجّحه آخر تغيّر في محرّكاته.</p>
          <RelatedPanel indicator={ind.key} />
        </section>
      )}

      <section className="section" aria-label="المصدر والمنهجية">
        <h2>المصدر والمنهجية</h2>
        <dl className="card indicator-source">
          <div><dt>المصدر</dt><dd>{ind.dataset.source.url ? <a href={ind.dataset.source.url} target="_blank" rel="noreferrer">{ind.dataset.source.name_ar}</a> : ind.dataset.source.name_ar}</dd></div>
          <div><dt>مجموعة البيانات</dt><dd>{ind.dataset.name_ar}</dd></div>
          <div><dt>المفتاح</dt><dd><code dir="ltr">{ind.key}</code></dd></div>
          <div><dt>الترخيص</dt><dd>{ind.dataset.source.license ?? '—'}</dd></div>
          <div><dt>الحداثة</dt><dd><FreshnessBadge dataset={ind.dataset} /></dd></div>
          <div><dt>الموثوقية</dt><dd>{ind.dataset.reliability}</dd></div>
        </dl>
        <p className="source-note">تُجلب البيانات آليًا من المصدر وتمر بقواعد تدقيق (المدى، والقفزات، وانقطاع الوحدة) قبل نشرها؛ التفاصيل وسجل التشغيل في <Link to="/status">حالة البيانات</Link> و<Link to="/methodology">المنهجية</Link>. جدول البيانات وتنزيلها CSV متاحان من زر الجدول في الرسم.</p>
      </section>
    </>
  );
}
