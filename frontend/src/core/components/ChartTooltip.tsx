import { axisNumber, formatPeriod, formatValue, yearOf } from '../format';

interface Entry {
  dataKey?: string | number;
  name?: string | number;
  value?: number | string;
  color?: string;
  stroke?: string;
  fill?: string;
}

/** تلميح موحّد: الفترة، ثم صف لكل سلسلة (دون تكرار خط التوقعات). */
export function ChartTooltip({ active, payload, label, unit, forecastFrom }: { active?: boolean; payload?: Entry[]; label?: string | number; unit?: string | null; forecastFrom?: number }) {
  if (!active || !payload?.length) return null;
  const seen = new Set<string>();
  const rows = payload.filter((p) => {
    const key = String(p.dataKey ?? '').replace(/__f$/, '');
    if (seen.has(key) || p.value === null || p.value === undefined) return false;
    seen.add(key);
    return true;
  });

  return (
    <div className="tooltip">
      <div className="t-head">
        {formatPeriod(String(label))}
        {forecastFrom !== undefined && yearOf(String(label)) >= forecastFrom && <span className="t-forecast">توقعات</span>}
      </div>
      {rows.map((p) => (
        <div className="t-row" key={String(p.dataKey)}>
          <span className="t-name">
            <span className="dot" style={{ background: p.color ?? p.stroke ?? p.fill }} />
            {p.name}
          </span>
          <strong className="num">{typeof p.value === 'number' ? (p.dataKey === '__oil' ? `${formatValue(p.value, '$/برميل')} $` : formatValue(p.value, unit)) : p.value}</strong>
        </div>
      ))}
      {unit && <div className="muted" style={{ marginTop: 4, fontSize: 12 }}>{unit}</div>}
    </div>
  );
}

export { axisNumber };
