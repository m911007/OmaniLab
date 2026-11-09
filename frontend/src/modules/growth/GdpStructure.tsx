import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';
import { Breakdown } from '@/modules/fiscal/GovSpending';
import { growth } from '@/modules/fiscal/metrics';
import type { Category } from '@/core/components/CompositionPie';

const P = (code: string, label: string, color: string): Category => ({ key: `ncsi.GDP_SHARE_${code}`, label, color });

const SPLIT: Category[] = [P('PETRO', 'الأنشطة النفطية', 'var(--series-2)'), P('NON_PETRO', 'الأنشطة غير النفطية', 'var(--series-3)')];
const ACTIVITIES: Category[] = [
  P('CRUDE', 'النفط الخام', 'var(--series-2)'), P('GAS', 'الغاز الطبيعي', 'var(--series-4)'), P('MANUF', 'الصناعات التحويلية', 'var(--series-7)'),
  P('UTILITIES', 'الكهرباء والمياه', 'var(--series-5)'), P('CONSTRUCTION', 'التشييد', 'var(--series-6)'), P('SERVICES', 'الخدمات', 'var(--series-1)'), P('AGRI_FISH', 'الزراعة والصيد', 'var(--series-3)'),
];

/** نفطي مقابل غير نفطي: حصة كل منهما من الناتج بالأسعار الجارية (حصص منشورة، فالمجموع قد يتجاوز 100 قليلًا بسبب اختلاف أساس القياس). */
export function PetroSplit() {
  return <Breakdown unit="% من الناتج" from="2010" defaultView="lines" title="الاقتصاد النفطي وغير النفطي" subtitle="المركز الوطني للإحصاء — بالأسعار الجارية" categories={SPLIT}
    note="حصة الأنشطة النفطية (النفط الخام والغاز) وغير النفطية من الناتج المحلي. الحصص منشورة بأسس قياس مختلفة فقد يتجاوز مجموعهما 100%؛ لذلك يعرض «حصص %» و«الدائري» النسبة إلى مجموع المعروض. آخر سنة منشورة 2021." />;
}

/** من أين تأتي القيمة المضافة: النفط الخام والغاز والتحويلية والمرافق والتشييد والخدمات والزراعة والصيد. */
export function ActivityMix() {
  return <Breakdown unit="% من الناتج" from="2010" defaultView="lines" title="هيكل الناتج حسب النشاط" subtitle="المركز الوطني للإحصاء — بالأسعار الجارية" categories={ACTIVITIES}
    note="حصص الأنشطة من الناتج بالأسعار الجارية (دون صافي الضرائب على المنتجات). الحصص تقريبية المجموع لاختلاف الأساس، ويعرض «حصص %» و«الدائري» النسبة إلى مجموع المعروض. التعدين والأنشطة الصغيرة الأخرى غير معروضة." />;
}

const LINES: LineSpec[] = [
  { indicator: 'ncsi.GDP_CONSTANT', entity: 'OMN' },
  { indicator: 'ncsi.GDP_SHARE_PETRO_CONST', entity: 'OMN' },
];

/** نمو الاقتصاد الحقيقي الكلي مقابل غير النفطي: غير النفطي = الناتج الثابت × (1 − حصة النفط الثابتة). مشتق، حتى آخر سنة تتوفر فيها الحصص. */
export function NonOilGrowth() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2010' });
  const { rows, chart } = useMemo(() => {
    const gdp = new Map(series.find((s) => s.id.startsWith('ncsi.GDP_CONSTANT|'))?.points ?? []);
    const share = new Map(series.find((s) => s.id.startsWith('ncsi.GDP_SHARE_PETRO_CONST|'))?.points ?? []);
    const nonOil = new Map([...gdp].filter(([y]) => share.has(y)).map(([y, v]) => [y, v * (1 - share.get(y)! / 100)]));
    const petro = new Map([...gdp].filter(([y]) => share.has(y)).map(([y, v]) => [y, v * (share.get(y)! / 100)]));
    const mk = (id: string, label: string, color: string, m: Map<string, number>): SeriesItem => ({ id, label, color, points: [...growth(m)].sort() as [string, number][] });
    const chart = [mk('total', 'الناتج الكلي', 'var(--series-1)', new Map([...gdp].filter(([y]) => share.has(y)))), mk('nonoil', 'غير النفطي', 'var(--series-3)', nonOil), mk('petro', 'النفطي', 'var(--series-2)', petro)];
    const years = [...new Set(chart.flatMap((s) => s.points.map((p) => p[0])))].sort().reverse();
    return { chart, rows: years.map((y) => [y, ...chart.map((s) => { const v = s.points.find((p) => p[0] === y)?.[1]; return v === undefined ? '—' : `${formatNumber(v, 1)}%`; })]) };
  }, [series]);
  return (
    <ChartCard wide title="من يقود النمو: النفط أم غيره؟" subtitle="نمو الناتج الحقيقي % — مشتق من الحسابات القومية (المركز الوطني للإحصاء)" datasets={datasets} table={{ columns: ['السنة', 'الناتج الكلي', 'غير النفطي', 'النفطي'], rows }}
      note="الناتج الثابت × حصة كل قطاع بالأسعار الثابتة. يتوقف عند آخر سنة تُنشر فيها حصص النشاط (2021). النفطي يتقلب مع حصص الإنتاج (اتفاقات أوبك+)، فالنمو غير النفطي هو الأقرب لمقياس قوة الاقتصاد المتنوع.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : <LineChartView series={chart} unit="%" height={320} />}
    </ChartCard>
  );
}
