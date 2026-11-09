import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';

type Yearly = Map<string, number>;

/** حصة a من (a + b) % على السنوات المشتركة. */
export function shareOf(part: Yearly, other: Yearly): Yearly {
  return new Map([...part].filter(([y]) => other.has(y) && part.get(y)! + other.get(y)! > 0).map(([y, v]) => [y, (v / (v + other.get(y)!)) * 100]));
}

const L = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });
const KEYS = ['gccstat.POP_CITIZEN', 'gccstat.POP_NON_CITIZEN', 'gccstat.EMPLOYED_CITIZEN', 'gccstat.EMPLOYED'];

function useGcc() {
  const { isLoading, error, series, datasets } = useLines(KEYS.map(L), { from: '2010' });
  const get = (k: string): Yearly => new Map(series.find((s) => s.id.startsWith(`${k}|`))?.points ?? []);
  return { isLoading, error, datasets, get };
}

const twoBars = (id: string, label: string, color: string, m: Yearly, others: [string, string, string]): SeriesItem[] => {
  const pts = [...m].sort() as [string, number][];
  return [{ id, label, color, points: pts }, { id: others[0], label: others[1], color: others[2], points: pts.map(([y, v]) => [y, 100 - v] as [string, number]) }];
};

/** حصة غير المواطنين من السكان % (مشتق من المركز الإحصائي الخليجي). */
export function PopulationShares() {
  const { isLoading, error, datasets, get } = useGcc();
  const series = useMemo(() => twoBars('expat', 'غير المواطنين', 'var(--series-2)', shareOf(get('gccstat.POP_NON_CITIZEN'), get('gccstat.POP_CITIZEN')), ['citizen', 'المواطنون', 'var(--series-1)']), [isLoading]); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <ChartCard wide title="حصة الوافدين من السكان" subtitle="% من إجمالي السكان — مشتق من المركز الإحصائي الخليجي" datasets={datasets}
      note="غير المواطنين ÷ إجمالي السكان. ترتبط الحصة بدورة الإنفاق الحكومي والمشاريع (راجع سلاسل الأثر).">
      {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : <ColumnChartView series={series} unit="%" height={280} stacked last={15} />}
    </ChartCard>
  );
}

/** حصة غير المواطنين من العاملين % (مشتق من العاملين الكلي والمواطنين). */
export function WorkforceShares() {
  const { isLoading, error, datasets, get } = useGcc();
  const series = useMemo(() => {
    const emp = get('gccstat.EMPLOYED');
    const cit = get('gccstat.EMPLOYED_CITIZEN');
    const expat = new Map([...emp].filter(([y]) => cit.has(y)).map(([y, v]) => [y, v - cit.get(y)!]));
    return twoBars('expat', 'غير المواطنين', 'var(--series-2)', shareOf(expat, cit), ['citizen', 'المواطنون', 'var(--series-1)']);
  }, [isLoading]); // eslint-disable-line react-hooks/exhaustive-deps
  return (
    <ChartCard wide title="حصة الوافدين من العاملين" subtitle="% من إجمالي العاملين — مشتق من المركز الإحصائي الخليجي" datasets={datasets}
      note="العاملون غير المواطنين = إجمالي العاملين − العاملون المواطنون. الفرق بين هذه الحصة وحصتهم من السكان يعكس عمل الوافدين بنسبة أعلى من المواطنين.">
      {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : <ColumnChartView series={series} unit="%" height={280} stacked last={15} />}
    </ChartCard>
  );
}
