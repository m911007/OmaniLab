import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { Loading } from '@/core/components/States';
import { INDICES, loadRanking } from './data';
import { GCC_NAMES, gccPosition, omanRankOf, standingPct, TIER_META, tierOf, totalOf, type RankingRow } from './types';
import { StatLabel } from '@/core/components/IndicatorIcon';

const PAGE = 20;
const GCC_EN = /saudi|emirates|qatar|kuwait|bahrain|^oman$/i;
const GCC_AR = /السعودية|الإمارات|الامارات|قطر|الكويت|البحرين|^(عمان|عُمان|سلطنة عُمان)$/;
const isOman = (r: RankingRow) => /^(oman|sultanate of oman)$/i.test(r.country) || /^(عمان|عُمان|سلطنة عُمان|سلطنة عمان)$/.test(r.countryAr);
const isGcc = (r: RankingRow) => GCC_EN.test(r.country) || GCC_AR.test(r.countryAr);

export default function IndexDetailPage() {
  const { slug = '' } = useParams();
  const info = INDICES.find((i) => i.slug === slug);
  const [rows, setRows] = useState<RankingRow[] | null>(null);
  const [q, setQ] = useState('');
  const [gccOnly, setGccOnly] = useState(false);
  const [page, setPage] = useState(0);

  useEffect(() => {
    setRows(null);
    setPage(0);
    setQ('');
    loadRanking(slug).then(setRows);
  }, [slug]);

  const filtered = useMemo(() => {
    const term = q.trim().toLowerCase();
    return (rows ?? []).filter((r) => (!gccOnly || isGcc(r)) && (!term || `${r.country} ${r.countryAr}`.toLowerCase().includes(term)));
  }, [rows, q, gccOnly]);

  if (!info) {
    return (
      <>
        <PageHead title="المؤشر غير موجود" />
        <Link to="/indices">← العودة إلى المؤشرات العالمية</Link>
      </>
    );
  }

  const rank = omanRankOf(info);
  const total = totalOf(info);
  const pct = standingPct(info);
  const meta = TIER_META[tierOf(info)];
  const gcc = gccPosition(info);
  const pages = Math.max(1, Math.ceil(filtered.length / PAGE));
  const shown = filtered.slice(page * PAGE, page * PAGE + PAGE);
  const jumpToOman = () => {
    const idx = filtered.findIndex(isOman);
    if (idx >= 0) {
      setPage(Math.floor(idx / PAGE));
    }
  };

  return (
    <>
      <PageHead title={info.ar} lead={`${info.en} · ${info.org} · إصدار ${info.year}`} />
      <p className="no-print"><Link to="/indices">← كل المؤشرات العالمية</Link></p>

      <div className="grid cols-4 insight-tiles">
        <div className="card tile"><StatLabel>ترتيب عُمان</StatLabel><span className="stat-value num">{rank ?? '—'}</span><span className="stat-meta">{total ? <>من <span className="num">{total}</span> دولة</> : 'غير مرتّبة'}</span></div>
        <div className="card tile"><StatLabel>الدرجة</StatLabel><span className="stat-value num">{info.score ?? (info.omanScore ?? '—')}</span><span className="stat-meta">{info.scaleMax ? <>من <span className="num">{info.scaleMax}</span></> : ' '}</span></div>
        <div className="card tile"><StatLabel>الموقع الخليجي</StatLabel><span className="stat-value num">{gcc ? gcc.rank : '—'}</span><span className="stat-meta">{gcc ? <>من <span className="num">{gcc.of}</span> دول خليجية</> : 'غير متاح'}</span></div>
        <div className="card tile"><StatLabel>التصنيف</StatLabel><span className="stat-value"><span className={`chip ${meta.chip}`}>{meta.icon} {meta.label}</span></span><span className="stat-meta">{pct != null ? <>تتفوق على <span className="num">{pct}%</span> من الدول</> : ' '}</span></div>
      </div>

      {info.trend && <p className="lead" style={{ marginTop: 14 }}>{info.trendDir === 'up' ? '↗' : info.trendDir === 'down' ? '↘' : '→'} {info.trend}</p>}
      <p>{info.note}</p>

      <section className="section card">
        <h2>على ماذا يستند المؤشر؟</h2>
        <p>{info.methodology}</p>
        <p className="source-note"><a href={info.url} target="_blank" rel="noreferrer noopener">المصدر الرسمي ↗</a></p>
      </section>

      {info.gcc.length > 1 && (
        <section className="section card">
          <h2>عُمان والخليج في هذا المؤشر</h2>
          <ol className="gcc-rank">
            {[...info.gcc].sort((a, b) => a.rank - b.rank).map((g) => (
              <li key={g.code} className={g.code === 'OMN' ? 'me' : ''}>
                <span>{GCC_NAMES[g.code]}</span>
                <span className="num">{g.rank}</span>
                {g.score != null && <span className="muted num">{g.score}</span>}
              </li>
            ))}
          </ol>
        </section>
      )}

      {info.pillars && info.pillars.length > 0 && (
        <section className="section">
          <h2>تفاصيل التقييم</h2>
          <div className="grid cols-2">
            {info.pillars.map((p) => (
              <div key={p.name} className="card">
                <strong>{p.name}</strong>
                {p.score != null && <div className="stat-value num">{p.score}</div>}
                {p.note && <p className="muted">{p.note}</p>}
              </div>
            ))}
          </div>
        </section>
      )}

      <section className="section card">
        <div className="card-head" style={{ flexWrap: 'wrap', gap: 10 }}>
          <h2>ترتيب الدول{info.rankingYear ? ` · ${info.rankingYear}` : ''}</h2>
          {rows && rows.length > 0 && (
            <div className="no-print" style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              <input className="search" type="search" placeholder="ابحث عن دولة…" value={q} onChange={(e) => { setQ(e.target.value); setPage(0); }} aria-label="بحث عن دولة" />
              <button className="btn" aria-pressed={gccOnly} onClick={() => { setGccOnly(!gccOnly); setPage(0); }}>{gccOnly ? 'كل الدول' : 'دول الخليج فقط'}</button>
              {!gccOnly && !q && <button className="btn" onClick={jumpToOman}>الانتقال إلى عُمان</button>}
            </div>
          )}
        </div>
        {rows === null && <Loading height={200} />}
        {rows && rows.length === 0 && <p className="muted">الجدول الكامل غير متوافر آليًا لهذا المؤشر؛ راجع المصدر الرسمي.</p>}
        {rows && rows.length > 0 && (
          <>
            <div className="table-scroll">
              <table className="data-table">
                <thead><tr><th>الترتيب</th><th>الدولة</th><th>الدرجة</th></tr></thead>
                <tbody>
                  {shown.map((r) => (
                    <tr key={`${r.rank}-${r.country}`} className={isOman(r) ? 'row-oman' : isGcc(r) ? 'row-gcc' : ''}>
                      <td className="num">{r.rank}</td>
                      <td>{r.countryAr || r.country}</td>
                      <td className="num">{r.score ?? '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="pager no-print">
              <button className="btn" disabled={page === 0} onClick={() => setPage(page - 1)}>السابق</button>
              <span className="muted">صفحة <span className="num">{page + 1}</span> من <span className="num">{pages}</span></span>
              <button className="btn" disabled={page >= pages - 1} onClick={() => setPage(page + 1)}>التالي</button>
            </div>
          </>
        )}
        {info.rankingNote && <p className="source-note">{info.rankingNote}</p>}
      </section>
    </>
  );
}
