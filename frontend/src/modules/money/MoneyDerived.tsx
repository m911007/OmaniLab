import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber, formatPeriod } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';
import { difference, ratio, yoyMonthly, type Series } from './derived';

const L = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });
const KEYS = ['ncsi.MON_CREDIT_PRIVATE', 'ncsi.MON_M2', 'ncsi.MON_M1', 'ncsi.MON_QUASI_MONEY', 'ncsi.MON_RATE_LENDING_RO', 'ncsi.MON_RATE_DEPOSIT_TIME_RO', 'ncsi.MON_RATE_REPO', 'ncsi.CPI_INFLATION'];
const LINES = KEYS.map(L);

function useMoney() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2012' });
  const get = useMemo(() => (k: string): Series => series.find((s) => s.id.startsWith(`${k}|`))?.points ?? [], [series]);
  return { isLoading, error, datasets, get };
}
const cut = (s: Series, from: string): Series => s.filter(([p]) => p >= from);
const lastOf = (s: Series) => s[s.length - 1];

/** وتيرة الائتمان والنقد: التغيّر السنوي % لائتمان القطاع الخاص وM2 وM1 (مشتق من الأرصدة الشهرية)، فالتسارع أو التباطؤ يظهر قبل الأرصدة. */
export function CreditMoneyGrowth() {
  const { isLoading, error, datasets, get } = useMoney();
  const out = useMemo<SeriesItem[]>(() => [
    { id: 'credit', label: 'ائتمان القطاع الخاص', color: 'var(--series-1)', points: cut(yoyMonthly(get('ncsi.MON_CREDIT_PRIVATE')), '2014-01') },
    { id: 'm2', label: 'النقد الواسع M2', color: 'var(--series-3)', points: cut(yoyMonthly(get('ncsi.MON_M2')), '2014-01') },
    { id: 'm1', label: 'النقد الضيق M1', color: 'var(--series-2)', points: cut(yoyMonthly(get('ncsi.MON_M1')), '2014-01') },
  ], [get]);
  const lc = lastOf(out[0].points);
  const lm = lastOf(out[1].points);
  return (
    <ChartCard wide title="وتيرة الائتمان والنقد" subtitle="التغيّر % عن الشهر نفسه قبل سنة — مشتق من الأرصدة الشهرية (المركز الوطني للإحصاء)" datasets={datasets}
      table={{ columns: ['الشهر', ...out.map((s) => s.label)], rows: [...new Set(out.flatMap((s) => s.points.map((p) => p[0])))].sort().reverse().slice(0, 60).map((p) => [formatPeriod(p), ...out.map((s) => { const v = s.points.find((x) => x[0] === p)?.[1]; return v === undefined ? '—' : `${formatNumber(v, 1)}%`; })]) }}
      note="نمو الائتمان أسرع من النقد الواسع يعني أن البنوك تُقرض نسبة أكبر مما تجمع؛ والعكس يعني سيولة متراكمة دون إقراض. أرقام شهرية تتأثر بالمواسم فالاتجاه أهم من القفزة الواحدة.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : (
        <>
          {lc && lm && <p className="muted" role="status">{formatPeriod(lc[0])}: الائتمان <b className="num">{formatNumber(lc[1], 1)}%</b> والنقد الواسع <b className="num">{formatNumber(lm[1], 1)}%</b> — {lc[1] > lm[1] ? 'الإقراض أسرع من نمو النقد.' : 'النقد أسرع من الإقراض.'}</p>}
          <LineChartView series={out} unit="%" height={320} />
        </>
      )}
    </ChartCard>
  );
}

/** كلفة الإقراض: سعر الإقراض الاسمي والفائدة الحقيقية (الإقراض − التضخم) وهامش الإقراض فوق الودائع الزمنية. */
export function RealRates() {
  const { isLoading, error, datasets, get } = useMoney();
  const out = useMemo<SeriesItem[]>(() => {
    const lending = get('ncsi.MON_RATE_LENDING_RO');
    return [
      { id: 'nominal', label: 'سعر الإقراض الاسمي', color: 'var(--series-2)', points: cut(lending, '2014-01') },
      { id: 'real', label: 'الفائدة الحقيقية (الإقراض − التضخم)', color: 'var(--series-1)', points: cut(difference(lending, get('ncsi.CPI_INFLATION')), '2014-01') },
      { id: 'spread', label: 'هامش الإقراض فوق الودائع', color: 'var(--series-4)', points: cut(difference(lending, get('ncsi.MON_RATE_DEPOSIT_TIME_RO')), '2014-01') },
    ];
  }, [get]);
  const last = lastOf(out[1].points);
  return (
    <ChartCard wide title="الفائدة الحقيقية وهامش الإقراض" subtitle="% — مشتق: سعر الإقراض، التضخم، سعر الودائع الزمنية (المركز الوطني للإحصاء)" datasets={datasets}
      note="الفائدة الحقيقية = سعر الإقراض الاسمي − التضخم السنوي: ارتفاعها يعني كلفة اقتراض فعلية أعلى على المقترض. هامش الإقراض هو الفرق بين سعر الإقراض وسعر الودائع الزمنية، وهو مؤشر تقريبي لربحية الوساطة المصرفية.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : (
        <>
          {last && <p className="muted" role="status">{formatPeriod(last[0])}: الفائدة الحقيقية <b className="num">{formatNumber(last[1], 2)}%</b> — {last[1] > 0 ? 'كلفة الاقتراض الفعلية موجبة.' : 'سالبة: التضخم يفوق كلفة الاقتراض.'}</p>}
          <LineChartView series={out} unit="%" height={320} />
        </>
      )}
    </ChartCard>
  );
}

/** الائتمان إلى النقد الواسع %: حصة النقد التي تحوّلت قروضًا للقطاع الخاص. */
export function CreditToMoney() {
  const { isLoading, error, datasets, get } = useMoney();
  const out = useMemo<SeriesItem[]>(() => [{ id: 'ratio', label: 'الائتمان ÷ النقد الواسع', color: 'var(--series-1)', points: cut(ratio(get('ncsi.MON_CREDIT_PRIVATE'), get('ncsi.MON_M2')), '2008-01') }], [get]);
  return (
    <ChartCard wide title="الائتمان إلى النقد الواسع" subtitle="% — مشتق من الأرصدة الشهرية (المركز الوطني للإحصاء)" datasets={datasets}
      note="النسبة المرتفعة تعني أن جزءًا كبيرًا من أموال الاقتصاد مُقرَض للقطاع الخاص (عمق وساطة أعلى لكن مخاطر أعلى)، والمنخفضة سيولة غير موظّفة.">
      {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : <LineChartView series={out} unit="%" height={280} />}
    </ChartCard>
  );
}
