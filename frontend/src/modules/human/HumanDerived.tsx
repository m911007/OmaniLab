import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';

type Yearly = Map<string, number>;

/** نسبة a إلى b على السنوات المشتركة (طلبة لكل معلم). */
export function perUnit(numer: Yearly, denom: Yearly): Yearly {
  return new Map([...numer].filter(([y]) => (denom.get(y) ?? 0) > 0).map(([y, v]) => [y, v / denom.get(y)!]));
}

const L = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });
const LINES = ['gccstat.STUDENTS_SCHOOL', 'gccstat.TEACHERS_SCHOOL', 'gccstat.STUDENTS_UNI', 'gccstat.TEACHERS_UNI'].map(L);

/** عبء الفصل: طلبة المدارس لكل معلم، وطلبة الجامعات لكل عضو هيئة تدريس (مشتق من المركز الإحصائي الخليجي). */
export function SchoolRatios() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2011' });
  const out = useMemo<SeriesItem[]>(() => {
    const get = (k: string): Yearly => new Map(series.find((s) => s.id.startsWith(`${k}|`))?.points ?? []);
    return [
      { id: 'school', label: 'طلبة المدارس لكل معلم', color: 'var(--series-1)', points: [...perUnit(get('gccstat.STUDENTS_SCHOOL'), get('gccstat.TEACHERS_SCHOOL'))].sort() as [string, number][] },
      { id: 'uni', label: 'طلبة الجامعات لكل عضو هيئة تدريس', color: 'var(--series-2)', points: [...perUnit(get('gccstat.STUDENTS_UNI'), get('gccstat.TEACHERS_UNI'))].sort() as [string, number][] },
    ];
  }, [series]);
  return (
    <ChartCard wide title="عدد الطلبة لكل معلم" subtitle="طالب لكل معلم — مشتق من المركز الإحصائي الخليجي" datasets={datasets}
      note="ارتفاع النسبة يعني ازدحامًا أكبر في الفصول؛ متوسط وطني يخفي التفاوت بين المدارس والمحافظات.">
      {() => isLoading ? <Loading height={260} /> : error ? <ErrorState error={error} /> : <LineChartView series={out} unit="طالب/معلم" height={280} />}
    </ChartCard>
  );
}
