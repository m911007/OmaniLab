import { useMemo, useState } from 'react';
import { Bar, BarChart, CartesianGrid, Cell, LabelList, ReferenceLine, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { ChartCard } from '@/core/components/ChartCard';
import { PeriodSlider } from '@/core/components/PeriodSlider';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { axisNumber, formatNumber } from '@/core/format';
import { CATEGORIES } from './GovSpending';
import { MOF_KEYS, useFiscalData } from './useFiscalData';

interface Step {
  label: string;
  range: [number, number];
  value: number;
  kind: 'total' | 'down' | 'result';
}

const SHORT: Record<string, string> = { 'الدفاع والأمن': 'الدفاع', 'الوزارات المدنية': 'المدنية', 'خدمة الدين وأخرى': 'الدين وأخرى', 'الاستثمار والتنمية': 'الاستثمار', 'المساهمات والدعم': 'الدعم' };

/** خطوات الجسر: إيراد ثم خصم كل وجه صرف ثم الرصيد؛ كل شريط عائم بين مستويين (يتحمّل العبور تحت الصفر). */
export function bridgeSteps(revenue: number, spending: { label: string; value: number }[]): Step[] {
  const steps: Step[] = [{ label: 'الإيرادات', range: [0, revenue], value: revenue, kind: 'total' }];
  let running = revenue;
  for (const s of spending) {
    steps.push({ label: s.label, range: [running - s.value, running], value: -s.value, kind: 'down' });
    running -= s.value;
  }
  steps.push({ label: running >= 0 ? 'الفائض' : 'العجز', range: running >= 0 ? [0, running] : [running, 0], value: running, kind: 'result' });
  return steps;
}

const fill = (s: Step) => (s.kind === 'total' ? 'var(--series-3)' : s.kind === 'down' ? 'var(--series-2)' : s.value >= 0 ? 'var(--good)' : 'var(--critical)');

/** من الإيراد إلى الرصيد: جسر (Waterfall) لسنة يختارها القارئ بشريط تمرير، يبيّن أين ذهب كل ريال من الإيراد. */
export function FiscalBridge() {
  const { isLoading, error, m, datasets } = useFiscalData(MOF_KEYS);
  const years = useMemo(() => [...m['mof.rev_total'].keys()].filter((y) => CATEGORIES.every((c) => m[c.key as 'mof.exp_defense'].has(y))).sort(), [m]);
  const [picked, setPicked] = useState<string | null>(null);
  const year = picked && years.includes(picked) ? picked : years[years.length - 1];
  const steps = useMemo(() => year ? bridgeSteps(m['mof.rev_total'].get(year)!, CATEGORIES.map((c) => ({ label: SHORT[c.label] ?? c.label, value: m[c.key as 'mof.exp_defense'].get(year)! }))) : [], [m, year]);

  return (
    <ChartCard wide title="من الإيراد إلى الرصيد" subtitle="مليار ريال عُماني — وزارة المالية (الحساب الختامي)" datasets={datasets}
      note="كل شريط عائم يبدأ من مستوى ما تبقّى من الإيراد وينتهي بعد خصم الوجه. الأخضر/الأحمر هو الرصيد النهائي (فائض/عجز)."
      table={steps.length ? { columns: ['البند', 'القيمة (مليار ر.ع)'], rows: steps.map((s) => [s.label, formatNumber(s.value, 2)]) } : undefined}>
      {() => isLoading ? <Loading height={320} /> : error ? <ErrorState error={error} /> : !year ? <Empty /> : (
        <>
          <PeriodSlider periods={years} current={year} onChange={setPicked} />
          <div className="chart-wrap" style={{ height: 320 }} role="img" aria-label={`جسر الإيراد إلى الرصيد ${year}`}>
            <ResponsiveContainer>
              <BarChart data={steps} margin={{ top: 22, right: 12, bottom: 0, left: 0 }}>
                <CartesianGrid vertical={false} stroke="var(--grid)" />
                <XAxis dataKey="label" stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={{ stroke: 'var(--border)' }} interval={0} />
                <YAxis tickFormatter={axisNumber} stroke="var(--text-muted)" tick={{ fontSize: 13 }} tickLine={false} axisLine={false} width={44} />
                <ReferenceLine y={0} stroke="var(--text-muted)" />
                <Tooltip formatter={(_v: unknown, _n: string, item: { payload?: Step }) => [`${formatNumber(item.payload?.value ?? 0, 2)} مليار ر.ع`, item.payload?.label ?? '']} cursor={{ fill: 'var(--surface-2)', opacity: 0.6 }} contentStyle={{ background: 'var(--surface-1)', border: '1px solid var(--border)', borderRadius: 8 }} />
                <Bar dataKey="range" radius={4} isAnimationActive={false}>
                  {steps.map((s) => <Cell key={s.label} fill={fill(s)} />)}
                  <LabelList dataKey="value" position="top" formatter={(v: number) => formatNumber(v, 2)} fill="var(--text-primary)" fontSize={12} />
                </Bar>
              </BarChart>
            </ResponsiveContainer>
          </div>
        </>
      )}
    </ChartCard>
  );
}
