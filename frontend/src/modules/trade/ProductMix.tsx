import { useMemo, useState } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { RankedBars } from '@/core/components/RankedBars';
import { Segmented } from '@/core/components/Segmented';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useRelations } from '@/core/hooks';
import { COUNTRY_NAMES } from '@/core/groups';
import { assignColors } from '@/core/colors';

type Flow = 'exports' | 'imports';

const COUNTRIES = ['OMN', 'SAU', 'ARE', 'QAT', 'KWT', 'BHR'] as const;
const THIS_YEAR = new Date().getFullYear();
const YEARS = [THIS_YEAR - 1, THIS_YEAR - 2, THIS_YEAR - 3, THIS_YEAR - 4].map(String);

/** مؤشر هيرفندال–هيرشمان للتركّز (0–10000) من حصص المنتجات: فوق 2500 يعني تركّزًا عاليًا. */
export function hhi(shares: readonly number[]): number {
  return Math.round(shares.reduce((a, s) => a + (s * 100) ** 2, 0));
}

/**
 * تركيبة التجارة حسب المنتج (فصول HS) لدولة خليجية: أكبر الفصول وحصصها، وتركّز الصادرات (حصة الأكبر وHHI).
 * يبدأ بأحدث سنة فيها بيانات لهذه الدولة، ويمكن اختيار سنة أقدم.
 */
export function ProductMix() {
  const [country, setCountry] = useState<string>('OMN');
  const [flow, setFlow] = useState<Flow>('exports');
  const [year, setYear] = useState<string | null>(null);
  const indicator = flow === 'exports' ? 'comtrade.hs_exports' : 'comtrade.hs_imports';

  // أحدث سنة ذات بيانات: تُجرَّب السنوات تنازليًا وتُؤخذ أول سنة غير فارغة.
  const probes = YEARS.map((y) => useRelations({ indicator, entity: country, period: y, direction: 'out', limit: 100 })); // eslint-disable-line react-hooks/rules-of-hooks
  const firstWithData = probes.findIndex((p) => (p.data?.count ?? 0) > 0);
  const pending = probes.some((p) => p.isLoading);
  const shownIndex = year ? YEARS.indexOf(year) : firstWithData;
  const q = shownIndex >= 0 ? probes[shownIndex] : undefined;
  const data = q?.data;

  const stats = useMemo(() => {
    if (!data) return null;
    const shares = data.items.map((i) => i.share ?? 0);
    return { top: shares[0] ?? 0, hhi: hhi(shares), total: data.sum };
  }, [data]);

  const colors = assignColors([country]);
  const items = (data?.items ?? []).slice(0, 12).map((i) => ({ key: i.entity.code, rank: i.rank, label: i.entity.name_ar ?? i.entity.name_en, value: i.value, share: i.share, color: colors[country] }));

  return (
    <ChartCard
      title="التجارة حسب المنتج"
      subtitle={`فصول النظام المنسّق (HS) — ${COUNTRY_NAMES[country]}${shownIndex >= 0 ? ` — ${YEARS[shownIndex]}` : ''}`}
      datasets={data ? [data.dataset] : []}
      note="قيم بالدولار الأمريكي كما تبلّغ بها الدولة؛ قد تختلف عن الإحصاءات الوطنية في التغطية والتقييم."
      actions={
        <Segmented
          label="التدفق"
          value={flow}
          onChange={(f) => {
            setFlow(f);
            setYear(null);
          }}
          options={[
            { value: 'exports', label: 'الصادرات' },
            { value: 'imports', label: 'الواردات' },
          ]}
        />
      }
    >
      <div className="controls">
        <label className="field">
          الدولة
          <select value={country} onChange={(e) => { setCountry(e.target.value); setYear(null); }} aria-label="الدولة">
            {COUNTRIES.map((c) => (
              <option key={c} value={c}>
                {COUNTRY_NAMES[c]}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          السنة
          <select value={year ?? (shownIndex >= 0 ? YEARS[shownIndex] : '')} onChange={(e) => setYear(e.target.value)} aria-label="السنة">
            {YEARS.map((y) => (
              <option key={y} value={y}>
                {y}
              </option>
            ))}
          </select>
        </label>
      </div>
      {pending ? (
        <Loading height={260} />
      ) : q?.error ? (
        <ErrorState error={q.error as Error} />
      ) : !data || data.count === 0 || !stats ? (
        <Empty>لا بيانات منشورة لهذه الدولة في هذه السنة.</Empty>
      ) : (
        <>
          <p style={{ marginBottom: 12 }}>
            الإجمالي <strong className="num">{formatNumber(stats.total / 1e9, 1)}</strong> مليار دولار. أكبر فصل ({items[0]?.label}) يمثّل <strong className="num">{formatNumber(stats.top * 100, 0)}%</strong>، ومؤشر التركّز HHI{' '}
            <strong className="num">{formatNumber(stats.hhi, 0)}</strong>{' '}
            <span className="muted">({stats.hhi >= 2500 ? 'تركّز عالٍ' : stats.hhi >= 1500 ? 'تركّز متوسط' : 'تنوّع جيد'})</span>.
          </p>
          <RankedBars items={items} unitLabel="دولار" />
        </>
      )}
    </ChartCard>
  );
}
