import type { Dataset, DatasetStatus } from '../api';
import { formatDateTime, relativeTime } from '../format';

const TONE: Record<DatasetStatus, { cls: string; icon: string }> = {
  fresh: { cls: 'good', icon: '✓' },
  pending: { cls: 'neutral', icon: '…' },
  manual: { cls: 'neutral', icon: '✎' },
  disabled: { cls: 'neutral', icon: '–' },
  stale: { cls: 'warning', icon: '!' },
  quarantined: { cls: 'warning', icon: '⏸' },
  failing: { cls: 'critical', icon: '✕' },
};

/** حالة المجموعة وآخر فحص وآخر تغيير: الأيقونة والنص معًا، فالحالة لا تعتمد على اللون وحده. */
export function FreshnessBadge({ dataset }: { dataset: Dataset }) {
  const tone = TONE[dataset.status];
  const f = dataset.freshness;
  const title = [
    `الإصدار: ${f.source_version ?? '—'}`,
    `آخر فحص: ${formatDateTime(f.last_checked_at)}`,
    `آخر تغيير في البيانات: ${formatDateTime(f.last_changed_at)}`,
    `الفحص القادم: ${formatDateTime(f.next_check_at)}`,
  ].join('\n');

  return (
    <span title={title} style={{ display: 'inline-flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
      <span className={`chip ${tone.cls}`}>
        <span aria-hidden="true">{tone.icon}</span>
        {dataset.status_label}
      </span>
      <span>
        آخر فحص <strong>{relativeTime(f.last_checked_at)}</strong>
        {f.last_changed_at && <> · آخر تغيير {relativeTime(f.last_changed_at)}</>}
      </span>
    </span>
  );
}
