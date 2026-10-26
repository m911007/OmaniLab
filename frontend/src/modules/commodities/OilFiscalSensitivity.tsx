import { useMemo } from 'react';
import { CartesianGrid, LabelList, ReferenceLine, ResponsiveContainer, Scatter, ScatterChart, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartCard } from '@/core/components/ChartCard';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';
import { lastActualYear } from '@/core/lines';
import { annualMean, ols, pairByYear } from '@/core/stats';
import type { Explanation } from '@/core/explain';

const SENSITIVITY: Explanation = {
  meaning: 'هذا الرسم يسأل: عندما يرتفع سعر النفط، كم يتحسّن الرصيد المالي أو الخارجي لعُمان، وعندما ينخفض كم يتدهور؟ كل نقطة سنة واحدة، ومعها خط اتجاه إحصائي بسيط.',
  shows: 'المحور الأفقي متوسط سعر نفط عُمان في السنة، والمحور الرأسي رصيد الميزانية (أو الحساب الجاري) كنسبة من الناتج. النقاط تحمل أرقام السنوات، والخط المتقطع أفضل خط يمرّ بين النقاط.',
  read: ['ميل الخط الصاعد يعني أن أسعار النفط الأعلى ترتبط بأرصدة أفضل.', 'الرقم في الجملة فوق الرسم هو التغيّر المتوقع في الرصيد (بنقطة مئوية من الناتج) مقابل كل 10 دولارات.', 'ابتعاد النقطة عن الخط يعني أن عوامل أخرى (إنتاج، إنفاق، إصلاحات) غيّرت النتيجة في تلك السنة.', 'معامل التحديد (R²) قريب من 1 يعني علاقة قوية، وقريب من 0 علاقة ضعيفة لا يُعوَّل عليها.'], 
  why: 'يبيّن مدى اعتماد المالية العامة والحساب الجاري على سعر النفط، وما مقدار الصدمة التي يحتملها الاقتصاد قبل أن تتحول الميزانية إلى عجز.',
  terms: [{ term: 'الانحدار', definition: 'طريقة إحصائية ترسم أفضل خط مستقيم بين نقاط لوصف العلاقة بين متغيرين.' }, { term: 'معامل التحديد R²', definition: 'نسبة تغيّر الرصيد التي يفسّرها سعر النفط وحده؛ بين 0 و1.' }, { term: 'سعر التعادل', definition: 'السعر التقريبي الذي يصبح عنده الرصيد صفرًا (لا عجز ولا فائض).' }],
  caution: 'علاقة وصفية تاريخية لا توقع ولا سببية: تتغير حين تتغير السياسات أو الإنتاج.',
};

interface Props {
  title: string;
  /** المؤشر الذي يتأثر بسعر النفط (رصيد الميزانية، الحساب الجاري…) */
  indicator: string;
  yLabel: string;
}

const LAST = Number(lastActualYear());

/**
 * حساسية مؤشر عُماني لسعر النفط: كل سنة نقطة (متوسط سعر نفط عُمان السنوي، قيمة المؤشر)، وخط انحدار بالمربعات الصغرى.
 * تقدير إحصائي وصفي للعلاقة التاريخية، لا توقع ولا علاقة سببية؛ ويُعرض معامل التحديد وعدد السنوات ليحكم القارئ.
 */
export function OilFiscalSensitivity({ title, indicator, yLabel }: Props) {
  const lines = useMemo<LineSpec[]>(
    () => [
      { indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'نفط عُمان' },
      { indicator, entity: 'OMN', label: yLabel },
    ],
    [indicator, yLabel],
  );
  const { isLoading, error, series, datasets } = useLines(lines, { from: '2005' });

  const fit = useMemo(() => {
    const oil = series.find((s) => s.id.startsWith('ncsi.OIL_PRICE'));
    const y = series.find((s) => s.id.startsWith(indicator));
    if (!oil || !y) return null;
    const pairs = pairByYear(annualMean(oil.points), y.points).filter(([year]) => Number(year) <= LAST);
    const reg = ols(pairs.map((p) => p[1]), pairs.map((p) => p[2]));
    return reg ? { pairs, reg } : null;
  }, [series, indicator]);

  const table = fit && {
    columns: ['السنة', 'متوسط سعر نفط عُمان (دولار/برميل)', yLabel],
    rows: fit.pairs.map(([year, x, y]) => [year, formatNumber(x, 1), formatNumber(y, 1)]),
  };

  const { reg, pairs } = fit ?? {};
  const xs = pairs?.map((p) => p[1]) ?? [];
  const x0 = Math.min(...xs);
  const x1 = Math.max(...xs);
  const breakeven = reg && reg.slope > 0 && reg.r2 >= 0.4 ? -reg.intercept / reg.slope : null;

  return (
    <ChartCard
      title={title}
      subtitle="كل نقطة سنة؛ المحور الأفقي متوسط سعر نفط عُمان السنوي"
      datasets={datasets}
      table={table ?? undefined}
      note="تقدير وصفي للعلاقة التاريخية، لا توقعًا ولا علاقة سببية."
      explain={SENSITIVITY}
    >
      {isLoading ? (
        <Loading height={300} />
      ) : error ? (
        <ErrorState error={error} />
      ) : !fit || !reg || !pairs ? (
        <Empty />
      ) : (
        <>
          <p style={{ marginBottom: 8 }}>
            كل <strong className="num">10</strong> دولارات في سعر النفط ارتبطت تاريخيًا بتغيّر قدره <strong className="num">{formatNumber(reg.slope * 10, 1)}</strong> نقطة مئوية في «{yLabel}» (معامل التحديد{' '}
            <span className="num">{formatNumber(reg.r2, 2)}</span>، <span className="num">{reg.n}</span> سنة).
            {breakeven !== null && breakeven > 0 && (
              <>
                {' '}
                السعر الذي يقترب عنده المؤشر من الصفر نحو <strong className="num">{formatNumber(breakeven, 0)}</strong> دولارًا للبرميل.
              </>
            )}
          </p>
          <div className="chart-wrap" style={{ height: 300 }} role="img" aria-label={`${title}: انحدار ${yLabel} على سعر النفط`}>
            <ResponsiveContainer>
              <ScatterChart margin={{ top: 12, right: 24, bottom: 18, left: 0 }}>
                <CartesianGrid stroke="var(--grid)" />
                <XAxis type="number" dataKey="x" name="سعر النفط" unit="" domain={['auto', 'auto']} stroke="var(--text-muted)" tick={{ fontSize: 12 }} tickLine={false} label={{ value: 'دولار/برميل', position: 'insideBottom', offset: -10, fontSize: 12, fill: 'var(--text-muted)' }} />
                <YAxis type="number" dataKey="y" name={yLabel} domain={['auto', 'auto']} stroke="var(--text-muted)" tick={{ fontSize: 12 }} tickLine={false} axisLine={false} width={44} />
                <ReferenceLine y={0} stroke="var(--text-muted)" />
                <ReferenceLine segment={[{ x: x0, y: reg.intercept + reg.slope * x0 }, { x: x1, y: reg.intercept + reg.slope * x1 }]} stroke="var(--series-2)" strokeWidth={2} strokeDasharray="5 4" />
                <Tooltip cursor={{ strokeDasharray: '3 3' }} content={<ScatterTip yLabel={yLabel} />} />
                <Scatter data={pairs.map(([year, x, y]) => ({ year, x, y }))} fill="var(--series-1)">
                  <LabelList dataKey="year" position="top" style={{ fontSize: 11, fill: 'var(--text-secondary)' }} />
                </Scatter>
              </ScatterChart>
            </ResponsiveContainer>
          </div>
        </>
      )}
    </ChartCard>
  );
}

function ScatterTip({ active, payload, yLabel }: { active?: boolean; payload?: { payload: { year: string; x: number; y: number } }[]; yLabel: string }) {
  if (!active || !payload?.length) return null;
  const p = payload[0].payload;
  return (
    <div className="tooltip">
      <div className="t-head">{p.year}</div>
      <div className="t-row"><span className="t-name">نفط عُمان</span><strong className="num">{formatNumber(p.x, 1)}</strong></div>
      <div className="t-row"><span className="t-name">{yLabel}</span><strong className="num">{formatNumber(p.y, 1)}</strong></div>
    </div>
  );
}
