import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber, OMR_PER_USD } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';
import { annualMean } from '@/core/stats';
import { hydrocarbonExportShare, importCoverMonths, type Yearly } from './derived';

const L = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });
const LINES: LineSpec[] = ['ncsi.BOP_EXPORTS_CRUDE', 'ncsi.BOP_EXPORTS_REFINED', 'ncsi.BOP_EXPORTS_GAS', 'ncsi.BOP_EXPORTS_FOB', 'ncsi.BOP_IMPORTS_FOB', 'ncsi.MON_RESERVES_FX'].map(L);

function useSeriesMaps() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2012' });
  const m = useMemo(() => {
    const get = (k: string): Yearly => new Map(k === 'ncsi.MON_RESERVES_FX' ? annualMean(series.find((s) => s.id.startsWith(`${k}|`))?.points ?? [], 1) : series.find((s) => s.id.startsWith(`${k}|`))?.points ?? []);
    return { crude: get('ncsi.BOP_EXPORTS_CRUDE'), refined: get('ncsi.BOP_EXPORTS_REFINED'), gas: get('ncsi.BOP_EXPORTS_GAS'), fob: get('ncsi.BOP_EXPORTS_FOB'), imports: get('ncsi.BOP_IMPORTS_FOB'), reserves: get('ncsi.MON_RESERVES_FX') };
  }, [series]);
  return { isLoading, error, m, datasets };
}

/** كم تعتمد الصادرات على الهيدروكربون؟ حصة النفط الخام والمشتقات والغاز من الصادرات السلعية % (مشتق من ميزان المدفوعات). */
export function ExportDependence() {
  const { isLoading, error, m, datasets } = useSeriesMaps();
  const series = useMemo<SeriesItem[]>(() => {
    const h = hydrocarbonExportShare(m.crude, m.refined, m.gas, m.fob);
    const pts = [...h].sort() as [string, number][];
    return [{ id: 'hydro', label: 'نفط ومشتقات وغاز', color: 'var(--series-2)', points: pts }, { id: 'other', label: 'صادرات أخرى', color: 'var(--series-3)', points: pts.map(([y, v]) => [y, 100 - v] as [string, number]) }];
  }, [m]);
  return (
    <ChartCard wide title="اعتماد الصادرات على النفط والغاز" subtitle="% من الصادرات السلعية (فوب) — مشتق من ميزان المدفوعات، المركز الوطني للإحصاء" datasets={datasets}
      note="حصة النفط الخام والمشتقات والغاز من إجمالي الصادرات السلعية. ترتفع حين ترتفع الأسعار فلا تعني زيادة الاعتماد الهيكلي وحدها؛ والمتبقي يشمل غير النفطي وإعادة التصدير حسب التصنيف المنشور.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : <ColumnChartView series={series} unit="%" height={300} stacked last={12} />}
    </ChartCard>
  );
}

/** تغطية الاحتياطيات للواردات: كم شهرًا من الواردات السلعية تكفيها احتياطيات النقد الأجنبي (البنك المركزي، محوَّلة من الدولار). */
export function ReserveCover() {
  const { isLoading, error, m, datasets } = useSeriesMaps();
  const series = useMemo<SeriesItem[]>(() => {
    const cover = importCoverMonths(new Map([...m.reserves].map(([y, v]) => [y, v * OMR_PER_USD])), m.imports);
    return [{ id: 'cover', label: 'أشهر من الواردات', color: 'var(--series-1)', points: [...cover].sort() as [string, number][] }];
  }, [m]);
  const last = series[0].points[series[0].points.length - 1];
  return (
    <ChartCard wide title="تغطية الاحتياطيات للواردات" subtitle="أشهر — مشتق: احتياطيات النقد الأجنبي ÷ متوسط الواردات السلعية الشهري" datasets={datasets}
      table={{ columns: ['السنة', 'أشهر'], rows: [...series[0].points].reverse().map(([y, v]) => [y, formatNumber(v, 1)]) }}
      note="احتياطيات النقد الأجنبي (متوسط السنة، محوَّلة بالربط 0.3845) على الواردات السلعية فوب. القاعدة الشائعة: 3 أشهر فأكثر كحدٍّ أدنى، والربط بالدولار يتطلب هامشًا أعلى.">
      {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : (
        <>
          {last && <p className="muted" role="status">{last[0]}: <b className="num">{formatNumber(last[1], 1)}</b> أشهر من الواردات السلعية.</p>}
          <LineChartView series={series} unit="أشهر" height={280} />
        </>
      )}
    </ChartCard>
  );
}
