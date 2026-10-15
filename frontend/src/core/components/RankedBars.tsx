import { formatNumber } from '../format';
import { Compact } from './Compact';

export interface RankedItem {
  key: string;
  rank: number;
  label: string;
  value: number;
  share?: number | null;
  color?: string;
  muted?: boolean;
}

/** أشرطة أفقية مرتبة (HTML): تسمية مباشرة لكل شريط، والقيمة والنسبة نصًا، فلا اعتماد على اللون وحده. */
export function RankedBars({ items, unitLabel }: { items: RankedItem[]; unitLabel?: string }) {
  const max = Math.max(...items.map((i) => i.value), 1);
  return (
    <div className="ranked" role="list">
      {items.map((i) => (
        <div className="ranked-row" role="listitem" key={i.key}>
          <span className="rk num">{i.rank}</span>
          <span style={{ color: i.muted ? 'var(--text-muted)' : undefined, fontWeight: i.color ? 700 : 400 }}>{i.label}</span>
          <span className="bar-track" aria-hidden="true">
            <span className="bar" style={{ display: 'block', width: `${(i.value / max) * 100}%`, background: i.muted ? 'var(--series-other)' : (i.color ?? 'var(--series-1)') }} />
          </span>
          <span className="val" title={`${formatNumber(i.value, 0)}${unitLabel ? ' ' + unitLabel : ''}`}>
            <Compact v={i.value} />
            {i.share != null && (
              <span className="muted">
                {' · '}
                <span className="num">{formatNumber(i.share * 100, 1)}%</span>
              </span>
            )}
          </span>
        </div>
      ))}
    </div>
  );
}
