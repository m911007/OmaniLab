import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import type { SeriesItem } from '@/core/hooks';
import { INTEREST_KEYS, useFiscalData } from './useFiscalData';

/** عبء خدمة الدين: ما تقتطعه الفوائد من كل ريال إيراد (الفوائد ÷ الإيرادات، من نسب صندوق النقد إلى الناتج). */
export function InterestBurden() {
  const { isLoading, error, m, datasets } = useFiscalData(INTEREST_KEYS);
  const series = useMemo<SeriesItem[]>(() => [{ id: 'burden', label: 'الفوائد ÷ الإيرادات', color: 'var(--series-4)', points: [...m['imf.ie']].filter(([y]) => m['imf.GGR_G01_GDP_PT'].get(y)).sort().map(([y, v]) => [y, (v / m['imf.GGR_G01_GDP_PT'].get(y)!) * 100] as [string, number]) }], [m]);
  return (
    <ChartCard wide title="عبء الفوائد على الإيرادات" subtitle="% — مشتق من نسب الفوائد والإيرادات إلى الناتج (صندوق النقد)" datasets={datasets}
      note="كلما ارتفعت النسبة اقتطعت الفوائد جزءًا أكبر من الإيراد قبل أن تصل إلى الإنفاق على الخدمات. سنوات الفوائد المتاحة تنتهي عند آخر نشر لقاعدة صندوق النقد.">
      {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : <LineChartView series={series} unit="%" height={280} />}
    </ChartCard>
  );
}
