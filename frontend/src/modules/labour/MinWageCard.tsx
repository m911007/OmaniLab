import { formatNumber } from '@/core/format';
import { useSeries } from '@/core/hooks';

/** الحد الأدنى للأجور مقابل غلاء المعيشة: كم تآكلت قوته الشرائية منذ آخر تعديل؟ */
export function MinWageCard() {
  const wage = useSeries('ilo.min_wage', ['OMN']);
  const cpi = useSeries('wb.FP.CPI.TOTL', ['OMN']);
  const w = wage.data?.series[0]?.points ?? [];
  const c = cpi.data?.series[0]?.points ?? [];
  if (w.length < 2 || c.length < 2) {
    return null;
  }
  const last = w[w.length - 1]!;
  let since = last;
  for (let i = w.length - 1; i >= 0 && w[i]![1] === last[1]; i--) {
    since = w[i]!;
  }
  const cpiAt = (year: string) => c.find((p) => p[0] === year)?.[1];
  const base = cpiAt(since[0]);
  const now = c[c.length - 1]!;
  if (!base) {
    return null;
  }
  const loss = (1 - base / now[1]) * 100;
  return (
    <section className="card narrative" aria-label="الحد الأدنى للأجور">
      <p>
        الحد الأدنى للأجور في عُمان <strong className="num">{formatNumber(last[1], 0)}</strong> ريالًا شهريًا منذ <strong className="num">{since[0]}</strong>. وبعد تضخم تراكمي حتى <span className="num">{now[0]}</span>، فقدت هذه القيمة نحو <strong className="num">{formatNumber(loss, 1)}%</strong> من قوتها الشرائية.
      </p>
    </section>
  );
}
