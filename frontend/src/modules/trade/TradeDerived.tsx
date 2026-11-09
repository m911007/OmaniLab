import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';

type Yearly = Map<string, number>;

/** الميزان التجاري = الصادرات − الواردات (نفس الوحدة، السنوات المشتركة). */
export function balanceOf(exports: Yearly, imports: Yearly): Yearly {
  return new Map([...exports].filter(([y]) => imports.has(y)).map(([y, v]) => [y, v - imports.get(y)!]));
}

/** نسبة تغطية الصادرات للواردات %. */
export function coverage(exports: Yearly, imports: Yearly): Yearly {
  return new Map([...exports].filter(([y]) => (imports.get(y) ?? 0) > 0).map(([y, v]) => [y, (v / imports.get(y)!) * 100]));
}

/** النمو السنوي % (بين سنتين متتاليتين فقط). */
export function growthPct(m: Yearly): Yearly {
  const out: Yearly = new Map();
  for (const [y, v] of m) {
    const prev = m.get(String(Number(y) - 1));
    if (prev) out.set(y, ((v - prev) / Math.abs(prev)) * 100);
  }
  return out;
}

const L = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });
const KEYS = ['manafeth.EXPORTS', 'manafeth.IMPORTS', 'manafeth.REEXPORTS'];

export function useTradeTotals() {
  const { isLoading, error, series, datasets } = useLines(KEYS.map(L), { from: '2012' });
  const get = (k: string): Yearly => new Map((series.find((s) => s.id.startsWith(`${k}|`))?.points ?? []).map(([y, v]) => [y, v / 1e9]));
  return { isLoading, error, datasets, exports: get('manafeth.EXPORTS'), imports: get('manafeth.IMPORTS'), reexports: get('manafeth.REEXPORTS') };
}

const pts = (m: Yearly): [string, number][] => [...m].sort();

/** الميزان التجاري السلعي الجمركي بالمليار ريال (أخضر فائض/أحمر عجز) وتغطية الصادرات للواردات. */
export function TradeBalance() {
  const { isLoading, error, datasets, exports, imports } = useTradeTotals();
  const { bal, cov } = useMemo(() => ({
    bal: [{ id: 'bal', label: 'الفائض/العجز التجاري', color: 'var(--series-1)', points: pts(balanceOf(exports, imports)) }] as SeriesItem[],
    cov: [{ id: 'cov', label: 'تغطية الصادرات للواردات', color: 'var(--series-3)', points: pts(coverage(exports, imports)) }] as SeriesItem[],
  }), [exports, imports]);
  return (
    <>
      <ChartCard wide title="الميزان التجاري السلعي" subtitle="مليار ريال عُماني — الصادرات (دون إعادة التصدير) − الواردات، منافذ (المركز الوطني للإحصاء)" datasets={datasets}
        table={{ columns: ['السنة', 'الفائض/العجز', 'تغطية الصادرات للواردات'], rows: [...bal[0].points].reverse().map(([y, v]) => [y, formatNumber(v, 2), `${formatNumber(cov[0].points.find((p) => p[0] === y)?.[1] ?? 0, 0)}%`]) }}
        note="بيانات جمركية: الصادرات تشمل النفط الخام والمشتقات دون إعادة التصدير. تختلف قليلًا عن ميزان السلع في ميزان المدفوعات لاختلاف التقييم (جمركي مقابل فوب).">
        {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : <ColumnChartView series={bal} unit="مليار ريال عُماني" height={300} last={14} signColors />}
      </ChartCard>
      <ChartCard wide title="تغطية الصادرات للواردات" subtitle="% — مشتق من منافذ" datasets={datasets} note="100% يعني توازنًا؛ أعلى منها فائض تجاري. تنخفض حين ترتفع الواردات أسرع من الصادرات أو يهبط سعر النفط.">
        {() => isLoading ? <Loading height={260} /> : error ? <ErrorState error={error} /> : <LineChartView series={cov} unit="%" height={260} />}
      </ChartCard>
    </>
  );
}

/** نمو الصادرات والواردات وإعادة التصدير سنويًا %. */
export function TradeGrowth() {
  const { isLoading, error, datasets, exports, imports, reexports } = useTradeTotals();
  const series = useMemo<SeriesItem[]>(() => [
    { id: 'x', label: 'الصادرات', color: 'var(--series-1)', points: pts(growthPct(exports)) },
    { id: 'm', label: 'الواردات', color: 'var(--series-2)', points: pts(growthPct(imports)) },
    { id: 'r', label: 'إعادة التصدير', color: 'var(--series-3)', points: pts(growthPct(reexports)) },
  ], [exports, imports, reexports]);
  return (
    <ChartCard wide title="نمو التجارة السنوي" subtitle="% عن السنة السابقة — مشتق من منافذ (المركز الوطني للإحصاء)" datasets={datasets} note="نمو الصادرات يتبع سعر النفط أساسًا؛ فنمو الواردات الأعلى منه علامة اتساع في الطلب المحلي أو المشاريع.">
      {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : <LineChartView series={series} unit="%" height={280} />}
    </ChartCard>
  );
}
