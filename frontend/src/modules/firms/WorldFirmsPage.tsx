import { useState } from 'react';
import { Link } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { SourceNote } from '@/core/components/SourceNote';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { compact, formatNumber } from '@/core/format';
import { useFirmStats, useFirms } from '@/core/hooks';
import type { FirmRow, Money } from '@/core/api';

export const moneyText = (m?: Money): string => (m ? `${compact(m.amount)} ${m.currency ?? ''}${m.year ? ` (${m.year})` : ''}` : '—');

export function FirmLogo({ f }: { f: FirmRow }) {
  return f.logo ? <img className="firm-logo" src={f.logo} alt="" loading="lazy" referrerPolicy="no-referrer" /> : <span className="firm-logo firm-logo-empty" aria-hidden="true">{(f.name_en || '?').slice(0, 1)}</span>;
}

export function FirmTable({ rows }: { rows: FirmRow[] }) {
  return (
    <div className="table-scroll">
      <table className="wide">
        <thead>
          <tr><th>الشركة</th><th>الدولة</th><th>القطاع</th><th>العاملون</th><th>الإيرادات</th><th>القيمة السوقية</th><th>تأسست</th><th>البورصة</th></tr>
        </thead>
        <tbody>
          {rows.map((f) => (
            <tr key={f.code}>
              <td>
                <Link to={`/world-companies/${f.code}`} className="firm-name">
                  <FirmLogo f={f} />
                  <span>{f.name_ar ?? f.name_en}{f.name_ar && f.name_en !== f.name_ar && <span className="muted" dir="ltr"> {f.name_en}</span>}</span>
                </Link>
              </td>
              <td>{f.country ? <Link to={`/world/markets/${f.country}`}>{f.country}</Link> : '—'}</td>
              <td>{(f.industry_ar?.length ? f.industry_ar : f.industry)?.slice(0, 2).join('، ') ?? '—'}</td>
              <td className="num">{f.employees != null ? formatNumber(f.employees, 0) : '—'}</td>
              <td className="num">{moneyText(f.revenue)}</td>
              <td className="num">{moneyText(f.market_cap)}</td>
              <td className="num">{f.inception ?? '—'}</td>
              <td>{f.ticker ? <span dir="ltr">{f.ticker}</span> : '—'}{f.exchange?.[0] ? <div className="muted">{f.exchange[0]}</div> : null}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default function WorldFirmsPage() {
  const stats = useFirmStats();
  const [q, setQ] = useState('');
  const [country, setCountry] = useState('');
  const [industry, setIndustry] = useState('');
  const [listed, setListed] = useState(false);
  const [sort, setSort] = useState('employees');
  const [page, setPage] = useState(1);
  const firms = useFirms({ q: q || undefined, country: country || undefined, industry: industry || undefined, listed: listed ? '1' : undefined, sort, page });
  const pages = Math.max(1, Math.ceil((firms.data?.total ?? 0) / (firms.data?.per_page ?? 25)));
  const reset = () => setPage(1);

  return (
    <>
      <PageHead title="الشركات العالمية" lead="دليل لأكبر عدد ممكن من شركات العالم بتفاصيلها: الدولة والقطاع وعدد العاملين والإيرادات والقيمة السوقية والبورصة والرمز والرئيس التنفيذي والشركة الأم. المصدر ويكيداتا (CC0) ويُوسَّع على دفعات حتى تغطي كل الدول." />

      {stats.data && (
        <div className="grid cols-4 insight-tiles">
          <div className="card tile"><span className="stat-label">الشركات الموثّقة</span><span className="stat-value num">{formatNumber(stats.data.total, 0)}</span><span className="stat-meta">تتزايد مع كل دفعة جلب</span></div>
          <div className="card tile"><span className="stat-label">الدول المغطّاة</span><span className="stat-value num">{formatNumber(stats.data.countries.length, 0)}</span><span className="stat-meta">من نحو 200</span></div>
          <div className="card tile"><span className="stat-label">أكبر الدول عددًا</span><span className="stat-value" style={{ fontSize: 18 }}>{stats.data.countries.slice(0, 3).map((c) => c.name_ar ?? c.name_en).join('، ') || '—'}</span></div>
          <div className="card tile"><span className="stat-label">أكثر القطاعات</span><span className="stat-value" style={{ fontSize: 18 }}>{stats.data.industries.slice(0, 2).map((i) => i.name).join('، ') || '—'}</span></div>
        </div>
      )}

      <section className="section card">
        <div className="card-head" style={{ flexWrap: 'wrap', gap: 10 }}>
          <h2>الدليل ({formatNumber(firms.data?.total ?? 0, 0)})</h2>
          <div className="no-print" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <input type="search" placeholder="ابحث باسم الشركة…" value={q} onChange={(e) => { setQ(e.target.value); reset(); }} aria-label="بحث" />
            <select value={country} onChange={(e) => { setCountry(e.target.value); reset(); }} aria-label="الدولة">
              <option value="">كل الدول</option>
              {stats.data?.countries.map((c) => <option key={c.code} value={c.code}>{c.name_ar ?? c.name_en} ({c.count})</option>)}
            </select>
            <select value={industry} onChange={(e) => { setIndustry(e.target.value); reset(); }} aria-label="القطاع">
              <option value="">كل القطاعات</option>
              {stats.data?.industries.map((i) => <option key={i.name} value={i.name}>{i.name} ({i.count})</option>)}
            </select>
            <select value={sort} onChange={(e) => { setSort(e.target.value); reset(); }} aria-label="الترتيب">
              <option value="employees">الأكثر عاملين</option>
              <option value="market_cap">الأعلى قيمة سوقية</option>
              <option value="inception">الأقدم تأسيسًا</option>
              <option value="name">الاسم</option>
            </select>
            <button className="btn" aria-pressed={listed} onClick={() => { setListed(!listed); reset(); }}>المدرجة فقط</button>
          </div>
        </div>
        {firms.isLoading && <Loading height={240} />}
        {firms.error && <ErrorState error={firms.error} />}
        {firms.data && (firms.data.data.length === 0 ? <Empty /> : <FirmTable rows={firms.data.data} />)}
        <div className="pager no-print">
          <button className="btn" disabled={page <= 1} onClick={() => setPage(page - 1)}>السابق</button>
          <span className="muted">صفحة <span className="num">{page}</span> من <span className="num">{pages}</span></span>
          <button className="btn" disabled={page >= pages} onClick={() => setPage(page + 1)}>التالي</button>
        </div>
        {firms.data?.dataset && <SourceNote datasets={[firms.data.dataset]} note="مصدر تطوعي: الأرقام كما أدخلها المحرّرون بعملاتها المختلفة (لا تُجمع)، وقد تتأخر أو تنقص." />}
      </section>
    </>
  );
}
