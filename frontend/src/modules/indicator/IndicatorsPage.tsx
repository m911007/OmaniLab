import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { Loading } from '@/core/components/States';
import { formatNumber, formatPeriod } from '@/core/format';
import { useIndicatorCatalog } from '@/core/hooks';

const FREQ: Record<string, string> = { A: 'سنوي', Q: 'فصلي', M: 'شهري', D: 'يومي' };

/** قاموس المؤشرات: كل مؤشر له بيانات، قابل للبحث والتصفية حسب القسم، ومنه إلى صفحة المؤشر. */
export default function IndicatorsPage() {
  const [params, setParams] = useSearchParams();
  const q = params.get('q') ?? '';
  const section = params.get('section') ?? '';
  const omn = params.get('omn') !== '0';
  const page = Number(params.get('page') ?? 1);
  const [draft, setDraft] = useState(q);
  const { data, isLoading, isFetching } = useIndicatorCatalog({ q, section, omn, page });

  useEffect(() => {
    const t = setTimeout(() => {
      if (draft !== q) setParams((p) => { p.set('q', draft); p.delete('page'); return p; }, { replace: true });
    }, 300);
    return () => clearTimeout(t);
  }, [draft, q, setParams]);

  const set = (k: string, v: string | null) => setParams((p) => { if (v) p.set(k, v); else p.delete(k); if (k !== 'page') p.delete('page'); return p; }, { replace: true });

  return (
    <>
      <PageHead title="قاموس المؤشرات" lead="كل المؤشرات التي تجمعها المنصة آليًا من مصادرها الرسمية. ابحث بالعربية أو الإنجليزية، وافتح أي مؤشر لترى خلاصته ورسمه وترتيبه وما يرتبط به ومصدره." />
      <div className="controls catalog-filters">
        <input type="search" className="catalog-search" placeholder="ابحث: التضخم، الدين، inflation…" value={draft} onChange={(e) => setDraft(e.target.value)} aria-label="بحث في المؤشرات" />
        <select value={section} onChange={(e) => set('section', e.target.value || null)} aria-label="القسم">
          <option value="">كل الأقسام</option>
          {data?.meta.sections.map((s) => <option key={s} value={s}>{s}</option>)}
        </select>
        <label className="catalog-check"><input type="checkbox" checked={omn} onChange={(e) => set('omn', e.target.checked ? null : '0')} /> لعُمان بيانات فقط</label>
        {data && <span className="muted">{formatNumber(data.meta.total, 0)} مؤشرًا{isFetching ? ' …' : ''}</span>}
      </div>

      {isLoading || !data ? <Loading height={400} /> : (
        <>
          <div className="table-scroll">
            <table className="wide catalog-table">
              <thead><tr><th>المؤشر</th><th>الوحدة</th><th>التكرار</th><th>آخر فترة</th><th>الكيانات</th><th>المصدر</th></tr></thead>
              <tbody>
                {data.data.map((i) => (
                  <tr key={i.key}>
                    <td><Link to={`/indicator/${i.key}`}>{i.name_ar}</Link><small className="muted" dir="ltr">{i.key}</small></td>
                    <td>{i.unit ?? '—'}</td>
                    <td>{FREQ[i.frequency] ?? i.frequency}</td>
                    <td className="num">{formatPeriod(i.last_period)}</td>
                    <td className="num">{i.entities}</td>
                    <td><small>{i.dataset.source}</small></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          {data.meta.last_page > 1 && (
            <nav className="controls catalog-pages" aria-label="الصفحات">
              <button type="button" className="btn" disabled={page <= 1} onClick={() => set('page', String(page - 1))}>السابق</button>
              <span className="num">{page} / {data.meta.last_page}</span>
              <button type="button" className="btn" disabled={page >= data.meta.last_page} onClick={() => set('page', String(page + 1))}>التالي</button>
            </nav>
          )}
        </>
      )}
    </>
  );
}
