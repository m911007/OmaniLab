import { useMemo } from 'react';
import { useSeries } from '@/core/hooks';
import { formatNumber } from '@/core/format';
import { BORROWING_NOTE, BORROWING_RATE, gradeColor, isInvestmentGrade, OMAN_RATINGS, RATING_YEARS } from './ratingData';

const AGENCIES: { key: 'sp' | 'moodys' | 'fitch'; label: string }[] = [
  { key: 'sp', label: 'S&P Global' },
  { key: 'moodys', label: "Moody's" },
  { key: 'fitch', label: 'Fitch' },
];

/** التصنيف الائتماني السيادي لعُمان: آخر تقييم لكل وكالة وخريطة حرارية لمساره مع الدين الحكومي وكلفة الاقتراض الدولي. */
export function CreditRatings() {
  const debt = useSeries('imf.GGXWDG_NGDP', ['OMN'], { from: RATING_YEARS[0] });
  const debtByYear = useMemo(() => new Map((debt.data?.series[0]?.points ?? []).map((p) => [p[0], p[1]])), [debt.data]);
  const latest = AGENCIES.map((a) => ({ ...a, rating: [...OMAN_RATINGS[a.key]].reverse().find((r) => r[0]) ?? [null, null] }));
  const lastRate = [...BORROWING_RATE].reverse().find((v) => v != null);
  const lastRateNote = BORROWING_NOTE[BORROWING_RATE.lastIndexOf(lastRate ?? null)];
  const cols = [...RATING_YEARS.keys()].reverse();

  return (
    <section className="section" aria-labelledby="cr-h">
      <h2 id="cr-h">التصنيف الائتماني السيادي</h2>
      <p className="lead">كيف تقيّم أكبر ثلاث وكالات عالمية قدرة عُمان على سداد ديونها، وكلفة اقتراضها الدولي. الدرجة الاستثمارية (BBB-/Baa3 فأعلى) تخفض كلفة التمويل وتفتح مستثمرين أكثر.</p>
      <div className="grid cols-4 insight-tiles">
        {latest.map((a) => (
          <div key={a.key} className="card tile" style={{ borderInlineStartColor: gradeColor(a.rating[0]) }}>
            <span className="stat-label">{a.label}</span>
            <span className="stat-value" dir="ltr">{a.rating[0] ?? '—'}</span>
            <span className="stat-meta">{a.rating[1] ? `نظرة ${a.rating[1]} · ` : ''}{isInvestmentGrade(a.rating[0]) ? 'درجة استثمارية' : 'درجة مضاربة'}</span>
          </div>
        ))}
        <div className="card tile">
          <span className="stat-label">فائدة آخر سند دولي</span>
          <span className="stat-value num">{lastRate != null ? `${formatNumber(lastRate, 3)}%` : '—'}</span>
          <span className="stat-meta">{lastRateNote ?? ''}</span>
        </div>
      </div>

      <div className="card heat-wrap">
        <div className="heat-scroll">
          <table className="heat-table">
            <thead>
              <tr>
                <th />
                {cols.map((i) => <th key={i} className="num">{RATING_YEARS[i]}</th>)}
              </tr>
            </thead>
            <tbody>
              {AGENCIES.map((a) => (
                <tr key={a.key}>
                  <th scope="row">{a.label}</th>
                  {cols.map((i) => {
                    const [grade, outlook] = OMAN_RATINGS[a.key][i] ?? [null, null];
                    return (
                      <td key={i} style={{ background: gradeColor(grade) }} title={`${a.label} ${RATING_YEARS[i]}: ${grade ?? 'غير متاح'}${outlook ? ` (نظرة ${outlook})` : ''}`}>
                        <strong dir="ltr">{grade ?? '—'}</strong>
                        <small>{outlook ?? ''}</small>
                      </td>
                    );
                  })}
                </tr>
              ))}
              <tr>
                <th scope="row">الدين الحكومي</th>
                {cols.map((i) => {
                  const v = debtByYear.get(RATING_YEARS[i]!);
                  return <td key={i} className="plain num">{v != null ? `${formatNumber(v, 0)}%` : '—'}<small>من الناتج</small></td>;
                })}
              </tr>
              <tr>
                <th scope="row">فائدة السند الدولي</th>
                {cols.map((i) => (
                  <td key={i} className="plain num" title={BORROWING_NOTE[i] ?? 'لا إصدار دولي مؤكَّد'}>{BORROWING_RATE[i] != null ? `${formatNumber(BORROWING_RATE[i]!, 2)}%` : '—'}</td>
                ))}
              </tr>
            </tbody>
          </table>
        </div>
        <p className="source-note">الأحمر درجة مضاربة، والتركوازي درجة استثمارية. انتقلت الوكالات الثلاث بعُمان من المضاربة إلى الدرجة الاستثمارية بين 2023 و2025 مع تراجع الدين وتحسّن المالية العامة. المصدر: البيانات الصحفية لـS&P وMoody's وFitch (تجميع يدوي حتى 2025)؛ الدين من صندوق النقد.</p>
      </div>
    </section>
  );
}
