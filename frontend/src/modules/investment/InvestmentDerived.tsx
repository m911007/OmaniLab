import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';

type Yearly = Map<string, number>;

/** فجوة الادخار والاستثمار (نقاط مئوية من الناتج): الادخار − الاستثمار؛ موجبة = فائض يُوظَّف في الخارج. */
export function savingGap(saving: Yearly, investment: Yearly): Yearly {
  return new Map([...saving].filter(([y]) => investment.has(y)).map(([y, v]) => [y, v - investment.get(y)!]));
}

const LINES: LineSpec[] = ['wb.NY.GNS.ICTR.ZS', 'wb.NE.GDI.TOTL.ZS'].map((indicator) => ({ indicator, entity: 'OMN' }));

/** الادخار مقابل الاستثمار وفجوتهما (مشتق من البنك الدولي). */
export function SavingInvestmentGap() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2000' });
  const { lines, gap } = useMemo(() => {
    const get = (k: string): Yearly => new Map(series.find((s) => s.id.startsWith(`${k}|`))?.points ?? []);
    const sv = get('wb.NY.GNS.ICTR.ZS');
    const inv = get('wb.NE.GDI.TOTL.ZS');
    return {
      lines: [{ id: 'sv', label: 'الادخار القومي الإجمالي', color: 'var(--series-3)', points: [...sv].sort() as [string, number][] }, { id: 'inv', label: 'تكوين رأس المال', color: 'var(--series-2)', points: [...inv].sort() as [string, number][] }] as SeriesItem[],
      gap: [{ id: 'gap', label: 'الادخار القومي − الاستثمار', color: 'var(--series-1)', points: [...savingGap(sv, inv)].sort() as [string, number][] }] as SeriesItem[],
    };
  }, [series]);
  const last = gap[0].points[gap[0].points.length - 1];
  return (
    <>
      <ChartCard wide title="الادخار والاستثمار في عُمان" subtitle="% من الناتج — البنك الدولي" datasets={datasets} note="الادخار القومي الإجمالي (بعد الدخل الصافي من الخارج والتحويلات) مقابل إجمالي تكوين رأس المال. استخدمنا القومي لا المحلي: المحلي يتجاوز الاستثمار بكثير في اقتصادات النفط لأنه قبل أرباح الشركات الأجنبية المحوَّلة للخارج.">
        {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : <LineChartView series={lines} unit="%" height={300} />}
      </ChartCard>
      <ChartCard wide title="فجوة الادخار والاستثمار" subtitle="نقاط مئوية من الناتج — مشتق (الأخضر فائض ادخار، الأحمر حاجة إلى تمويل خارجي)" datasets={datasets}
        note="الفرق بين الادخار القومي والاستثمار يقارب رصيد الحساب الجاري (2024: نحو +2.9 نقطة مقابل +3.4% منشورة من صندوق النقد)؛ والفروق الصغيرة من اختلاف المصادر والتقديرات.">
        {() => isLoading ? <Loading height={260} /> : error ? <ErrorState error={error} /> : (
          <>
            {last && <p className="muted" role="status">{last[0]}: <b className="num">{last[1] > 0 ? '+' : ''}{formatNumber(last[1], 1)}</b> نقطة من الناتج — {last[1] >= 0 ? 'ادخار يفوق الاستثمار.' : 'استثمار يفوق الادخار.'}</p>}
            <ColumnChartView series={gap} unit="% من الناتج" height={280} last={25} signColors />
          </>
        )}
      </ChartCard>
    </>
  );
}
