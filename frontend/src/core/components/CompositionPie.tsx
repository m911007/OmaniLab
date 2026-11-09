import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';
import { Empty } from './States';
import { PeriodSlider } from './PeriodSlider';
import { formatNumber, formatPeriod, formatValue } from '../format';

export interface Category {
  key: string;
  label: string;
  color: string;
}

export interface Slice {
  key: string;
  label: string;
  color: string;
  value: number;
  share: number;
}

interface SeriesLike {
  id: string;
  points: [string, number][];
}

/** حصص المكوّنات في فترة؛ من لا قيمة موجبة له يُهمل (الدائري لا يمثّل السالب). */
export function slicesOf(period: string, series: SeriesLike[], categories: Category[]): { slices: Slice[]; total: number } {
  const raw = categories.map((c) => ({ ...c, value: series.find((s) => s.id.startsWith(`${c.key}|`))?.points.find((p) => p[0] === period)?.[1] ?? null }))
    .filter((c): c is typeof c & { value: number } => c.value !== null && c.value > 0);
  const total = raw.reduce((a, c) => a + c.value, 0);
  return { slices: raw.map((c) => ({ ...c, share: total ? (c.value / total) * 100 : 0 })), total };
}

/** مكوّنات الرسم المكدّس كفئات للدائري. */
export function categoriesOf(series: { id: string; label: string; color: string }[]): Category[] {
  return series.map((s) => ({ key: s.id.split('|')[0], label: s.label, color: s.color }));
}

const fmt = (v: number) => formatNumber(v, Math.abs(v) < 100 ? 2 : 0);

/** دائري حلقي لتركيب مجموع في فترة يختارها القارئ بشريط تمرير، مع حصص كل مكوّن. */
export function CompositionPie({ series, categories, unit, period, onPeriod }: { series: SeriesLike[]; categories: Category[]; unit?: string | null; period: string | null; onPeriod: (p: string) => void }) {
  const periods = [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort();
  const current = period && periods.includes(period) ? period : periods[periods.length - 1];
  const { slices: raw, total } = slicesOf(current, series, categories);
  // من الأكبر إلى الأصغر (وبند «جهات أخرى» المجمَّع يبقى أخيرًا)
  const slices = [...raw].sort((a, b) => (a.key === 'others' ? 1 : b.key === 'others' ? -1 : b.value - a.value));
  return (
    <div className="gov-pie">
      <PeriodSlider periods={periods} current={current} onChange={onPeriod} />
      {slices.length === 0 ? <Empty>لا بيانات لهذه الفترة.</Empty> : (
        <div className="gov-pie-body">
          <div className="gov-pie-chart" role="img" aria-label={`تركيب ${formatPeriod(current)}`}>
            <ResponsiveContainer>
              <PieChart>
                <Pie data={slices} dataKey="value" nameKey="label" innerRadius="58%" outerRadius="92%" paddingAngle={2} stroke="var(--surface-1)" strokeWidth={2} isAnimationActive={false}>
                  {slices.map((s) => <Cell key={s.key} fill={s.color} />)}
                </Pie>
                <Tooltip formatter={(v: number, n: string) => [`${formatValue(v, unit)}${unit ? ` ${unit}` : ''}`, n]} contentStyle={{ background: 'var(--surface-1)', border: '1px solid var(--border)', borderRadius: 8 }} />
              </PieChart>
            </ResponsiveContainer>
            <div className="gov-pie-center"><b className="num">{fmt(total)}</b>{unit && <small>{unit}</small>}</div>
          </div>
          <ul className="gov-pie-legend">
            {slices.map((s) => (
              <li key={s.key}><span className="swatch" style={{ background: s.color }} /><span>{s.label}</span><b className="num">{fmt(s.value)}</b><span className="muted num">{formatNumber(s.share, 1)}%</span></li>
            ))}
          </ul>
        </div>
      )}
    </div>
  );
}
