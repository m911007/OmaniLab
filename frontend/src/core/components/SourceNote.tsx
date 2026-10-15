import type { Dataset, DatasetStatus } from '../api';
import { formatDate, formatDateTime, relativeTime } from '../format';
import { SourceBadge } from './SourceBadge';

const STATUS_DOT: Record<DatasetStatus, { color: string; show: boolean }> = {
  fresh: { color: 'var(--good)', show: false },
  pending: { color: 'var(--text-muted)', show: true },
  manual: { color: 'var(--text-muted)', show: true },
  disabled: { color: 'var(--text-muted)', show: true },
  stale: { color: 'var(--warning)', show: true },
  quarantined: { color: 'var(--warning)', show: true },
  failing: { color: 'var(--critical)', show: true },
};

/**
 * تذييل المصدر تحت كل رسم، بترتيب ثابت لكل مصدر: شارة (رابط المصدر الأصلي) ← اسم المصدر والمجموعة ← تاريخ تحديث المصدر وآخر فحص عندنا.
 * الحالة الطبيعية (محدّثة) تظهر نقطة خضراء فقط؛ غير الطبيعية تُكتب نصًا بجانب النقطة. التفاصيل الكاملة في التلميح.
 */
export function SourceNote({ datasets, note }: { datasets: Dataset[]; note?: string }) {
  const unique = [...new Map(datasets.map((d) => [d.key, d])).values()];
  return (
    <footer className="src-foot" aria-label="مصدر البيانات">
      {unique.map((d) => {
        const f = d.freshness;
        const dot = STATUS_DOT[d.status];
        const tip = [`الإصدار: ${f.source_version ?? '—'}`, `آخر فحص: ${formatDateTime(f.last_checked_at)}`, `آخر تغيير في البيانات: ${formatDateTime(f.last_changed_at)}`, `الفحص القادم: ${formatDateTime(f.next_check_at)}`].join('\n');
        return (
          <div className="src-line" key={d.key}>
            <SourceBadge sourceKey={d.source.key} name={d.source.name_ar} url={d.source.url} />
            <div className="src-text">
              <div className="src-name">
                <strong>{d.source.name_ar}</strong>
                <span className="muted"> · {d.name_ar}</span>
              </div>
              <div className="src-meta" title={tip}>
                <span>
                  تحديث المصدر: <b>{f.source_published_at ? formatDate(f.source_published_at) : 'غير معلن'}</b>
                </span>
                <span aria-hidden="true">•</span>
                <span>فُحص {relativeTime(f.last_checked_at)}</span>
                <span className="src-status" style={{ color: dot.color }}>
                  <i style={{ background: dot.color }} aria-hidden="true" />
                  {dot.show ? d.status_label : <span className="sr-only">{d.status_label}</span>}
                </span>
              </div>
            </div>
          </div>
        );
      })}
      {note && <p className="src-note">{note}</p>}
    </footer>
  );
}
