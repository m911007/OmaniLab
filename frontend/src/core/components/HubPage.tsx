import { ReadingGrid } from './ReadingGrid';
import { Suspense, type ReactNode } from 'react';
import { Link, NavLink, useParams } from 'react-router-dom';
import { useReadings } from '../hooks';
import { EmbeddedContext, PageHead } from './PageHead';
import { ReadingCard } from './ReadingCard';
import { Loading } from './States';

export interface HubTab {
  slug: string;
  label: string;
  render: () => ReactNode;
}

/** صف بطاقات القراءة: الخلاصة قبل الرسوم في رأس كل صفحة. */
export function ReadingStrip({ keys }: { keys: readonly string[] }) {
  const { data, isLoading } = useReadings({ indicators: keys });
  if (isLoading) return <Loading height={200} />;
  if (!data?.length) return null;
  return <ReadingGrid className="hub-readings">{data.map((r) => <ReadingCard key={r.key} reading={r} />)}</ReadingGrid>;
}

/**
 * صفحة مجمِّعة: موضوع واحد بتبويبات، يُحمَّل كل تبويب عند فتحه، وتظهر صفحاته الداخلية دون عنوان مكرر (EmbeddedContext).
 * التبويب جزء من الرابط (/fiscal/revenue) فيمكن مشاركته، والخلاصة (بطاقات القراءة) أعلى الصفحة قبل التفاصيل.
 */
export function HubPage({ section, title, lead, base, tabs, readings, related, tab: forced }: { section: string; title: string; lead: string; base: string; tabs: readonly HubTab[]; readings?: readonly string[]; related?: readonly [string, string][]; tab?: string }) {
  const params = useParams();
  const slug = forced ?? params.tab;
  const current = tabs.find((t) => t.slug === slug) ?? tabs[0];

  return (
    <>
      <nav className="breadcrumb" aria-label="مسار التنقل">
        <Link to="/">الموجز</Link> <span aria-hidden="true">›</span> <span>{section}</span> <span aria-hidden="true">›</span>{' '}
        {tabs.length > 1 ? <Link to={base}>{title}</Link> : <span aria-current="page">{title}</span>}
        {tabs.length > 1 && <> <span aria-hidden="true">›</span> <span aria-current="page">{current.label}</span></>}
      </nav>
      <PageHead title={tabs.length > 1 ? `${title}: ${current.label}` : title} lead={lead} />
      {readings && readings.length > 0 && <ReadingStrip keys={readings} />}
      {tabs.length > 1 && (
        <nav className="hub-tabs" aria-label={`أقسام ${title}`}>
          {tabs.map((t, i) => (
            <NavLink key={t.slug} to={i === 0 ? base : `${base}/${t.slug}`} end className={() => `hub-tab${t === current ? ' active' : ''}`} aria-current={t === current ? 'page' : undefined}>
              {t.label}
            </NavLink>
          ))}
        </nav>
      )}
      <EmbeddedContext.Provider value={true}>
        <Suspense fallback={<Loading height={320} />}>
          <div key={current.slug}>{current.render()}</div>
        </Suspense>
      </EmbeddedContext.Provider>
      {related && related.length > 0 && (
        <nav className="related-pages" aria-label="صفحات ذات صلة">
          <h2>ذات صلة</h2>
          <div className="controls">
            {related.map(([to, label]) => <Link key={to} to={to} className="chip neutral">{label} ←</Link>)}
          </div>
        </nav>
      )}
    </>
  );
}
