import { useMemo, useState } from 'react';
import { CartesianGrid, Line, LineChart, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { SeriesItem } from '../hooks';
import { axisNumber, formatPeriod, formatValue, shortPeriod, yearOf } from '../format';
import { ChartTooltip } from './ChartTooltip';

interface Props {
  series: SeriesItem[];
  unit?: string | null;
  height?: number;
  /** أول سنة تُعدّ توقعًا: تُرسم بخط متقطع بعد آخر قيمة فعلية */
  forecastFrom?: number;
  /** شبكة قيم أفقية فقط */
  zeroLine?: boolean;
}

/** مخطط خطي: خط 2px، شبكة هامسة، مفتاح دائم لأكثر من سلسلة، وتلميح عند التمرير. */
export function LineChartView({ series, unit, height = 280, forecastFrom, zeroLine = true }: Props) {
  const [hidden, setHidden] = useState<Set<string>>(new Set());

  const { rows, hasForecast } = useMemo(() => buildRows(series, forecastFrom), [series, forecastFrom]);
  const visible = series.filter((s) => !hidden.has(s.id));
  const allValues = visible.flatMap((s) => s.points.map((p) => p[1]));
  const crossesZero = allValues.length > 0 && Math.min(...allValues) < 0 && Math.max(...allValues) > 0;

  const toggle = (id: string) =>
    setHidden((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else if (series.length - next.size > 1) next.add(id);
      return next;
    });

  const summary = `مخطط خطي: ${series.map((s) => s.label).join('، ')}`;

  return (
    <div>
      {series.length > 1 && (
        <div className="legend" role="group" aria-label="مفتاح المخطط">
          {series.map((s) => (
            <button key={s.id} aria-pressed={!hidden.has(s.id)} onClick={() => toggle(s.id)}>
              <span className="swatch" style={{ background: s.color }} />
              {s.label}
            </button>
          ))}
        </div>
      )}
      <div className="chart-wrap" style={{ height }} role="img" aria-label={summary}>
        <ResponsiveContainer>
          <LineChart data={rows} margin={{ top: 8, right: 12, bottom: 0, left: 0 }}>
            <CartesianGrid vertical={false} stroke="var(--grid)" />
            <XAxis dataKey="period" tickFormatter={shortPeriod} stroke="var(--text-muted)" tick={{ fontSize: 12 }} tickLine={false} axisLine={{ stroke: 'var(--border)' }} minTickGap={28} />
            <YAxis tickFormatter={axisNumber} stroke="var(--text-muted)" tick={{ fontSize: 12 }} tickLine={false} axisLine={false} width={54} domain={['auto', 'auto']} />
            <Tooltip content={<ChartTooltip unit={unit} />} cursor={{ stroke: 'var(--text-muted)', strokeDasharray: '3 3' }} />
            {zeroLine && crossesZero && <ReferenceLine y={0} stroke="var(--text-muted)" strokeWidth={1} />}
            {visible.map((s) => (
              <Line key={s.id} dataKey={s.id} name={s.label} stroke={s.color} strokeWidth={2} dot={false} activeDot={{ r: 4, stroke: 'var(--surface-1)', strokeWidth: 2 }} connectNulls isAnimationActive={false} />
            ))}
            {hasForecast &&
              visible.map((s) => (
                <Line key={`${s.id}__f`} dataKey={`${s.id}__f`} name={s.label} stroke={s.color} strokeWidth={2} strokeDasharray="5 4" dot={false} activeDot={{ r: 4, stroke: 'var(--surface-1)', strokeWidth: 2 }} connectNulls isAnimationActive={false} />
              ))}
          </LineChart>
        </ResponsiveContainer>
      </div>
      {hasForecast && forecastFrom && <p className="muted" style={{ fontSize: 12 }}>الخط المتقطع: توقعات من عام {forecastFrom}.</p>}
    </div>
  );
}

type Row = { period: string } & Record<string, number | string | null>;

/** صفوف موحّدة بالفترة. بعد forecastFrom تنتقل القيم إلى مفتاح «__f» مع وصل آخر نقطة فعلية لاستمرار الخط. */
export function buildRows(series: SeriesItem[], forecastFrom?: number): { rows: Row[]; hasForecast: boolean } {
  const periods = [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort();
  const map = new Map<string, Row>(periods.map((p) => [p, { period: p }]));
  let hasForecast = false;

  for (const s of series) {
    let lastActual: string | null = null;
    for (const [period, value] of s.points) {
      const row = map.get(period)!;
      if (forecastFrom && yearOf(period) >= forecastFrom) {
        row[`${s.id}__f`] = value;
        hasForecast = true;
      } else {
        row[s.id] = value;
        lastActual = period;
      }
    }
    if (forecastFrom && lastActual) {
      const joint = map.get(lastActual)!;
      joint[`${s.id}__f`] = joint[s.id] ?? null;
    }
  }

  return { rows: periods.map((p) => map.get(p)!), hasForecast };
}

export { formatPeriod, formatValue };
