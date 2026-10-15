import type { Scorecard } from '@/core/api';
import { formatNumber } from '@/core/format';

/** أشرطة أفقية لمتوسط موقع عُمان في كل محور؛ خط الوسيط عند 50. الاسم والقيمة نصًا، فالقراءة لا تعتمد على اللون. */
export function ThemeProfile({ themes, selected, onSelect }: { themes: Scorecard['themes']; selected: string; onSelect: (key: string) => void }) {
  const rows = themes.filter((t) => t.percentile !== null).sort((a, b) => (b.percentile ?? 0) - (a.percentile ?? 0));
  return (
    <ul className="theme-list" aria-label="موقع عُمان حسب المحور">
      {rows.map((t) => {
        const pct = t.percentile ?? 0;
        return (
          <li key={t.key}>
            <button className="theme-row" aria-pressed={selected === t.key} onClick={() => onSelect(t.key)}>
              <span className="theme-name">
                {t.label} <span className="muted num">({t.count})</span>
              </span>
              <span className="theme-track" aria-hidden="true">
                <span className="theme-bar" style={{ width: `${pct}%`, background: pct >= 50 ? 'var(--series-1)' : 'var(--series-2)' }} />
                <span className="theme-median" />
              </span>
              <span className="num theme-val">{formatNumber(pct, 0)}</span>
            </button>
          </li>
        );
      })}
    </ul>
  );
}
