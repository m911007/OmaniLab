import { useMemo, useState } from 'react';
import { ChartCard, type ViewOption } from '@/core/components/ChartCard';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { LineChartView } from '@/core/components/LineChartView';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';
import { useOilOverlay } from '@/core/oilOverlay';
import { OilToggle } from '@/core/components/OilToggle';

const UNIT = 'مليار ريال عُماني';
const LINES: LineSpec[] = [
  { indicator: 'mof.rev_total', entity: 'OMN', label: 'الإيرادات', color: 'var(--series-3)' },
  { indicator: 'mof.exp_total', entity: 'OMN', label: 'المصروفات', color: 'var(--series-2)' },
  { indicator: 'mof.balance', entity: 'OMN', label: 'الفائض/العجز', color: 'var(--series-1)' },
];
const VIEWS: ViewOption[] = [
  { value: 'lines', label: 'خطوط' },
  { value: 'bars', label: 'أعمدة' },
  { value: 'balance', label: 'الفائض/العجز' },
  { value: 'table', label: 'جدول' },
];

/** الميزانية الفعلية (الحساب الختامي، وزارة المالية): الإيرادات والمصروفات خطوطًا أو أعمدة، والفائض/العجز، مع سعر النفط يُخفى بزر. */
export function BudgetActual() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2012' });
  const [showOil, setShowOil] = useState(true);
  const oil = useOilOverlay(showOil);
  const flows = useMemo(() => series.filter((s) => !s.id.startsWith('mof.balance|')), [series]);
  const balance = useMemo(() => series.filter((s) => s.id.startsWith('mof.balance|')), [series]);
  const years = useMemo(() => [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort().reverse(), [series]);
  const table = years.length ? {
    columns: ['السنة', ...series.map((s) => s.label)],
    rows: years.map((y) => [y, ...series.map((s) => { const v = s.points.find((p) => p[0] === y)?.[1]; return v === undefined ? '—' : formatNumber(v, 2); })]),
  } : undefined;

  return (
    <ChartCard
      wide
      title="الميزانية الفعلية"
      subtitle={`${UNIT} — وزارة المالية`}
      datasets={datasets}
      table={table}
      views={VIEWS}
      defaultView="lines"
      actions={<OilToggle on={showOil} onChange={setShowOil} />}
      note="الحساب الختامي السنوي للدولة (وزارة المالية، opendata.gov.om)؛ 2021 غير منشور. الفائض/العجز = إجمالي الإيرادات − إجمالي الإنفاق (الموجب فائض)."
    >
      {(view) => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : series.length === 0 ? <Empty /> : view === 'bars' ? (
        <ColumnChartView series={flows} unit={UNIT} height={340} last={14} oil={oil} />
      ) : view === 'balance' ? (
        <ColumnChartView series={balance} unit={UNIT} height={340} last={14} oil={oil} signColors />
      ) : (
        <LineChartView series={flows} unit={UNIT} height={340} oil={oil} />
      )}
    </ChartCard>
  );
}
