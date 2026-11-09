import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { OMR_PER_USD } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';
import { annualMean } from '@/core/stats';

const L = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });
type Yearly = Map<string, number>;

/** قيمة الإنتاج السنوية بسعر السوق (مليار ريال): الإنتاج اليومي (ألف برميل) × 365 × متوسط السعر × الربط ÷ مليون. */
export function marketValueOmr(productionKbd: Yearly, priceUsd: Yearly): Yearly {
  return new Map([...productionKbd].filter(([y]) => priceUsd.has(y)).map(([y, p]) => [y, ((p * 365 * priceUsd.get(y)!) / 1e6) * OMR_PER_USD]));
}

/** استهلاك الاشتراك الواحد (ميغاواط ساعة): الاستهلاك الكلي ÷ الاشتراكات. */
export function perConnection(consumptionGwh: Yearly, connections: Yearly): Yearly {
  return new Map([...consumptionGwh].filter(([y]) => (connections.get(y) ?? 0) > 0).map(([y, v]) => [y, (v * 1000) / connections.get(y)!]));
}

const OIL_LINES = ['ncsi.OIL_PRODUCTION', 'ncsi.OIL_PRICE', 'mof.rev_oil'].map(L);

/** قيمة إنتاج النفط الخام بسعر السوق مقابل إيرادات النفط في الميزانية (مشتق): الفرق يشمل تكاليف الإنتاج وحصص الشركاء ومنتجات غير داخلة في الإيراد. */
export function OilValue() {
  const { isLoading, error, series, datasets } = useLines(OIL_LINES, { from: '2016' });
  const out = useMemo<SeriesItem[]>(() => {
    const get = (k: string) => series.find((s) => s.id.startsWith(`${k}|`))?.points ?? [];
    const prod = new Map(annualMean(get('ncsi.OIL_PRODUCTION')));
    const price = new Map(annualMean(get('ncsi.OIL_PRICE')));
    const value = marketValueOmr(prod, price);
    const rev = new Map(get('mof.rev_oil'));
    return [
      { id: 'value', label: 'قيمة الإنتاج بسعر السوق', color: 'var(--series-2)', points: [...value].sort() as [string, number][] },
      { id: 'rev', label: 'إيرادات النفط في الميزانية', color: 'var(--series-3)', points: [...rev].filter(([y]) => value.has(y)).sort() as [string, number][] },
    ];
  }, [series]);
  const table = { columns: ['السنة', ...out.map((s) => s.label), 'الإيراد ÷ القيمة'], rows: [...(out[0].points)].reverse().map(([y, v]) => { const r = out[1].points.find((p) => p[0] === y)?.[1]; return [y, formatNumber(v, 2), r === undefined ? '—' : formatNumber(r, 2), r === undefined ? '—' : `${formatNumber((r / v) * 100, 0)}%`]; }) };
  return (
    <ChartCard wide title="قيمة الإنتاج مقابل إيراد النفط" subtitle="مليار ريال عُماني — مشتق: الإنتاج × السعر (المركز الوطني للإحصاء) مقابل الحساب الختامي (وزارة المالية)" datasets={datasets} table={table}
      note="قيمة الإنتاج = متوسط الإنتاج اليومي × 365 × متوسط سعر نفط عُمان × 0.3845. لا تساوي دخل الدولة: يُخصم منها تكاليف الإنتاج وحصص الشركاء، وتتأخر بعض المستحقات؛ فالنسبة بين الخطين تقدير تقريبي لحصة الميزانية لا قياس محاسبي.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : <LineChartView series={out} unit="مليار ريال عُماني" height={300} />}
    </ChartCard>
  );
}

const ELEC_LINES = ['ncsi.ELEC_CONSUMPTION', 'ncsi.ELEC_CONNECTIONS'].map(L);

/** كثافة استهلاك الكهرباء: ميغاواط ساعة لكل اشتراك سنويًا (مشتق): ارتفاعها يعني تبريدًا وصناعةً أكثر لكل مشترك. */
export function ElectricityIntensity() {
  const { isLoading, error, series, datasets } = useLines(ELEC_LINES, { from: '2005' });
  const out = useMemo<SeriesItem[]>(() => {
    const get = (k: string) => new Map(series.find((s) => s.id.startsWith(`${k}|`))?.points ?? []);
    return [{ id: 'pc', label: 'استهلاك الاشتراك الواحد', color: 'var(--series-1)', points: [...perConnection(get('ncsi.ELEC_CONSUMPTION'), get('ncsi.ELEC_CONNECTIONS'))].sort() as [string, number][] }];
  }, [series]);
  return (
    <ChartCard wide title="استهلاك الكهرباء لكل اشتراك" subtitle="ميغاواط ساعة سنويًا — مشتق: الاستهلاك ÷ الاشتراكات (المركز الوطني للإحصاء)" datasets={datasets}
      note="متوسط يشمل المنازل والمصانع والتجاري والحكومي معًا، فيتأثر بتركيبة الاشتراكات لا بسلوك الأسرة وحدها.">
      {() => isLoading ? <Loading height={260} /> : error ? <ErrorState error={error} /> : <LineChartView series={out} unit="ميغاواط ساعة" height={260} />}
    </ChartCard>
  );
}
