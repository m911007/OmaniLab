import { useMemo, useState } from 'react';
import { Area, CartesianGrid, ComposedChart, Customized, Line, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import type { SeriesItem } from '../hooks';
import { axisNumber, formatPeriod, formatValue, shortPeriod, yearOf } from '../format';
import { dashFor } from '../colors';
import { oilAt, type OilOverlay } from '../oilOverlay';
import { ChartTooltip } from './ChartTooltip';
import { Segmented } from './Segmented';

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

/** متوسط متحرك لآخر n نقاط (يبدأ بعد اكتمال النافذة). */
export function movingAverage(points: SeriesItem['points'], n: number): SeriesItem['points'] {
  if (n <= 1) return points;
  const out: SeriesItem['points'] = [];
  for (let i = n - 1; i < points.length; i++) {
    let sum = 0;
    for (let j = i - n + 1; j <= i; j++) sum += points[j][1];
    out.push([points[i][0], sum / n]);
  }
  return out;
}

/** يعيد القياس إلى 100 في أول نقطة (لمقارنة الاتجاه على محور واحد). */
export function rebase(points: SeriesItem['points']): SeriesItem['points'] {
  const base = points[0]?.[1];
  return base ? points.map(([p, v]) => [p, (v / base) * 100]) : points;
}

/** لكل فترة: أدنى وأعلى ووسيط قيم الدول الأخرى (نطاق المقارنة حول الدولة المرصودة). */
export function bandAt(others: SeriesItem[], period: string): { lo: number; hi: number; mid: number } | null {
  const vals = others.map((s) => s.points.find((p) => p[0] === period)?.[1]).filter((v): v is number => v !== undefined).sort((a, b) => a - b);
  if (vals.length < 2) return null;
  const m = Math.floor(vals.length / 2);
  return { lo: vals[0], hi: vals[vals.length - 1], mid: vals.length % 2 ? vals[m] : (vals[m - 1] + vals[m]) / 2 };
}

/** حدّ الخطوط المرسومة معًا؛ ما زاد يُعرض نطاقًا حول الدولة المرصودة أو يُخفى حتى يختاره القارئ. */
export const MAX_LINES = 4;

/**
 * مخطط خطي: خط 2px، شبكة هامسة، مفتاح دائم لأكثر من سلسلة، وتلميح عند التمرير.
 * يختار الشكل بحسب البيانات: أرقام منفردة إن قلّت النقاط، ونطاق «بقية الدول» إن زادت الخطوط عن أربعة،
 * ومتوسط متحرك اختياري للشهري المتذبذب، وعرض «مؤشَّر = 100» على محور واحد بديلًا عن محور النفط الثاني.
 */
export function LineChartView({ series, unit, height = 280, forecastFrom, zeroLine = true, oil = null }: Props) {
  const many = series.length > MAX_LINES;
  const [hidden, setHidden] = useState<Set<string>>(() => new Set(many ? series.slice(MAX_LINES).map((s) => s.id) : []));
  const [mode, setMode] = useState<'band' | 'lines'>(many ? 'band' : 'lines');
  const [scale, setScale] = useState<'dual' | 'indexed'>('dual');
  const [smooth, setSmooth] = useState<'0' | '3' | '12'>('0');

  const monthly = series.length > 0 && series.length <= 2 && series.every((s) => s.points.every((p) => p[0].length === 7)) && series.some((s) => s.points.length >= 24);
  const canIndex = Boolean(oil) && series.length > 0 && series.every((s) => s.points.length > 1 && s.points.every((p) => p[1] > 0));
  const indexed = canIndex && scale === 'indexed';
  const smoothN = monthly ? Number(smooth) : 0;
  const band = many && mode === 'band';

  const shaped = useMemo<SeriesItem[]>(
    () => series.map((s) => {
      let pts = smoothN > 1 ? movingAverage(s.points, smoothN) : s.points;
      if (indexed) pts = rebase(pts);
      return { ...s, points: pts };
    }),
    [series, smoothN, indexed],
  );
  const drawn = band ? shaped.slice(0, 1) : shaped;
  const others = band ? shaped.slice(1) : [];

  // خطوط متعددة تبدأ من أول فترة مشتركة، فتنطلق كلها من الموضع نفسه ويسهل مقارنتها (لا تُقصّ إلا إن بقيت 5 نقاط فأكثر).
  const { aligned, startedAt } = useMemo(() => alignStart(drawn), [drawn]);
  const { rows: baseRows, hasForecast } = useMemo(() => buildRows(aligned, forecastFrom), [aligned, forecastFrom]);
  const rows = useMemo<Row[]>(() => {
    const oilBase = indexed && oil && baseRows[0] ? oilAt(oil, baseRows[0].period) : null;
    const raw = smoothN > 1 ? new Map(series.flatMap((s) => (indexed ? rebase(s.points) : s.points).map((p): [string, number] => [`${s.id}|${p[0]}`, p[1]]))) : null;
    return baseRows.map((r): Row => {
      const out: Row = { ...r };
      if (oil) {
        const o = oilAt(oil, r.period);
        out.__oil = indexed ? (o != null && oilBase ? (o / oilBase) * 100 : null) : o;
      }
      if (band) {
        const b = bandAt(others, r.period);
        out.__band = b ? ([b.lo, b.hi] as unknown as number) : null;
        out.__mid = b?.mid ?? null;
      }
      if (raw) for (const s of aligned) out[`${s.id}__raw`] = raw.get(`${s.id}|${r.period}`) ?? null;
      return out;
    });
  }, [baseRows, oil, indexed, band, others, smoothN, series, aligned]);

  const visible = aligned.filter((s) => !hidden.has(s.id));
  const allValues = visible.flatMap((s) => s.points.map((p) => p[1]));
  const crossesZero = allValues.length > 0 && Math.min(...allValues) < 0 && Math.max(...allValues) > 0;
  const shownUnit = indexed ? 'مؤشر (أول فترة = 100)' : unit;

  const toggle = (id: string) =>
    setHidden((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else if (series.length - next.size > 1) next.add(id);
      return next;
    });

  // رقم أو رقمان لا يصنعان خطًا: تُعرض أرقامًا مباشرة
  if (series.length > 0 && series.every((s) => s.points.length < 3)) {
    return (
      <div className="stat-tiles" role="list" aria-label="قيم المؤشر">
        {series.map((s) => {
          const p = s.points[s.points.length - 1];
          return (
            <div key={s.id} className="stat-tile" role="listitem">
              <span className="muted">{s.label}</span>
              <b className="num">{p ? formatValue(p[1], unit) : '—'}</b>
              {p && <small className="muted">{formatPeriod(p[0])}</small>}
            </div>
          );
        })}
      </div>
    );
  }

  const summary = `مخطط خطي: ${series.map((s) => s.label).join('، ')}`;
  // تسمية مباشرة لآخر قيمة لكل خط حين تكون الخطوط قليلة (≤4): تغني عن التمرير لقراءة الرقم الحالي.
  const labelEnds = visible.length === 1 && !oil && !band;
  // أكثر من خط: أسماء الخطوط وقيمها الأخيرة في عمود جانبي مرتب، متباعدة فلا تتراكب.
  const sideLabels = !band && visible.length > 1 && visible.length <= 9;
  const dualAxis = Boolean(oil) && !indexed;
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
      {(many || canIndex || monthly) && (
        <div className="chart-modes">
          {many && <Segmented label="طريقة العرض" value={mode} onChange={setMode} options={[{ value: 'band', label: `${series[0].label} ونطاق البقية` }, { value: 'lines', label: 'كل الخطوط' }]} />}
          {canIndex && <Segmented label="مقياس النفط" value={scale} onChange={setScale} options={[{ value: 'dual', label: 'محوران' }, { value: 'indexed', label: 'مؤشَّر = 100' }]} />}
          {monthly && <Segmented label="التنعيم" value={smooth} onChange={setSmooth} options={[{ value: '0', label: 'الأصل' }, { value: '3', label: 'متوسط 3 أشهر' }, { value: '12', label: '12 شهرًا' }]} />}
        </div>
      )}
      {band ? (
        <div className="legend" aria-label="مفتاح المخطط">
          <span><LineSwatch color={series[0].color} />{series[0].label}</span>
          <span><span className="band-swatch" aria-hidden="true" />نطاق بقية الدول ({others.length}): الأدنى–الأعلى</span>
          <span><LineSwatch color="var(--text-muted)" dash="4 3" />الوسيط</span>
        </div>
      ) : series.length > 1 && (
        <div className="legend" role="group" aria-label="مفتاح المخطط">
          {series.map((s) => (
            <button key={s.id} aria-pressed={!hidden.has(s.id)} onClick={() => toggle(s.id)}>
              <LineSwatch color={s.color} dash={dashFor(s.color)} />
              {s.label}
            </button>
          ))}
          {hidden.size > 0 && <button className="legend-all" onClick={() => setHidden(new Set())}>إظهار الكل ({series.length})</button>}
        </div>
      )}
      <div className="chart-wrap" style={{ height }} role="img" aria-label={summary}>
        <ResponsiveContainer>
          <ComposedChart data={rows} margin={{ top: 8, right: sideLabels ? GUTTER : dualAxis ? 4 : labelEnds ? 48 : 12, bottom: 0, left: 0 }}>
            <CartesianGrid vertical={false} stroke="var(--grid)" />
            <XAxis dataKey="period" tickFormatter={shortPeriod} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={{ stroke: 'var(--border)' }} minTickGap={28} />
            <YAxis tickFormatter={axisNumber} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={false} width={54} domain={['auto', 'auto']} />
            {dualAxis && oil && <YAxis yAxisId="oil" orientation="right" tickFormatter={axisNumber} stroke={OIL_COLOR} tick={{ fontSize: 12, fill: OIL_COLOR }} tickLine={false} axisLine={false} width={40} domain={[0, 'auto']} label={{ value: 'النفط $', angle: 0, position: 'insideTopRight', offset: 0, fontSize: 11, fill: OIL_COLOR }} />}
            <Tooltip content={<ChartTooltip unit={shownUnit} />} cursor={{ stroke: 'var(--text-muted)', strokeDasharray: '3 3' }} />
            {zeroLine && crossesZero && <ReferenceLine y={0} stroke="var(--text-muted)" strokeWidth={1} />}
            {indexed && <ReferenceLine y={100} stroke="var(--text-muted)" strokeDasharray="2 3" />}
            {band && <Area dataKey="__band" name="نطاق بقية الدول" stroke="none" fill="var(--text-muted)" fillOpacity={0.16} connectNulls isAnimationActive={false} activeDot={false} legendType="none" tooltipType="none" />}
            {band && <Line dataKey="__mid" name="وسيط بقية الدول" stroke="var(--text-muted)" strokeWidth={1.5} strokeDasharray="4 3" dot={false} connectNulls isAnimationActive={false} />}
            {visible.length === 1 && !band && (
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
            {smoothN > 1 && visible.map((s) => <Line key={`${s.id}__raw`} dataKey={`${s.id}__raw`} name={`${s.label} (الأصل)`} stroke={s.color} strokeOpacity={0.3} strokeWidth={1.2} dot={false} connectNulls isAnimationActive={false} />)}
            {visible.map((s) => (
              <Line key={s.id} dataKey={s.id} name={s.label} stroke={s.color} strokeWidth={visible.length > 1 ? (s.id === visible[0].id ? 3.2 : 1.8) : 2.5} strokeOpacity={visible.length > 1 && s.id !== visible[0].id ? 0.92 : 1} strokeDasharray={visible.length > 1 ? dashFor(s.color) : undefined} strokeLinecap="round" strokeLinejoin="round" dot={false} activeDot={{ r: 5, stroke: 'var(--surface-1)', strokeWidth: 2 }} connectNulls isAnimationActive={false} label={labelEnds && !hasForecast ? endLabel(s.id) : undefined} />
            ))}
            {sideLabels && <Customized component={<SideLabels series={visible} unit={shownUnit} />} />}
            {oil && <Line yAxisId={dualAxis ? 'oil' : undefined} dataKey="__oil" name={oil.label} stroke={OIL_COLOR} strokeWidth={2} strokeDasharray="2 4" strokeLinecap="round" dot={false} activeDot={{ r: 4 }} connectNulls isAnimationActive={false} />}
            {hasForecast &&
              visible.map((s) => (
                <Line key={`${s.id}__f`} dataKey={`${s.id}__f`} name={s.label} stroke={s.color} strokeWidth={2} strokeDasharray="5 4" dot={false} activeDot={{ r: 4, stroke: 'var(--surface-1)', strokeWidth: 2 }} connectNulls isAnimationActive={false} />
              ))}
          </ComposedChart>
        </ResponsiveContainer>
      </div>
      {startedAt && <p className="muted" style={{ fontSize: 12 }}>تبدأ الخطوط من أول فترة مشتركة ({formatPeriod(startedAt)}) لتسهيل المقارنة.</p>}
      {oil && dualAxis && <p className="muted" style={{ fontSize: 12 }}>الخط المتقطع الرمادي (المحور الأيمن «النفط $»): {oil.label} للمقارنة مع حركة المؤشر. لمقارنة الاتجاه على محور واحد اختر «مؤشَّر = 100».</p>}
      {oil && indexed && <p className="muted" style={{ fontSize: 12 }}>المؤشر وسعر النفط معًا على محور واحد: كلاهما = 100 في أول فترة، فيظهر أيهما تحرك أكثر.</p>}
      {smoothN > 1 && <p className="muted" style={{ fontSize: 12 }}>الخط الواضح متوسط متحرك لآخر {smoothN} أشهر، والباهت القيم الشهرية الأصلية.</p>}
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
