import type { Signal } from '@/core/api';
import { useSignals } from '@/core/hooks';

const TONE: Record<Signal['tone'], { chip: string; icon: string; label: string }> = {
  critical: { chip: 'critical', icon: '▼', label: 'خطر' },
  warning: { chip: 'warning', icon: '!', label: 'انتباه' },
  good: { chip: 'good', icon: '▲', label: 'فرصة' },
};

/** إشارات السوق النشطة: قواعد معلنة (config/signals.php) على أحدث البيانات، كل إشارة بقيمتها وفترتها. تختفي اللوحة إن لم توجد إشارات. */
export function SignalsPanel() {
  const { data } = useSignals();
  if (!data || data.length === 0) return null;
  return (
    <section className="card signals" aria-label="إشارات السوق">
      <div className="card-head">
        <div>
          <h3 className="card-title">إشارات السوق</h3>
          <div className="card-sub">تغيّرات تستحق المتابعة في أحدث البيانات المعتمدة</div>
        </div>
      </div>
      <ul className="signal-list">
        {data.map((s) => {
          const tone = TONE[s.tone];
          return (
            <li key={s.id}>
              <span className={`chip ${tone.chip}`}>
                <span aria-hidden="true">{tone.icon}</span>
                {tone.label}
              </span>
              <div>
                <strong>{s.title}</strong>
                <div className="muted">{s.message}</div>
              </div>
            </li>
          );
        })}
      </ul>
    </section>
  );
}
