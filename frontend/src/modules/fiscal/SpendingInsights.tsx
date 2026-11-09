import { useMemo, useState } from 'react';
import { ChartCard, type ViewOption } from '@/core/components/ChartCard';
import { CompositionPie, categoriesOf } from '@/core/components/CompositionPie';
import { LineChartView } from '@/core/components/LineChartView';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import type { SeriesItem } from '@/core/hooks';
import { CATEGORIES } from './GovSpending';
import { growth, type Yearly } from './metrics';
import { MOF_KEYS, useFiscalData, type FiscalKey } from './useFiscalData';

/** لون نمو الإنفاق: درجات من الأخضر الهادئ للتراجع إلى الكهرماني ثم الأحمر للزيادات الكبيرة؛ يتدرج بشدة النمو. */
export function growthTint(v: number): string {
  const t = Math.min(1, Math.abs(v) / 25);
  if (v < 0) return `rgba(39, 150, 121, ${0.14 + t * 0.5})`;
  if (v < 10) return `rgba(58, 134, 255, ${0.08 + (v / 10) * 0.18})`;
  return v < 25 ? `rgba(214, 158, 63, ${0.25 + t * 0.35})` : 'rgba(196, 68, 65, 0.6)';
}

/** جدول حراري لأوجه الصرف: القيمة بالمليار فوق، ونسبة الزيادة أو النقص عن السنة السابقة تحتها. */
export function SpendingGrowthHeat() {
  const { isLoading, error, m, datasets } = useFiscalData(MOF_KEYS);
  const rows = useMemo(() => [...CATEGORIES.map((c) => ({ label: c.label, v: m[c.key as FiscalKey], g: growth(m[c.key as FiscalKey]) })), { label: 'إجمالي الإنفاق', v: m['mof.exp_total'], g: growth(m['mof.exp_total']) }], [m]);
  const years = useMemo(() => [...new Set(rows.flatMap((r) => [...r.v.keys()]))].sort().slice(-10), [rows]);
  return (
    <ChartCard wide title="نمو أوجه الصرف سنويًا" subtitle="القيمة (مليار ر.ع) وتحتها التغيّر % عن السنة السابقة — الحساب الختامي، وزارة المالية" datasets={datasets}
      note="الأخضر انخفاض، والأزرق نمو هادئ (أقل من 10%)، والكهرماني نمو متسارع، والأحمر قفزة فوق 25%. لا يُحسب التغيّر عبر سنة غير منشورة (2021). نسب كبيرة على أرقام صغيرة تعكس أحيانًا تغيّر التصنيف.">
      {() => isLoading ? <Loading height={260} /> : error ? <ErrorState error={error} /> : (
        <div className="heat-scroll">
          <table className="heat-table growth-heat" aria-label="نمو الإنفاق السنوي">
            <thead><tr><th />{[...years].reverse().map((y) => <th key={y} className="num">{y}</th>)}</tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.label} className={r.label === 'إجمالي الإنفاق' ? 'ga-target' : undefined}>
                  <th scope="row">{r.label}</th>
                  {[...years].reverse().map((y) => {
                    const val = r.v.get(y);
                    const g = r.g.get(y);
                    return (
                      <td key={y} className="plain num" style={g === undefined ? undefined : { background: growthTint(g) }}>
                        {val === undefined ? '—' : <><strong>{formatNumber(val, 2)}</strong><small>{g === undefined ? '' : `${g > 0 ? '+' : ''}${formatNumber(g, 1)}%`}</small></>}
                      </td>
                    );
                  })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </ChartCard>
  );
}

const sum = (...ms: Yearly[]): Yearly => new Map([...ms[0]].filter(([y]) => ms.every((x) => x.has(y))).map(([y]) => [y, ms.reduce((a, x) => a + x.get(y)!, 0)]));

const NATURE_VIEWS: ViewOption[] = [
  { value: 'stack', label: 'مكدّس مع الإجمالي' },
  { value: 'lines', label: 'خطوط' },
  { value: 'pie', label: 'دائري حسب السنة' },
  { value: 'table', label: 'جدول' },
];

/** طبيعة الإنفاق: ملزم (دفاع ومدنية وخدمة دين) مقابل استثمار مقابل دعم — مرونة الميزانية عند هبوط الإيراد. مكدّس أو خطوط أو دائري أو جدول. */
export function SpendingNature() {
  const { isLoading, error, m, datasets } = useFiscalData(MOF_KEYS);
  const [picked, setPicked] = useState<string | null>(null);
  const series = useMemo<SeriesItem[]>(() => {
    const committed = sum(m['mof.exp_defense'], m['mof.exp_civil'], m['mof.exp_other_current']);
    const mk = (id: string, label: string, color: string, y: Yearly): SeriesItem => ({ id, label, color, points: [...y].sort() as [string, number][] });
    return [mk('committed', 'جارٍ ملزم (دفاع ومدنية ودين)', 'var(--series-1)', committed), mk('invest', 'استثمار وتنمية', 'var(--series-3)', m['mof.exp_investment']), mk('support', 'مساهمات ودعم', 'var(--series-2)', m['mof.exp_contributions'])];
  }, [m]);
  const periods = useMemo(() => [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort().reverse(), [series]);
  const table = periods.length ? { columns: ['السنة', ...series.map((s) => s.label), 'الإجمالي'], rows: periods.map((y) => { const vals = series.map((s) => s.points.find((p) => p[0] === y)?.[1]); return [y, ...vals.map((v) => (v === undefined ? '—' : formatNumber(v, 2))), formatNumber(vals.reduce<number>((a, v) => a + (v ?? 0), 0), 2)]; }) } : undefined;
  return (
    <ChartCard wide title="الإنفاق الملزم مقابل المرن" subtitle="مليار ريال عُماني — مشتق من الحساب الختامي" datasets={datasets} table={table} views={NATURE_VIEWS} defaultView="stack"
      note="الجاري الملزم (الدفاع والوزارات المدنية وخدمة الدين وما شابهها) يصعب خفضه عند هبوط الإيراد؛ الاستثمار والدعم أكثر مرونة. تصنيف تحليلي لا محاسبي.">
      {(view) => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : view === 'pie' ? (
        <CompositionPie series={series} categories={categoriesOf(series)} unit="مليار ريال عُماني" period={picked} onPeriod={setPicked} />
      ) : view === 'lines' ? (
        <LineChartView series={series} unit="مليار ريال عُماني" height={320} />
      ) : (
        <ColumnChartView series={series} unit="مليار ريال عُماني" height={320} stacked last={14} totalLabel="إجمالي الإنفاق" />
      )}
    </ChartCard>
  );
}
