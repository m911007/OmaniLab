import { useEffect, useState } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { PageHead } from '@/core/components/PageHead';
import { RankedBars } from '@/core/components/RankedBars';
import { SeriesChart } from '@/core/components/SeriesChart';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { SourceNote } from '@/core/components/SourceNote';
import { formatDate, formatNumber } from '@/core/format';
import { useCompanies, useCompany, useCompanyStats, type CompanyQuery } from '@/core/hooks';
import { StartupsSection } from './StartupsSection';
import { ATTRIBUTE_LABELS, CATEGORY_AR, REGISTRATION_AR, STATUS_AR, label } from './labels';
import { StatLabel } from '@/core/components/IndicatorIcon';

export default function CompaniesPage() {
  const stats = useCompanyStats();
  const [query, setQuery] = useState<CompanyQuery>({ sort: 'name', page: 1 });
  const [text, setText] = useState('');
  const [selected, setSelected] = useState<string | null>(null);

  // بحث مؤجَّل: لا طلب مع كل ضغطة مفتاح
  useEffect(() => {
    const t = setTimeout(() => setQuery((q) => ({ ...q, q: text.trim() || undefined, page: 1 })), 300);
    return () => clearTimeout(t);
  }, [text]);

  const list = useCompanies(query);
  const set = (patch: Partial<CompanyQuery>) => setQuery((q) => ({ ...q, ...patch, page: 1 }));
  const active = stats.data?.by_status.find((s) => s.label === 'ACTIVE')?.count ?? 0;
  const muscat = stats.data?.by_governorate.find((g) => g.label === 'مسقط')?.count ?? 0;
  const pages = list.data ? Math.max(1, Math.ceil(list.data.total / list.data.per_page)) : 1;

  return (
    <>
      <PageHead title="الشركات العُمانية" lead="الكيانات القانونية العُمانية المسجَّلة في السجل العالمي لمعرّف الكيان القانوني (LEI). طبقة أولى للاستخبارات التجارية تتوسع بمصادر أخرى لاحقًا." />

      {stats.data && (
        <div className="grid cols-4">
          <div className="card stat"><StatLabel>الكيانات المسجَّلة</StatLabel><span className="stat-value num">{formatNumber(stats.data.total, 0)}</span></div>
          <div className="card stat"><StatLabel>النشطة</StatLabel><span className="stat-value num">{formatNumber(active, 0)}</span><span className="stat-meta">{formatNumber((active / Math.max(stats.data.total, 1)) * 100, 0)}% من الإجمالي</span></div>
          <div className="card stat"><StatLabel>في محافظة مسقط</StatLabel><span className="stat-value num">{formatNumber(muscat, 0)}</span><span className="stat-meta">من الكيانات التي تحدد محافظتها</span></div>
          <div className="card stat"><StatLabel>آخر تحديث للمصدر</StatLabel><span className="stat-value" style={{ fontSize: 18 }}>{formatDate(stats.data.dataset?.freshness.source_published_at)}</span></div>
        </div>
      )}

      <section className="section">
        <div className="grid cols-2">
          <SeriesChart title="تأسيس الكيانات بحسب السنة" subtitle="حاملو LEI فقط، لا كل الشركات" kind="columns" last={30} lines={[{ indicator: 'gleif.OM_LEI_CREATED', entity: 'OMN', label: 'كيانات مؤسَّسة' }]} />
          <ChartCard title="التوزيع بحسب المحافظة" subtitle="للكيانات التي حددت منطقتها" datasets={stats.data?.dataset ? [stats.data.dataset] : []}>
            {stats.isLoading ? <Loading height={240} /> : (
              <RankedBars items={(stats.data?.by_governorate ?? []).map((g, i) => ({ key: g.label, rank: i + 1, label: g.label, value: g.count, color: g.label === 'مسقط' ? 'var(--series-1)' : undefined, muted: g.label !== 'مسقط' }))} />
            )}
          </ChartCard>
        </div>
      </section>

      <StartupsSection />

      <section className="section">
        <h2>دليل الشركات</h2>
        <div className="card" style={{ marginTop: 12 }}>
          <div className="controls">
            <label className="field">
              بحث
              <input type="search" value={text} onChange={(e) => setText(e.target.value)} placeholder="اسم الشركة (عربي أو إنجليزي)" style={{ minWidth: 240 }} aria-label="بحث في الشركات" />
            </label>
            <label className="field">
              الحالة
              <select value={query.status ?? ''} onChange={(e) => set({ status: e.target.value || undefined })}>
                <option value="">الكل</option>
                {stats.data?.by_status.filter((s) => s.label).map((s) => <option key={s.label} value={s.label}>{label(STATUS_AR, s.label)} ({s.count})</option>)}
              </select>
            </label>
            <label className="field">
              المحافظة
              <select value={query.governorate ?? ''} onChange={(e) => set({ governorate: e.target.value || undefined })}>
                <option value="">الكل</option>
                {stats.data?.by_governorate.map((g) => <option key={g.label} value={g.label}>{g.label} ({g.count})</option>)}
              </select>
            </label>
            <label className="field">
              الفئة
              <select value={query.category ?? ''} onChange={(e) => set({ category: e.target.value || undefined })}>
                <option value="">الكل</option>
                {stats.data?.by_category.map((c) => <option key={c.label} value={c.label}>{label(CATEGORY_AR, c.label)} ({c.count})</option>)}
              </select>
            </label>
            <label className="field">
              الترتيب
              <select value={query.sort} onChange={(e) => set({ sort: e.target.value as 'name' | 'created' })}>
                <option value="name">الاسم</option>
                <option value="created">الأحدث تأسيسًا</option>
              </select>
            </label>
          </div>

          {list.isLoading ? <Loading height={300} /> : list.error ? <ErrorState error={list.error as Error} /> : list.data && list.data.data.length === 0 ? <Empty>لا شركات تطابق هذا البحث.</Empty> : (
            <div className="table-scroll">
              <table className="wide" aria-label="الشركات">
                <thead>
                  <tr>
                    <th>الشركة</th>
                    <th>الحالة</th>
                    <th>الفئة</th>
                    <th>المدينة</th>
                    <th>تاريخ التأسيس</th>
                    <th>السجل التجاري</th>
                  </tr>
                </thead>
                <tbody>
                  {list.data?.data.map((c) => (
                    <tr key={c.code} onClick={() => setSelected(c.code)} style={{ cursor: 'pointer' }}>
                      <td>
                        <button className="linklike" onClick={() => setSelected(c.code)} style={{ background: 'none', border: 0, padding: 0, textAlign: 'start', color: 'var(--accent)', fontWeight: 700 }}>
                          {c.name_ar ?? c.name_en}
                        </button>
                        {c.name_ar && <div className="muted" style={{ fontSize: 12, direction: 'ltr', textAlign: 'start' }}>{c.name_en}</div>}
                      </td>
                      <td><span className={`chip ${c.status === 'ACTIVE' ? 'good' : 'neutral'}`}>{label(STATUS_AR, c.status)}</span></td>
                      <td>{label(CATEGORY_AR, c.category)}</td>
                      <td>{c.governorate ?? c.city ?? '—'}</td>
                      <td className="num">{c.creation_date ?? '—'}</td>
                      <td className="num">{c.registered_as ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {list.data && (
            <div className="controls" style={{ marginTop: 12, justifyContent: 'space-between' }}>
              <span className="muted">
                <span className="num">{formatNumber(list.data.total, 0)}</span> شركة · صفحة <span className="num">{list.data.page}</span> من <span className="num">{pages}</span>
              </span>
              <span style={{ display: 'inline-flex', gap: 8 }}>
                <button className="btn" disabled={(query.page ?? 1) <= 1} onClick={() => setQuery((q) => ({ ...q, page: (q.page ?? 1) - 1 }))}>السابق</button>
                <button className="btn" disabled={(query.page ?? 1) >= pages} onClick={() => setQuery((q) => ({ ...q, page: (q.page ?? 1) + 1 }))}>التالي</button>
              </span>
            </div>
          )}
          {list.data?.dataset && <SourceNote datasets={[list.data.dataset]} note="حاملو LEI فقط؛ ليسوا كل الشركات المسجلة في سلطنة عُمان." />}
        </div>
      </section>

      {selected && <CompanyDrawer code={selected} onClose={() => setSelected(null)} />}
    </>
  );
}

function CompanyDrawer({ code, onClose }: { code: string; onClose: () => void }) {
  const { data, isLoading, error } = useCompany(code);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  const fmt = (key: string, v: string | string[] | number | null): string => {
    if (Array.isArray(v)) return v.join('، ');
    if (v === null) return '—';
    const s = String(v);
    if (key === 'status') return label(STATUS_AR, s);
    if (key === 'category') return label(CATEGORY_AR, s);
    if (key === 'registration_status') return label(REGISTRATION_AR, s);
    return s;
  };

  return (
    <div role="dialog" aria-modal="true" aria-label="ملف الشركة" style={{ position: 'fixed', inset: 0, zIndex: 40, display: 'flex', justifyContent: 'flex-start' }}>
      <div onClick={onClose} style={{ position: 'absolute', inset: 0, background: 'rgb(0 0 0 / 0.4)' }} />
      <div className="card" style={{ position: 'relative', width: 'min(520px, 100%)', height: '100%', overflowY: 'auto', borderRadius: 0 }}>
        <div className="card-head">
          <h2>{data ? (data.name_ar ?? data.name_en) : 'ملف الشركة'}</h2>
          <button className="btn" onClick={onClose} aria-label="إغلاق">✕</button>
        </div>
        {isLoading ? <Loading height={200} /> : error ? <ErrorState error={error as Error} /> : data && (
          <>
            {data.name_ar && <p className="muted" style={{ direction: 'ltr', textAlign: 'start' }}>{data.name_en}</p>}
            <p className="muted" style={{ marginTop: 6 }}>معرّف LEI: <span className="num">{data.code}</span></p>
            <table style={{ marginTop: 12 }}>
              <tbody>
                {Object.entries(data.attributes).map(([k, v]) => (
                  <tr key={k}>
                    <th style={{ width: '40%' }}>{ATTRIBUTE_LABELS[k] ?? k}</th>
                    <td>{fmt(k, v)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
            {data.dataset && <SourceNote datasets={[data.dataset]} />}
          </>
        )}
      </div>
    </div>
  );
}
