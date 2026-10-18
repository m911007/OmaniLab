import { useMemo, useState } from 'react';
import { Area, CartesianGrid, ComposedChart, Customized, Line, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { SeriesItem } from '../hooks';
import { axisNumber, formatPeriod, formatValue, shortPeriod, yearOf } from '../format';
import { dashFor } from '../colors';
import { oilAt, type OilOverlay } from '../oilOverlay';
import { ChartTooltip } from './ChartTooltip';

export const OIL_COLOR = 'var(--text-secondary)';

interface Props {
  series: SeriesItem[];
  unit?: string | null;
  height?: number;
  /** أول سنة تُعدّ توقعًا: تُرسم بخط متقطع بعد آخر قيمة فعلية */
  forecastFrom?: number;
  /** شبكة قيم أفقية فقط */
  zeroLine?: boolean;
  /** سعر النفط كخط متقطع على محور ثانوي (سياق للقراءة) */
  oil?: OilOverlay | null;
}

/** مخطط خطي: خط 2px، شبكة هامسة، مفتاح دائم لأكثر من سلسلة، وتلميح عند التمرير. */
export function LineChartView({ series, unit, height = 280, forecastFrom, zeroLine = true, oil = null }: Props) {
  const [hidden, setHidden] = useState<Set<string>>(new Set());

  // خطوط متعددة تبدأ من أول فترة مشتركة، فتنطلق كلها من الموضع نفسه ويسهل مقارنتها (لا تُقصّ إلا إن بقيت 5 نقاط فأكثر).
  const { aligned, startedAt } = useMemo(() => alignStart(series), [series]);
  const { rows: baseRows, hasForecast } = useMemo(() => buildRows(aligned, forecastFrom), [aligned, forecastFrom]);
  const rows = useMemo<Row[]>(() => (oil ? baseRows.map((r): Row => ({ ...r, __oil: oilAt(oil, r.period) })) : baseRows), [baseRows, oil]);
  const visible = aligned.filter((s) => !hidden.has(s.id));
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
  // تسمية مباشرة لآخر قيمة لكل خط حين تكون الخطوط قليلة (≤4): تغني عن التمرير لقراءة الرقم الحالي.
  const labelEnds = visible.length === 1 && !oil;
  // أكثر من خط: أسماء الخطوط وقيمها الأخيرة في عمود جانبي مرتب، متباعدة فلا تتراكب.
  const sideLabels = visible.length > 1 && visible.length <= 9;
  const lastIndex = (id: string): number => {
    for (let i = rows.length - 1; i >= 0; i--) if (rows[i][id] !== undefined && rows[i][id] !== null) return i;
    return -1;
  };
  const endLabel = (id: string) => (props: { x?: number; y?: number; index?: number; value?: number }) => {
    if (props.index !== lastIndex(id) || props.x === undefined || props.y === undefined || typeof props.value !== 'number') return <g />;
    return (
      <text x={props.x + 6} y={props.y} dy={4} fontSize={12} fontWeight={700} fill="var(--text-primary)" style={{ direction: 'ltr' }}>
        {axisNumber(props.value)}
      </text>
    );
  };

  return (
    <div>
      {series.length > 1 && (
        <div className="legend" role="group" aria-label="مفتاح المخطط">
          {series.map((s) => (
            <button key={s.id} aria-pressed={!hidden.has(s.id)} onClick={() => toggle(s.id)}>
              <LineSwatch color={s.color} dash={dashFor(s.color)} />
              {s.label}
            </button>
          ))}
        </div>
      )}
      <div className="chart-wrap" style={{ height }} role="img" aria-label={summary}>
        <ResponsiveContainer>
          <ComposedChart data={rows} margin={{ top: 8, right: sideLabels ? GUTTER : oil ? 4 : labelEnds ? 48 : 12, bottom: 0, left: 0 }}>
            <CartesianGrid vertical={false} stroke="var(--grid)" />
            <XAxis dataKey="period" tickFormatter={shortPeriod} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={{ stroke: 'var(--border)' }} minTickGap={28} />
            <YAxis tickFormatter={axisNumber} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={false} width={54} domain={['auto', 'auto']} />
            {oil && <YAxis yAxisId="oil" orientation="right" tickFormatter={axisNumber} stroke={OIL_COLOR} tick={{ fontSize: 12, fill: OIL_COLOR }} tickLine={false} axisLine={false} width={40} domain={[0, 'auto']} />}
            <Tooltip content={<ChartTooltip unit={unit} />} cursor={{ stroke: 'var(--text-muted)', strokeDasharray: '3 3' }} />
            {zeroLine && crossesZero && <ReferenceLine y={0} stroke="var(--text-muted)" strokeWidth={1} />}
            {visible.length === 1 && (
              <>
                <defs>
                  <linearGradient id={`fill-${visible[0].id.replace(/\W/g, '_')}`} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stopColor={visible[0].color} stopOpacity={0.28} />
                    <stop offset="100%" stopColor={visible[0].color} stopOpacity={0.02} />
                  </linearGradient>
                </defs>
                <Area dataKey={visible[0].id} stroke="none" fill={`url(#fill-${visible[0].id.replace(/\W/g, '_')})`} connectNulls isAnimationActive={false} legendType="none" tooltipType="none" activeDot={false} />
              </>
            )}
            {visible.map((s) => (
              <Line key={s.id} dataKey={s.id} name={s.label} stroke={s.color} strokeWidth={visible.length > 1 ? (s.id === visible[0].id ? 3.2 : 1.8) : 2.5} strokeOpacity={visible.length > 1 && s.id !== visible[0].id ? 0.92 : 1} strokeDasharray={visible.length > 1 ? dashFor(s.color) : undefined} strokeLinecap="round" strokeLinejoin="round" dot={false} activeDot={{ r: 5, stroke: 'var(--surface-1)', strokeWidth: 2 }} connectNulls isAnimationActive={false} label={labelEnds && !hasForecast ? endLabel(s.id) : undefined} />
            ))}
            {sideLabels && <Customized component={<SideLabels series={visible} unit={unit} />} />}
            {oil && <Line yAxisId="oil" dataKey="__oil" name={oil.label} stroke={OIL_COLOR} strokeWidth={2} strokeDasharray="2 4" strokeLinecap="round" dot={false} activeDot={{ r: 4 }} connectNulls isAnimationActive={false} />}
            {hasForecast &&
              visible.map((s) => (
                <Line key={`${s.id}__f`} dataKey={`${s.id}__f`} name={s.label} stroke={s.color} strokeWidth={2} strokeDasharray="5 4" dot={false} activeDot={{ r: 4, stroke: 'var(--surface-1)', strokeWidth: 2 }} connectNulls isAnimationActive={false} />
              ))}
          </ComposedChart>
        </ResponsiveContainer>
      </div>
      {startedAt && <p className="muted" style={{ fontSize: 12 }}>تبدأ الخطوط من أول فترة مشتركة ({formatPeriod(startedAt)}) لتسهيل المقارنة.</p>}
      {oil && <p className="muted" style={{ fontSize: 12 }}>الخط المتقطع الرمادي (المحور الأيمن): سعر النفط (خام دبي، صندوق النقد) للمقارنة مع حركة المؤشر.</p>}
      {hasForecast && forecastFrom && <p className="muted" style={{ fontSize: 12 }}>الخط المتقطع: توقعات من عام {forecastFrom}.</p>}
    </div>
  );
}

const GUTTER = 136;

/** عينة الخط في المفتاح: لون ونمط التقطيع نفسه المستخدم في الرسم. */
function LineSwatch({ color, dash }: { color: string; dash?: string }) {
  return (
    <svg width="22" height="8" viewBox="0 0 22 8" aria-hidden="true" style={{ flex: 'none' }}>
      <line x1="1" x2="21" y1="4" y2="4" stroke={color} strokeWidth="2.5" strokeDasharray={dash} strokeLinecap="round" />
    </svg>
  );
}

/** أسماء مختصرة للعمود الجانبي (السعودية بدل «المملكة العربية السعودية»…). */
const shortLabel = (l: string): string =>
  l.replace('المملكة العربية السعودية', 'السعودية').replace('الإمارات العربية المتحدة', 'الإمارات').replace(/^سلطنة /, '').replace(/^المملكة /, '').replace(/^جمهورية /, '').replace(/^دولة /, '').replace('الولايات المتحدة الأمريكية', 'أمريكا').replace('الولايات المتحدة', 'أمريكا');

interface SideProps {
  series: SeriesItem[];
  unit?: string | null;
  [k: string]: unknown;
}

/** عمود تسميات جانبي: اسم كل خط وقيمته الأخيرة بمحاذاة آخر نقطة، مع إزاحة رأسية تمنع التراكب (≥15px). */
function SideLabels(props: SideProps) {
  const { series, unit } = props;
  const yMap = props.yAxisMap as Record<string, { scale: (v: number) => number; yAxisId: string | number }> | undefined;
  const axis = yMap && Object.values(yMap).find((a) => String(a.yAxisId) === '0');
  const width = props.width as number | undefined;
  const offset = props.offset as { top: number; height: number } | undefined;
  if (!axis || width === undefined || !offset) return null;
  const x = width - GUTTER + 6;
  const items = series
    .map((s) => ({ s, p: s.points[s.points.length - 1] }))
    .filter((it) => it.p)
    .map((it) => ({ ...it, y: axis.scale(it.p![1]) }))
    .sort((a, b) => a.y - b.y);
  const GAP = 16;
  for (let i = 1; i < items.length; i++) if (items[i].y - items[i - 1].y < GAP) items[i].y = items[i - 1].y + GAP;
  const bottom = offset.top + offset.height;
  if (items.length && items[items.length - 1].y > bottom) {
    items[items.length - 1].y = bottom;
    for (let i = items.length - 2; i >= 0; i--) if (items[i + 1].y - items[i].y < GAP) items[i].y = items[i + 1].y - GAP;
  }
  return (
    <g aria-hidden="true">
      {items.map(({ s, p, y }) => (
        <g key={s.id}>
          <line x1={x - 6} x2={x + 10} y1={y} y2={y} stroke={s.color} strokeWidth={2.5} strokeDasharray={dashFor(s.color)} strokeLinecap="round" />
          <foreignObject x={x + 14} y={y - 9} width={GUTTER - 26} height={18}>
            <div className="side-label" dir="rtl">
              <span className="sl-name" title={s.label}>{shortLabel(s.label)}</span>
              <strong className="num sl-val">{formatValue(p![1], unit)}</strong>
            </div>
          </foreignObject>
        </g>
      ))}
    </g>
  );
}

/** يقص كل خط عند أول فترة تتوافر فيها كل الخطوط (أحدث بداية بينها)، إن بقي ما يكفي من النقاط. */
export function alignStart(series: SeriesItem[]): { aligned: SeriesItem[]; startedAt: string | null } {
  const withData = series.filter((s) => s.points.length > 0);
  if (withData.length < 2) return { aligned: series, startedAt: null };
  const starts = withData.map((s) => s.points[0][0]);
  const common = [...starts].sort().at(-1)!;
  if (common === [...starts].sort()[0]) return { aligned: series, startedAt: null };
  const trimmed = series.map((s) => ({ ...s, points: s.points.filter((p) => p[0] >= common) }));
  const kept = Math.min(...trimmed.filter((s) => s.points.length > 0).map((s) => s.points.length));
  return kept >= 5 ? { aligned: trimmed, startedAt: common } : { aligned: series, startedAt: null };
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
