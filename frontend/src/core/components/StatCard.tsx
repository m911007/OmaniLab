import type { LatestItem } from '../api';
import { changeBetween, formatNumber, formatPeriod, formatValue, shortUnit } from '../format';
import { StatLabel, TrendArrow } from './IndicatorIcon';
import { Compact } from './Compact';
import { SourceBadge } from './SourceBadge';
import { Sparkline } from './Sparkline';
import { Loading } from './States';

interface Props {
  label: string;
  item?: LatestItem;
  loading?: boolean;
  /** عرض القيمة مختصرة (مليار...) */
  compactValue?: boolean;
  /** اتجاه التحسّن لتلوين التغيّر؛ بلا تحديد يبقى محايدًا */
  goodWhen?: 'up' | 'down';
  /** قيم تاريخية قصيرة لخط الاتجاه المصغّر */
  spark?: readonly number[];
}


/** رقم رئيسي بتغيّره عن الفترة السابقة، وترتيبه بين الدول، وفترته ومصدره. */
export function StatCard({ label, item, loading, compactValue, goodWhen, spark }: Props) {
  if (loading) return <div className="card"><Loading height={96} /></div>;
  if (!item) {
    return (
      <div className="card stat">
        <StatLabel>{label}</StatLabel>
        <span className="muted">لا توجد بيانات</span>
      </div>
    );
  }
  const change = item.previous ? changeBetween(item.value, item.previous.value, item.indicator.unit) : null;
  const tone = !change || change.direction === 'flat' || !goodWhen ? 'var(--text-secondary)' : change.direction === goodWhen ? 'var(--good)' : 'var(--critical)';

  const toneClass = tone === 'var(--good)' ? 'good' : tone === 'var(--critical)' ? 'bad' : 'flat';

  return (
    <div className="card stat" aria-label={label}>
      <StatLabel>{label}</StatLabel>
      <span className="stat-line">
        <span>
          <span className="stat-value">{compactValue ? <Compact v={item.value} /> : <span className="num">{formatValue(item.value, item.indicator.unit)}</span>}</span>
          <span className="stat-unit"><bdi>{shortUnit(item.indicator.unit ?? '')}</bdi></span>
        </span>
        {spark && spark.length > 1 && <Sparkline values={spark} color={tone === 'var(--text-secondary)' ? 'var(--accent)' : tone} />}
      </span>
      {change && item.previous && (
        <span className="stat-deltarow">
          <span className={`stat-delta reading-delta ${toneClass}`} style={{ color: tone }}>
            <TrendArrow direction={change.direction} />
            <span>
              <span className="num">{change.direction === 'flat' ? '' : change.direction === 'up' ? '+' : '−'}{change.amount}</span>
              {change.suffix !== '%' ? ` ${change.suffix}` : <span className="num">%</span>}
            </span>
          </span>
          <span className="reading-since">{/^\d{4}$/.test(item.previous.period) ? `عن عام ${item.previous.period}` : `عن ${formatPeriod(item.previous.period)}`}</span>
        </span>
      )}
      <span className="stat-meta">
        {formatPeriod(item.period)}
        {item.rank && <> · المرتبة <span className="num">{formatNumber(item.rank.position, 0)}</span> من <span className="num">{item.rank.total}</span> دولة</>}
      </span>
      <span className="stat-meta source-item" title={item.dataset.freshness.source_version ?? ''}>
        <SourceBadge sourceKey={item.dataset.source.key} name={item.dataset.source.name_ar} url={item.dataset.source.url} />
        <span>{item.dataset.source.name_ar}</span>
      </span>
    </div>
  );
}
