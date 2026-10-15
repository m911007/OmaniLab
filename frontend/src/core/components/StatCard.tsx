import type { LatestItem } from '../api';
import { changeBetween, formatNumber, formatPeriod, formatValue } from '../format';
import { Compact } from './Compact';
import { Loading } from './States';

interface Props {
  label: string;
  item?: LatestItem;
  loading?: boolean;
  /** عرض القيمة مختصرة (مليار...) */
  compactValue?: boolean;
  /** اتجاه التحسّن لتلوين التغيّر؛ بلا تحديد يبقى محايدًا */
  goodWhen?: 'up' | 'down';
}

const ARROW = { up: '▲', down: '▼', flat: '■' } as const;

/** رقم رئيسي بتغيّره عن الفترة السابقة، وترتيبه بين الدول، وفترته ومصدره. */
export function StatCard({ label, item, loading, compactValue, goodWhen }: Props) {
  if (loading) return <div className="card"><Loading height={96} /></div>;
  if (!item) {
    return (
      <div className="card stat">
        <span className="stat-label">{label}</span>
        <span className="muted">لا توجد بيانات</span>
      </div>
    );
  }
  const change = item.previous ? changeBetween(item.value, item.previous.value, item.indicator.unit) : null;
  const tone = !change || change.direction === 'flat' || !goodWhen ? 'var(--text-secondary)' : change.direction === goodWhen ? 'var(--good)' : 'var(--critical)';

  return (
    <div className="card stat" aria-label={label}>
      <span className="stat-label">{label}</span>
      <span>
        <span className="stat-value">{compactValue ? <Compact v={item.value} /> : <span className="num">{formatValue(item.value, item.indicator.unit)}</span>}</span>
        <span className="stat-unit">{item.indicator.unit}</span>
      </span>
      {change && item.previous && (
        <span className="stat-delta" style={{ color: tone }}>
          <span aria-hidden="true">{ARROW[change.direction]}</span>
          <span>
            <span className="num">{change.amount}</span>
            {change.suffix !== '%' ? ` ${change.suffix}` : <span className="num">%</span>}
          </span>
          <span className="muted">عن {formatPeriod(item.previous.period)}</span>
        </span>
      )}
      <span className="stat-meta">
        {formatPeriod(item.period)}
        {item.rank && <> · المرتبة <span className="num">{formatNumber(item.rank.position, 0)}</span> من <span className="num">{item.rank.total}</span> دولة</>}
      </span>
      <span className="stat-meta" title={item.dataset.freshness.source_version ?? ''}>
        {item.dataset.source.name_ar}
      </span>
    </div>
  );
}
