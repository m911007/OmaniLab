import { formatPeriod, formatValue } from '../format';
import type { SeriesItem } from '../hooks';
import { Empty } from './States';

/** ترتيب الكيانات بآخر قيمة متاحة لكل منها؛ يبرز الكيان الأساسي (عُمان) ويعرض فترة كل قيمة إن اختلفت. */
export function RankView({ series, unit, highlight }: { series: SeriesItem[]; unit?: string | null; highlight?: string }) {
  const rows = series
    .map((s) => { const last = s.points[s.points.length - 1]; return last ? { id: s.id, label: s.label, color: s.color, period: last[0], value: last[1] } : null; })
    .filter((r): r is NonNullable<typeof r> => r !== null)
    .sort((a, b) => b.value - a.value);
  if (rows.length === 0) return <Empty />;
  const max = Math.max(...rows.map((r) => Math.abs(r.value)), 1e-9);
  const latest = rows.reduce((m, r) => (r.period > m ? r.period : m), '');
  return (
    <div className="ranked" role="list" aria-label="ترتيب بآخر قيمة">
      {rows.map((r, i) => (
        <div key={r.id} role="listitem" className={`ranked-row${r.id.endsWith(`|${highlight}`) ? ' ginf-nat' : ''}`}>
          <span className="rk">{i + 1}</span>
          <span>{r.label}</span>
          <span className="bar-track"><span className="bar" style={{ display: 'block', width: `${(Math.abs(r.value) / max) * 100}%`, background: r.color }} /></span>
          <span className="val num">{formatValue(r.value, unit)}{r.period !== latest && <small className="muted"> ({formatPeriod(r.period)})</small>}</span>
        </div>
      ))}
    </div>
  );
}
