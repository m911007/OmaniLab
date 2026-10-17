import { Link } from 'react-router-dom';
import { INDICES } from '@/modules/indices/data';
import { omanRankOf, standingPct, totalOf } from '@/modules/indices/types';

/** أقوى المؤشرات الدولية وأضعفها لعُمان، مأخوذة من سجل المؤشرات العالمية. */
export function GlobalIndicesSummary() {
  const ranked = INDICES.filter((i) => standingPct(i) != null).sort((a, b) => (standingPct(b) ?? 0) - (standingPct(a) ?? 0));
  const best = ranked.slice(0, 5);
  const worst = ranked.slice(-5).reverse();
  const row = (i: (typeof ranked)[number]) => (
    <li key={i.slug}>
      <Link to={`/indices/${i.slug}`}>{i.ar}</Link>
      <span className="muted">
        {' '}
        — <span className="num">{omanRankOf(i)}</span> من <span className="num">{totalOf(i)}</span> ({i.year})
      </span>
    </li>
  );
  return (
    <div className="grid cols-2">
      <div className="card">
        <h3>أقوى المؤشرات الدولية</h3>
        <ul className="plain-list">{best.map(row)}</ul>
      </div>
      <div className="card">
        <h3>أضعف المؤشرات الدولية</h3>
        <ul className="plain-list">{worst.map(row)}</ul>
      </div>
    </div>
  );
}
