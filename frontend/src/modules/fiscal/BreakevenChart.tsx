import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import type { SeriesItem } from '@/core/hooks';
import { breakevenOilPrice } from './metrics';
import { BREAKEVEN_KEYS, useFiscalData } from './useFiscalData';

/** سعر النفط الفعلي مقابل سعر التعادل التقريبي: السنوات التي يعلو فيها الأول الثاني فائض، وعكسها عجز. */
export function BreakevenChart() {
  const { isLoading, error, m, datasets } = useFiscalData(BREAKEVEN_KEYS);
  const be = useMemo(() => breakevenOilPrice(m['ncsi.OIL_PRICE'], m['mof.balance'], m['mof.rev_oil']), [m]);
  const series = useMemo<SeriesItem[]>(() => [
    { id: 'price', label: 'سعر النفط (متوسط السنة)', color: 'var(--series-2)', points: [...m['ncsi.OIL_PRICE']].filter(([y]) => be.has(y)) as [string, number][] },
    { id: 'be', label: 'سعر التعادل التقريبي', color: 'var(--series-1)', points: [...be] as [string, number][] },
  ], [m, be]);
  const table = series[1].points.length ? {
    columns: ['السنة', 'سعر النفط ($)', 'سعر التعادل ($)', 'الفرق'],
    rows: [...series[1].points].reverse().map(([y, b]) => { const p = m['ncsi.OIL_PRICE'].get(y)!; return [y, formatNumber(p, 1), formatNumber(b, 1), `${p - b > 0 ? '+' : ''}${formatNumber(p - b, 1)}`]; }),
  } : undefined;
  return (
    <ChartCard wide title="سعر النفط مقابل سعر التعادل" subtitle="دولار للبرميل — مشتق من الحساب الختامي وسعر نفط عُمان (المركز الوطني للإحصاء)" datasets={datasets} table={table}
      note="سعر التعادل = السعر × (1 − الرصيد ÷ إيراد النفط): السعر الذي كان يصفّر العجز لو تغيّر إيراد النفط وحده بنسبة السعر. تقدير تقريبي بإنتاج ثابت ولا يعكس تغيّر الإنتاج أو الإنفاق.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : <LineChartView series={series} unit="$/برميل" height={300} />}
    </ChartCard>
  );
}
