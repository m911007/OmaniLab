import type { ScorecardItem } from '@/core/api';
import { COUNTRY_NAMES } from '@/core/groups';
import { formatNumber, formatPeriod, formatValue } from '@/core/format';
import { CLASS_META } from './classes';
import { PositionStrip } from './PositionStrip';

const TREND = {
  improving: { icon: '↗', text: 'يتحسن' },
  worsening: { icon: '↘', text: 'يتراجع' },
  stable: { icon: '→', text: 'مستقر' },
} as const;

/** صف مؤشر في بطاقة الأداء: القيمة، موقعها العالمي والخليجي، اتجاهها، والفجوة عن أفضل خليجي. */
export function IndicatorRow({ item, thresholds }: { item: ScorecardItem; thresholds: { weak_until: number; strong_from: number; baseline_years: number } }) {
  const meta = CLASS_META[item.class];
  const trend = item.trend ? TREND[item.trend] : null;
  const best = item.gcc?.best;
  const lead = best && best.code === 'OMN';

  return (
    <li className="ind-row">
      <div className="ind-main">
        <div className="ind-title">
          <span className={`chip ${meta.chip}`} title={meta.hint}>
            <span aria-hidden="true">{meta.icon}</span>
            {meta.label}
          </span>
          <strong>{item.name_ar}</strong>
        </div>
        <div className="ind-value">
          <span className="num ind-number">{formatValue(item.value, item.unit)}</span>
          {item.unit && <span className="muted"> {item.unit}</span>}
          <span className="muted"> · {formatPeriod(item.period)}</span>
        </div>
      </div>

      <PositionStrip item={item} weakUntil={thresholds.weak_until} strongFrom={thresholds.strong_from} />

      <div className="ind-facts">
        <span>
          المرتبة <span className="num">{formatNumber(item.world.rank, 0)}</span> من <span className="num">{formatNumber(item.world.of, 0)}</span> عالميًا
        </span>
        {item.gcc && (
          <span>
            <span className="num">{item.gcc.rank}</span> من <span className="num">{item.gcc.of}</span> خليجيًا
            {best && !lead && (
              <span className="muted">
                {' '}
                (الأفضل: {COUNTRY_NAMES[best.code] ?? best.code} <span className="num">{formatValue(best.value, item.unit)}</span>)
              </span>
            )}
            {lead && <span className="muted"> (الأولى خليجيًا)</span>}
          </span>
        )}
        {trend && item.baseline && (
          <span>
            <span aria-hidden="true">{trend.icon}</span> {trend.text} منذ <span className="num">{formatPeriod(item.baseline.period)}</span> (<span className="num">{formatValue(item.baseline.value, item.unit)}</span>)
          </span>
        )}
        {!trend && <span className="muted">لا توجد بيانات كافية للاتجاه</span>}
        <span className="muted" title={item.dataset.freshness.source_version ?? ''}>
          {item.dataset.source.name_ar}
        </span>
      </div>
    </li>
  );
}
