import { useMemo, useState } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { Segmented } from '@/core/components/Segmented';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import type { SeriesItem } from '@/core/hooks';
import { executionRate } from './metrics';
import { MOF_KEYS, useFiscalData } from './useFiscalData';

type Measure = 'rev' | 'exp' | 'bal';
const MEASURES: { value: Measure; label: string; approved: 'mof.bud_rev_total' | 'mof.bud_exp_total' | 'mof.bud_balance'; actual: 'mof.rev_total' | 'mof.exp_total' | 'mof.balance' }[] = [
  { value: 'rev', label: 'الإيرادات', approved: 'mof.bud_rev_total', actual: 'mof.rev_total' },
  { value: 'exp', label: 'الإنفاق', approved: 'mof.bud_exp_total', actual: 'mof.exp_total' },
  { value: 'bal', label: 'الرصيد', approved: 'mof.bud_balance', actual: 'mof.balance' },
];

/** لون خانة التنفيذ: الإيراد الأعلى من المعتمد جيد (أخضر) والإنفاق الأعلى منه تجاوز (أحمر)، والعكس بالعكس. */
function tint(rate: number, goodWhenHigh: boolean): string {
  const dev = Math.min(1, Math.abs(rate - 100) / 40);
  const good = goodWhenHigh ? rate >= 100 : rate <= 100;
  return good ? `rgba(39, 150, 121, ${0.1 + dev * 0.5})` : `rgba(196, 68, 65, ${0.1 + dev * 0.5})`;
}

/** هل التزمت الحكومة بميزانيتها؟ المعتمد مقابل الفعلي، مع جدول حراري لنسبة التنفيذ. */
export function BudgetVsActual() {
  const { isLoading, error, m, datasets } = useFiscalData(MOF_KEYS);
  const [measure, setMeasure] = useState<Measure>('exp');
  const cur = MEASURES.find((x) => x.value === measure)!;
  const years = useMemo(() => [...m['mof.rev_total'].keys()].filter((y) => m['mof.bud_rev_total'].has(y)).sort(), [m]);
  const series = useMemo<SeriesItem[]>(() => [
    { id: 'approved', label: 'المعتمد', color: 'var(--series-5)', points: years.filter((y) => m[cur.approved].has(y)).map((y) => [y, m[cur.approved].get(y)!] as [string, number]) },
    { id: 'actual', label: 'الفعلي', color: 'var(--series-2)', points: years.filter((y) => m[cur.actual].has(y)).map((y) => [y, m[cur.actual].get(y)!] as [string, number]) },
  ], [m, cur, years]);
  const revRate = useMemo(() => executionRate(m['mof.rev_total'], m['mof.bud_rev_total']), [m]);
  const expRate = useMemo(() => executionRate(m['mof.exp_total'], m['mof.bud_exp_total']), [m]);
  const shown = [...years].reverse();

  return (
    <ChartCard wide title="المعتمد مقابل الفعلي" subtitle="مليار ريال عُماني — وزارة المالية (الميزانية المعتمدة والحساب الختامي)" datasets={datasets}
      actions={<Segmented label="المقياس" value={measure} onChange={setMeasure} options={MEASURES.map((x) => ({ value: x.value, label: x.label }))} />}
      note="نسبة التنفيذ = الفعلي ÷ المعتمد. إيراد أعلى من المعتمد أو إنفاق أدنى منه أفضل انضباطًا؛ 2021 غير منشور.">
      {() => isLoading ? <Loading height={320} /> : error ? <ErrorState error={error} /> : (
        <>
          <ColumnChartView series={series} unit="مليار ريال عُماني" height={300} last={14} signColors={false} />
          <h4 className="ga-h">نسبة التنفيذ %</h4>
          <div className="heat-scroll">
            <table className="heat-table" aria-label="نسبة تنفيذ الميزانية">
              <thead><tr><th />{shown.map((y) => <th key={y} className="num">{y}</th>)}</tr></thead>
              <tbody>
                <tr><th scope="row">الإيرادات</th>{shown.map((y) => { const r = revRate.get(y); return <td key={y} className="plain num" style={r === undefined ? undefined : { background: tint(r, true) }}>{r === undefined ? '—' : formatNumber(r, 0)}</td>; })}</tr>
                <tr><th scope="row">الإنفاق</th>{shown.map((y) => { const r = expRate.get(y); return <td key={y} className="plain num" style={r === undefined ? undefined : { background: tint(r, false) }}>{r === undefined ? '—' : formatNumber(r, 0)}</td>; })}</tr>
              </tbody>
            </table>
          </div>
        </>
      )}
    </ChartCard>
  );
}
