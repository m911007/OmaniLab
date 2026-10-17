import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { compact, entityName, formatNumber } from '@/core/format';
import { useCountryDirectory } from '@/core/hooks';
import type { DirectoryRow } from '@/core/api';
import { flagOf, INCOME_AR, incomeLabel, REGION_AR, regionLabel } from './labels';

type Col = 'name' | 'gdp' | 'gdp_pc' | 'population' | 'growth' | 'inflation' | 'debt' | 'trade';

const val = (r: DirectoryRow, k: string): number | null => r.values[k]?.value ?? null;
const tradeWithOman = (r: DirectoryRow): number | null => {
  const a = val(r, 'oman_exports_to');
  const b = val(r, 'oman_imports_from');
  return a == null && b == null ? null : (a ?? 0) + (b ?? 0);
};
const sortValue = (r: DirectoryRow, c: Col): number | string | null => (c === 'name' ? (r.name_ar ?? r.name_en) : c === 'trade' ? tradeWithOman(r) : val(r, c));

function Cell({ v, kind }: { v: number | null; kind: 'money' | 'pct' | 'int' }) {
  if (v == null) {
    return <td className="num muted">—</td>;
  }
  return <td className="num">{kind === 'money' ? `$${compact(v)}` : kind === 'pct' ? `${formatNumber(v, 1)}%` : compact(v)}</td>;
}

function TopList({ title, rows, metric, fmt }: { title: string; rows: DirectoryRow[]; metric: string; fmt: (v: number) => string }) {
  const top = rows.filter((r) => val(r, metric) != null).sort((a, b) => (val(b, metric) ?? 0) - (val(a, metric) ?? 0)).slice(0, 5);
  return (
    <div className="card">
      <h3>{title}</h3>
      <ol className="plain-list">
        {top.map((r) => (
          <li key={r.code}>
            <Link to={`/markets/${r.code}`}>{flagOf(r.iso2)} {entityName(r)}</Link> <span className="muted num">— {fmt(val(r, metric)!)}</span>
          </li>
        ))}
      </ol>
    </div>
  );
}

export default function CountriesPage() {
  const { data, isLoading, error } = useCountryDirectory();
  const [q, setQ] = useState('');
  const [region, setRegion] = useState('');
  const [income, setIncome] = useState('');
  const [sort, setSort] = useState<{ col: Col; dir: 1 | -1 }>({ col: 'gdp', dir: -1 });

  const rows = useMemo(() => data?.data ?? [], [data]);
  const oman = rows.find((r) => r.code === 'OMN');
  const shown = useMemo(() => {
    const term = q.trim().toLowerCase();
    return rows
      .filter((r) => (!region || regionLabel(r.region) === region) && (!income || incomeLabel(r.income_level) === income) && (!term || `${r.name_ar ?? ''} ${r.name_en} ${r.capital ?? ''}`.toLowerCase().includes(term)))
      .sort((a, b) => {
        const x = sortValue(a, sort.col);
        const y = sortValue(b, sort.col);
        if (x == null && y == null) return 0;
        if (x == null) return 1;
        if (y == null) return -1;
        return (typeof x === 'string' ? x.localeCompare(y as string, 'ar') : x - (y as number)) * sort.dir;
      });
  }, [rows, q, region, income, sort]);

  const world = useMemo(() => ({ gdp: rows.reduce((s, r) => s + (val(r, 'gdp') ?? 0), 0), pop: rows.reduce((s, r) => s + (val(r, 'population') ?? 0), 0) }), [rows]);
  const omanRank = (m: string) => (oman && val(oman, m) != null ? rows.filter((r) => (val(r, m) ?? -Infinity) > val(oman, m)!).length + 1 : null);
  const head = (c: Col, label: string) => (
    <th aria-sort={sort.col === c ? (sort.dir === 1 ? 'ascending' : 'descending') : 'none'}>
      <button className="th-sort" onClick={() => setSort({ col: c, dir: sort.col === c ? (sort.dir === 1 ? -1 : 1) : c === 'name' ? 1 : -1 })}>
        {label} {sort.col === c ? (sort.dir === 1 ? '▲' : '▼') : ''}
      </button>
    </th>
  );

  const csv = () => {
    const header = ['الدولة', 'الإقليم', 'مستوى الدخل', 'الناتج (دولار)', 'نصيب الفرد', 'السكان', 'النمو %', 'التضخم %', 'الدين %', 'تجارة عُمان (دولار)'];
    const lines = shown.map((r) => [r.name_ar ?? r.name_en, regionLabel(r.region), incomeLabel(r.income_level), val(r, 'gdp'), val(r, 'gdp_pc'), val(r, 'population'), val(r, 'growth'), val(r, 'inflation'), val(r, 'debt'), tradeWithOman(r)].map((c) => `"${String(c ?? '').replace(/"/g, '""')}"`).join(','));
    const blob = new Blob([`﻿${[header.join(','), ...lines].join('\n')}`], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'دول-العالم.csv';
    a.click();
  };

  return (
    <>
      <PageHead title="دول العالم" lead="دليل اقتصادي لكل دولة ومنطقة: الحجم والدخل والسكان والنمو والتضخم والدين وحجم التجارة مع عُمان. رتّب بأي عمود وافتح ملف الدولة الكامل. المصادر: البنك الدولي وصندوق النقد ومنافذ." />

      {isLoading && <Loading height={300} />}
      {error && <ErrorState error={error} />}

      {data && (
        <>
          <div className="grid cols-4 insight-tiles">
            <div className="card tile"><span className="stat-label">الدول والمناطق</span><span className="stat-value num">{formatNumber(rows.length, 0)}</span><span className="stat-meta">لها بيانات اقتصادية</span></div>
            <div className="card tile"><span className="stat-label">الناتج العالمي</span><span className="stat-value num">${compact(world.gdp)}</span><span className="stat-meta">مجموع الدول بالدولار الجاري</span></div>
            <div className="card tile"><span className="stat-label">سكان العالم</span><span className="stat-value num">{compact(world.pop)}</span><span className="stat-meta">مجموع الدول</span></div>
            <div className="card tile">
              <span className="stat-label">موقع عُمان</span>
              <span className="stat-value num">{omanRank('gdp') ?? '—'}</span>
              <span className="stat-meta">في حجم الاقتصاد · {omanRank('gdp_pc') ?? '—'} في نصيب الفرد · {omanRank('population') ?? '—'} في السكان</span>
            </div>
          </div>

          <div className="grid cols-3" style={{ marginTop: 14 }}>
            <TopList title="أكبر الاقتصادات" rows={rows} metric="gdp" fmt={(v) => `$${compact(v)}`} />
            <TopList title="الأعلى دخلًا للفرد" rows={rows} metric="gdp_pc" fmt={(v) => `$${compact(v)}`} />
            <TopList title="الأكثر سكانًا" rows={rows} metric="population" fmt={(v) => compact(v)} />
          </div>

          <section className="section card">
            <div className="card-head" style={{ flexWrap: 'wrap', gap: 10 }}>
              <h2>كل الدول ({formatNumber(shown.length, 0)})</h2>
              <div className="no-print" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                <input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="ابحث باسم الدولة أو العاصمة…" aria-label="بحث" />
                <select value={region} onChange={(e) => setRegion(e.target.value)} aria-label="الإقليم">
                  <option value="">كل الأقاليم</option>
                  {REGION_AR.map(([, l]) => <option key={l}>{l}</option>)}
                </select>
                <select value={income} onChange={(e) => setIncome(e.target.value)} aria-label="مستوى الدخل">
                  <option value="">كل مستويات الدخل</option>
                  {INCOME_AR.map(([, l]) => <option key={l}>{l}</option>)}
                </select>
                <button className="btn" onClick={csv}>تنزيل CSV</button>
              </div>
            </div>
            {shown.length === 0 ? (
              <Empty />
            ) : (
              <div className="table-scroll">
                <table className="wide countries-table">
                  <thead>
                    <tr>
                      {head('name', 'الدولة')}
                      <th>الإقليم · الدخل</th>
                      {head('gdp', 'الناتج')}
                      {head('gdp_pc', 'نصيب الفرد')}
                      {head('population', 'السكان')}
                      {head('growth', 'النمو')}
                      {head('inflation', 'التضخم')}
                      {head('debt', 'الدين')}
                      {head('trade', 'تجارة عُمان معها')}
                    </tr>
                  </thead>
                  <tbody>
                    {shown.map((r) => (
                      <tr key={r.code} className={r.code === 'OMN' ? 'row-oman' : ''}>
                        <td>
                          <Link to={`/markets/${r.code}`}>
                            <span aria-hidden="true">{flagOf(r.iso2)}</span> {entityName(r)}
                          </Link>
                          {r.capital && <div className="muted">{r.capital}</div>}
                        </td>
                        <td><span className="muted">{regionLabel(r.region)}</span><div>{incomeLabel(r.income_level)}</div></td>
                        <Cell v={val(r, 'gdp')} kind="money" />
                        <Cell v={val(r, 'gdp_pc')} kind="money" />
                        <Cell v={val(r, 'population')} kind="int" />
                        <Cell v={val(r, 'growth')} kind="pct" />
                        <Cell v={val(r, 'inflation')} kind="pct" />
                        <Cell v={val(r, 'debt')} kind="pct" />
                        <Cell v={tradeWithOman(r)} kind="money" />
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
            <p className="source-note">آخر قيمة متاحة لكل مؤشر (قد تختلف سنتها بين الدول). النمو والدين من صندوق النقد للسنوات الفعلية فقط. تجارة عُمان من منافذ (آخر سنة).</p>
          </section>
        </>
      )}
    </>
  );
}
