import type { Dataset } from '../api';
import { FreshnessBadge } from './FreshnessBadge';

/** سطر المصدر تحت كل مخطط: لا رقم بلا أصل. */
export function SourceNote({ datasets, note }: { datasets: Dataset[]; note?: string }) {
  const unique = [...new Map(datasets.map((d) => [d.key, d])).values()];
  return (
    <div className="source-note">
      {unique.map((d) => (
        <span key={d.key} style={{ display: 'inline-flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
          <span>
            المصدر: <strong>{d.source.name_ar}</strong>
            {d.freshness.source_version && <> — {d.freshness.source_version}</>}
          </span>
          <FreshnessBadge dataset={d} />
        </span>
      ))}
      {note && <span>{note}</span>}
    </div>
  );
}
