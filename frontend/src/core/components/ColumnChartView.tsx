import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { axisNumber, shortPeriod } from '../format';
import type { SeriesItem } from '../hooks';
import { ChartTooltip } from './ChartTooltip';

interface Props {
  series: SeriesItem[];
  unit?: string | null;
  height?: number;
  stacked?: boolean;
  /** آخر N فترة فقط */
  last?: number;
}

/** أعمدة سنوية مجمّعة أو مكدّسة: زوايا 4px للطرف، فجوة 2px بين المقاطع، وخط أساس صفري. */
export function ColumnChartView({ series, unit, height = 280, stacked = false, last = 14 }: Props) {
  const [hidden, setHidden] = useState<Set<string>>(new Set());

  const rows = useMemo(() => {
    const periods = [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort().slice(-last);
    return periods.map((period) => {
      const row: Record<string, number | string | null> = { period };
      for (const s of series) row[s.id] = s.points.find((p) => p[0] === period)?.[1] ?? null;
      return row;
    });
  }, [series, last]);

  const visible = series.filter((s) => !hidden.has(s.id));
  const toggle = (id: string) =>
    setHidden((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else if (series.length - next.size > 1) next.add(id);
      return next;
    });

  return (
    <div>
      {series.length > 1 && (
        <div className="legend" role="group" aria-label="مفتاح المخطط">
          {series.map((s) => (
            <button key={s.id} aria-pressed={!hidden.has(s.id)} onClick={() => toggle(s.id)}>
              <span className="swatch" style={{ background: s.color, height: 10, width: 10, borderRadius: 2 }} />
              {s.label}
            </button>
          ))}
        </div>
      )}
      <div className="chart-wrap" style={{ height }} role="img" aria-label={`مخطط أعمدة: ${series.map((s) => s.label).join('، ')}`}>
        <ResponsiveContainer>
          <BarChart data={rows} margin={{ top: 8, right: 12, bottom: 0, left: 0 }} barCategoryGap="22%" barGap={2}>
            <CartesianGrid vertical={false} stroke="var(--grid)" />
            <XAxis dataKey="period" tickFormatter={shortPeriod} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={{ stroke: 'var(--border)' }} />
            <YAxis tickFormatter={axisNumber} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={false} width={54} />
            <Tooltip content={<ChartTooltip unit={unit} />} cursor={{ fill: 'var(--surface-2)', opacity: 0.6 }} />
            {visible.map((s) => (
              <Bar key={s.id} dataKey={s.id} name={s.label} fill={s.color} stackId={stacked ? 'a' : undefined} radius={stacked ? 0 : [4, 4, 0, 0]} stroke="var(--surface-1)" strokeWidth={stacked ? 2 : 0} isAnimationActive={false} />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </div>
    </div>
  );
}
