import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { ErrorState, Loading } from '@/core/components/States';
import type { SeriesItem } from '@/core/hooks';
import { hydrocarbonShare } from './metrics';
import { MOF_KEYS, useFiscalData } from './useFiscalData';

/** كم يعتمد الإيراد على النفط والغاز؟ حصتا الهيدروكربون وغيره % من إجمالي الإيراد كل سنة. */
export function HydroShareChart() {
  const { isLoading, error, m, datasets } = useFiscalData(MOF_KEYS);
  const series = useMemo<SeriesItem[]>(() => {
    const h = hydrocarbonShare(m['mof.rev_oil'], m['mof.rev_gas'], m['mof.rev_total']);
    const pts = [...h].sort() as [string, number][];
    return [
      { id: 'hydro', label: 'نفط وغاز', color: 'var(--series-2)', points: pts },
      { id: 'other', label: 'إيرادات أخرى', color: 'var(--series-3)', points: pts.map(([y, v]) => [y, 100 - v] as [string, number]) },
    ];
  }, [m]);
  return (
    <ChartCard wide title="الاعتماد على النفط والغاز" subtitle="% من إجمالي الإيراد — مشتق من الحساب الختامي (وزارة المالية)" datasets={datasets}
      note="كلما انخفضت حصة النفط والغاز قلّ تأثر الإيراد بتقلّب الأسعار. تتغير الحصة أيضًا بتغيّر السعر نفسه، فارتفاعها في سنوات الأسعار المرتفعة لا يعني زيادة الاعتماد الهيكلي.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : <ColumnChartView series={series} unit="%" height={300} stacked last={14} />}
    </ChartCard>
  );
}
