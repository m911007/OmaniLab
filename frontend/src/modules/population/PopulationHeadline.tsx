import { useMemo } from 'react';
import { ReadingGrid } from '@/core/components/ReadingGrid';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber, formatPeriod } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';
import { yoyMonthly } from '@/modules/money/derived';
import { StatLabel } from '@/core/components/IndicatorIcon';

const KEYS = ['ncsi.POP_TOTAL', 'ncsi.POP_EXPAT', 'ilo.unemployment', 'ncsi.LAB_PRIVATE_OMANI', 'ncsi.LAB_PRIVATE_EXPAT'];
const LINES: LineSpec[] = KEYS.map((indicator) => ({ indicator, entity: 'OMN' }));
const last = <T,>(a: [string, T][]) => a[a.length - 1];

/** رأس السكان والعمل: عدد السكان ونموه وحصة الوافدين والتعمين في القطاع الخاص والبطالة، وجملة تقييم من قالب ثابت. */
export function PopulationHeadline() {
  const { isLoading, error, series } = useLines(LINES, { from: String(new Date().getFullYear() - 3) });
  const v = useMemo(() => {
    const get = (k: string) => (series.find((s) => s.id.startsWith(`${k}|`))?.points ?? []) as [string, number][];
    const pop = last(get('ncsi.POP_TOTAL'));
    const expat = new Map(get('ncsi.POP_EXPAT'));
    const growth = last(yoyMonthly(get('ncsi.POP_TOTAL')));
    const un = last(get('ilo.unemployment'));
    const om = new Map(get('ncsi.LAB_PRIVATE_OMANI'));
    const ex = new Map(get('ncsi.LAB_PRIVATE_EXPAT'));
    const y = [...om.keys()].filter((p) => ex.has(p)).sort().pop();
    return {
      pop, growth, un,
      expatShare: pop && expat.has(pop[0]) ? { period: pop[0], value: (expat.get(pop[0])! / pop[1]) * 100 } : null,
      omanisation: y ? { period: y, value: (om.get(y)! / (om.get(y)! + ex.get(y)!)) * 100 } : null,
    };
  }, [series]);
  if (isLoading) return <Loading height={160} />;
  if (error) return <ErrorState error={error} />;
  const tiles = [
    { label: 'عدد السكان', value: v.pop ? formatNumber(v.pop[1] / 1e6, 2) : '—', meta: v.pop ? `M نسمة · ${formatPeriod(v.pop[0])} — المركز الوطني` : '' },
    { label: 'نمو السكان السنوي', value: v.growth ? `${formatNumber(v.growth[1], 1)}%` : '—', meta: v.growth ? `${formatPeriod(v.growth[0])} · مشتق` : '' },
    { label: 'حصة الوافدين من السكان', value: v.expatShare ? `${formatNumber(v.expatShare.value, 0)}%` : '—', meta: v.expatShare ? `${formatPeriod(v.expatShare.period)} · مشتق` : '' },
    { label: 'التعمين في القطاع الخاص', value: v.omanisation ? `${formatNumber(v.omanisation.value, 1)}%` : '—', meta: v.omanisation ? `${v.omanisation.period} · مشتق من التأمينات` : '', good: true },
    { label: 'معدل البطالة', value: v.un ? `${formatNumber(v.un[1], 1)}%` : '—', meta: v.un ? `${v.un[0]} — منظمة العمل الدولية` : '' },
  ];
  const text = v.pop && v.growth && v.expatShare ? `يبلغ عدد سكان عُمان نحو ${formatNumber(v.pop[1] / 1e6, 2)} مليون نسمة (${formatPeriod(v.pop[0])})، بنمو سنوي ${formatNumber(v.growth[1], 1)}%، ويشكّل الوافدون ${formatNumber(v.expatShare.value, 0)}% منهم${v.omanisation ? `، وتبلغ نسبة العُمانيين ${formatNumber(v.omanisation.value, 1)}% من العاملين في القطاع الخاص` : ''}.` : null;
  return (
    <>
      {text && <p className="callout fiscal-assess" role="status">{text}</p>}
      <ReadingGrid className="fiscal-tiles">
        {tiles.map((t) => (
          <div key={t.label} className="card tile">
            <StatLabel>{t.label}</StatLabel>
            <span className="stat-value num">{t.value}</span>
            <span className="stat-meta">{t.meta}</span>
          </div>
        ))}
      </ReadingGrid>
    </>
  );
}
