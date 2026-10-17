import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { Empty } from '@/core/components/States';
import { INDEX_GROUPS, INDICES } from './data';
import { gccPosition, omanRankOf, standingPct, TIER_META, tierOf, totalOf, type IndexInfo, type Tier } from './types';

const CURRENT_YEAR = new Date().getFullYear();

export function IndexCard({ i }: { i: IndexInfo }) {
  const rank = omanRankOf(i);
  const total = totalOf(i);
  const pct = standingPct(i);
  const tier = tierOf(i);
  const meta = TIER_META[tier];
  const gcc = gccPosition(i);
  const stale = CURRENT_YEAR - i.year >= 3;

  return (
    <article className={`card gidx gidx-${tier}`}>
      <header>
        <span className={`chip ${meta.chip}`}>
          <span aria-hidden="true">{meta.icon}</span> {meta.label}
        </span>
        {i.badge && <span className="chip warning">{i.badge}</span>}
        {stale && (
          <span className="chip neutral" title="الإصدار الأحدث قد يكون صدر بعد هذه السنة">
            إصدار قديم
          </span>
        )}
      </header>
      <h3>{i.ar}</h3>
      <p className="muted gidx-en">{i.en}</p>

      <div className="gidx-rank">
        {rank != null ? (
          <>
            <span className="num gidx-big">{rank}</span>
            {total != null && (
              <span className="muted">
                من <span className="num">{total}</span>
              </span>
            )}
          </>
        ) : (
          <span className="muted">لا ترتيب عالميًا لعُمان</span>
        )}
        {i.score && (
          <span className="gidx-score">
            الدرجة <span className="num">{i.score}</span>
            {i.scaleMax && (
              <span className="muted">
                {' '}
                / <span className="num">{i.scaleMax}</span>
              </span>
            )}
          </span>
        )}
      </div>

      {pct != null && (
        <>
          <div className="gidx-bar" role="img" aria-label={`تتفوق عُمان على ${pct}% من الدول`}>
            <span style={{ width: `${Math.max(pct, 3)}%` }} />
          </div>
          <p className="stat-meta">
            تتفوق على <span className="num">{pct}%</span> من دول المؤشر{i.metric ? ` · ${i.metric}` : ''}
          </p>
        </>
      )}

      <ul className="gidx-facts">
        {gcc && (
          <li>
            خليجيًا: <strong className="num">{gcc.rank}</strong> من <span className="num">{gcc.of}</span>
          </li>
        )}
        {i.trend && (
          <li>
            <span aria-hidden="true">{i.trendDir === 'up' ? '↗' : i.trendDir === 'down' ? '↘' : '→'}</span> {i.trend}
          </li>
        )}
      </ul>
      <p className="gidx-note">{i.note}</p>

      <footer>
        <span className="muted">
          {i.org} · <span className="num">{i.year}</span>
        </span>
        <Link to={`/indices/${i.slug}`} className="gidx-more">
          التفاصيل والترتيب الكامل ←
        </Link>
      </footer>
    </article>
  );
}

export default function IndicesPage() {
  const [group, setGroup] = useState<string>('all');
  const [tier, setTier] = useState<Tier | 'all'>('all');
  const [q, setQ] = useState('');

  const ranked = INDICES.filter((i) => tierOf(i) !== 'none');
  const counts = { top: 0, mid: 0, low: 0 };
  ranked.forEach((i) => {
    counts[tierOf(i) as 'top' | 'mid' | 'low'] += 1;
  });
  const avg = ranked.length ? Math.round(ranked.reduce((s, i) => s + (standingPct(i) ?? 0), 0) / ranked.length) : 0;
  const gccLead = INDICES.map(gccPosition).filter((g) => g && g.rank <= 2).length;

  const items = useMemo(() => {
    const term = q.trim().toLowerCase();
    return INDICES.filter((i) => (group === 'all' || i.group === group) && (tier === 'all' || tierOf(i) === tier) && (!term || `${i.ar} ${i.en} ${i.org}`.toLowerCase().includes(term))).sort(
      (a, b) => (standingPct(b) ?? -1) - (standingPct(a) ?? -1),
    );
  }, [group, tier, q]);

  return (
    <>
      <PageHead
        title="المؤشرات العالمية"
        lead="أين تقف عُمان في أبرز المؤشرات الدولية؟ خمسة وعشرون مؤشرًا من الأمم المتحدة والبنك الدولي وWIPO وIMD وغيرها، لكل منها ترتيب عُمان وموقعها بين دول الخليج وجدول الدول كاملًا."
      />

      <div className="grid cols-4 insight-tiles">
        <button className="card tile tile-strength" aria-pressed={tier === 'top'} onClick={() => setTier(tier === 'top' ? 'all' : 'top')}>
          <span className="stat-label">ضمن الأفضل (أعلى 30%)</span>
          <span className="stat-value num">{counts.top}</span>
          <span className="stat-meta">من {ranked.length} مؤشرًا مرتّبًا · اضغط للتصفية</span>
        </button>
        <button className="card tile tile-watch" aria-pressed={tier === 'mid'} onClick={() => setTier(tier === 'mid' ? 'all' : 'mid')}>
          <span className="stat-label">وسط الترتيب</span>
          <span className="stat-value num">{counts.mid}</span>
          <span className="stat-meta">مجال واضح للتقدم</span>
        </button>
        <button className="card tile tile-challenge" aria-pressed={tier === 'low'} onClick={() => setTier(tier === 'low' ? 'all' : 'low')}>
          <span className="stat-label">متأخرة (أدنى 35%)</span>
          <span className="stat-value num">{counts.low}</span>
          <span className="stat-meta">أولويات للتحسين</span>
        </button>
        <div className="card tile">
          <span className="stat-label">متوسط موقع عُمان</span>
          <span className="stat-value num">{avg}%</span>
          <span className="stat-meta">
            تتفوق على {avg}% من الدول في المتوسط · أول أو ثاني خليجيًا في <span className="num">{gccLead}</span> مؤشرًا
          </span>
        </div>
      </div>

      <div className="card-head" style={{ marginTop: 18, flexWrap: 'wrap', gap: 10 }}>
        <select value={group} onChange={(e) => setGroup(e.target.value)} aria-label="المحور">
          <option value="all">كل المحاور</option>
          {INDEX_GROUPS.map((g) => (
            <option key={g.id} value={g.id}>
              {g.title}
            </option>
          ))}
        </select>
        <input type="search" placeholder="ابحث باسم المؤشر أو الجهة…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="بحث" />
      </div>
      {group !== 'all' && <p className="lead">{INDEX_GROUPS.find((g) => g.id === group)?.sub}</p>}

      {items.length === 0 ? (
        <Empty />
      ) : (
        <div className="grid cols-2 gidx-grid">
          {items.map((i) => (
            <IndexCard key={i.slug} i={i} />
          ))}
        </div>
      )}

      <section className="section card method">
        <h2>عن هذه المؤشرات</h2>
        <ul>
          <li>تُنشر المؤشرات مرة في السنة عادة؛ تُعرض سنة كل إصدار، وتُظهر البطاقة «إصدار قديم» إذا مضى عليه ثلاث سنوات أو أكثر.</li>
          <li>الموقع = نسبة الدول التي تتفوق عليها عُمان (100% = الأولى عالميًا). تختلف أعداد الدول ومنهجيات المؤشرات، فلا يُقارَن ترتيب بآخر مباشرة.</li>
          <li>المقارنة الخليجية محسوبة من جدول الترتيب الرسمي لكل مؤشر.</li>
          <li>بعض المؤشرات (مثل WJP) لا تشمل عُمان بعد، وبعضها (LPI وSDG وIDI) لم تتوافر قائمته الكاملة؛ يُذكر ذلك في صفحة المؤشر.</li>
        </ul>
      </section>
    </>
  );
}
