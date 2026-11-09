import { useMemo, useState } from 'react';
import { Bar, CartesianGrid, Cell, ComposedChart, Customized, Line, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { oilAt, type OilOverlay } from '../oilOverlay';
import { axisNumber, shortPeriod, yearOf } from '../format';
import type { SeriesItem } from '../hooks';
import { ChartTooltip } from './ChartTooltip';
import { ForecastBand } from './ForecastBand';

interface Props {
  series: SeriesItem[];
  unit?: string | null;
  height?: number;
  stacked?: boolean;
  /** آخر N فترة فقط */
  last?: number;
  /** سعر النفط كخط متقطع على محور ثانوي */
  oil?: OilOverlay | null;
  /** يرسم مجموع الأعمدة الظاهرة خطًا مع نقاط فوق المكدّس، باسم يُعرض في التلميح */
  totalLabel?: string;
  /** سلسلة واحدة: الموجب أخضر والسالب أحمر (فائض/عجز) */
  signColors?: boolean;
  /** أول سنة تُعدّ توقعًا: أعمدتها باهتة بحدّ منقّط ومنطقتها مظلّلة */
  forecastFrom?: number;
}

const gradId = (c: string) => `bar-${c.replace(/\W/g, '_')}`;

/** أعمدة سنوية مجمّعة أو مكدّسة: زوايا 4px للطرف، فجوة 2px بين المقاطع، وخط أساس صفري. */
export function ColumnChartView({ series, unit, height = 280, stacked = false, last = 14, oil = null, totalLabel, signColors = false, forecastFrom }: Props) {
  const [hidden, setHidden] = useState<Set<string>>(new Set());

  const rows = useMemo(() => {
    const periods = [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort().slice(-last);
    return periods.map((period) => {
      const row: Record<string, number | string | null> = { period };
      for (const s of series) row[s.id] = s.points.find((p) => p[0] === period)?.[1] ?? null;
      if (totalLabel) {
        const vals = series.filter((s) => !hidden.has(s.id)).map((s) => row[s.id]).filter((v): v is number => typeof v === 'number');
        row.__total = vals.length ? vals.reduce((a, b) => a + b, 0) : null;
      }
      if (oil) row.__oil = oilAt(oil, period);
      return row;
    });
  }, [series, last, oil, totalLabel, hidden]);

  const isForecast = (period: unknown) => forecastFrom !== undefined && yearOf(String(period)) >= forecastFrom;
  const forecastBefore = forecastFrom !== undefined ? ([...rows].reverse().find((r) => !isForecast(r.period))?.period as string | undefined) : undefined;
  const forecastStart = forecastFrom !== undefined ? (rows.find((r) => isForecast(r.period))?.period as string | undefined) : undefined;

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
          <ComposedChart data={rows} margin={{ top: 8, right: oil ? 4 : 12, bottom: 0, left: 0 }} barCategoryGap="22%" barGap={2}>
            <defs>
              {[...new Set([...series.map((x) => x.color), 'var(--good)', 'var(--critical)'])].map((c) => (
                <linearGradient key={c} id={gradId(c)} x1="0" y1="0" x2="0" y2="1">
                  <stop offset="0%" stopColor={c} stopOpacity={1} />
                  <stop offset="100%" stopColor={c} stopOpacity={0.62} />
                </linearGradient>
              ))}
            </defs>
            <CartesianGrid vertical={false} stroke="var(--grid)" strokeDasharray="2 6" strokeOpacity={0.7} />
            <XAxis dataKey="period" tickFormatter={shortPeriod} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={{ stroke: 'var(--border)' }} />
            <YAxis tickFormatter={axisNumber} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={false} width={54} />
            {oil && <YAxis yAxisId="oil" orientation="right" tickFormatter={axisNumber} stroke="var(--text-secondary)" tick={{ fontSize: 12, fill: 'var(--text-secondary)' }} tickLine={false} axisLine={false} width={40} domain={[0, 'auto']} />}
            {forecastStart && forecastBefore && <Customized component={<ForecastBand before={forecastBefore} after={forecastStart} />} />}
            {signColors && <ReferenceLine y={0} stroke="var(--text-muted)" />}
            <Tooltip content={<ChartTooltip unit={unit} forecastFrom={forecastFrom} />} cursor={{ fill: 'var(--surface-2)', opacity: 0.6 }} />
            {visible.map((s) => (
              <Bar key={s.id} dataKey={s.id} name={s.label} fill={stacked ? s.color : `url(#${gradId(s.color)})`} stackId={stacked ? 'a' : undefined} radius={stacked ? 0 : [7, 7, 0, 0]} stroke="var(--surface-1)" strokeWidth={stacked ? 2 : 0} isAnimationActive={false}>
                {(signColors && series.length === 1) || forecastStart
                  ? rows.map((r) => {
                      const fill = signColors && series.length === 1 ? (Number(r[s.id]) < 0 ? 'var(--critical)' : 'var(--good)') : s.color;
                      return isForecast(r.period) ? <Cell key={String(r.period)} fill={fill} fillOpacity={0.35} stroke={fill} strokeWidth={1.6} strokeDasharray="4 3" /> : <Cell key={String(r.period)} fill={stacked ? fill : `url(#${gradId(fill)})`} />;
                    })
                  : null}
              </Bar>
            ))}
            {totalLabel && <Line dataKey="__total" name={totalLabel} stroke="var(--text-primary)" strokeWidth={2} strokeDasharray="5 4" dot={{ r: 3, fill: 'var(--text-primary)', stroke: 'var(--surface-1)', strokeWidth: 2 }} isAnimationActive={false} />}
            {oil && <Line yAxisId="oil" dataKey="__oil" name={oil.label} stroke="var(--text-secondary)" strokeWidth={2} strokeDasharray="2 4" dot={false} connectNulls isAnimationActive={false} />}
          </ComposedChart>
        </ResponsiveContainer>
      </div>
      {forecastStart && <p className="muted" style={{ fontSize: 12 }}>الأعمدة الباهتة بحدّ منقّط: توقعات من عام {forecastFrom}، وما قبلها قيم فعلية.</p>}
      {totalLabel && <p className="muted" style={{ fontSize: 12 }}>الخط المتقطع مع النقاط: {totalLabel}.</p>}
      {oil && <p className="muted" style={{ fontSize: 12 }}>الخط المتقطع الرمادي (المحور الأيمن): سعر النفط العُماني (المركز الوطني للإحصاء) للمقارنة.</p>}
    </div>
  );
}
