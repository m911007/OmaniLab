import { useQueries } from '@tanstack/react-query';
import { api, type SeriesResponse } from '@/core/api';
import { Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { lastActualYear } from '@/core/lines';

const ROWS = [
  { key: 'imf.NGDP_RPCH', label: 'نمو الناتج الحقيقي', unit: '%' },
  { key: 'imf.PCPIPCH', label: 'التضخم', unit: '%' },
  { key: 'imf.GGXCNL_NGDP', label: 'رصيد الميزانية', unit: '% من الناتج' },
  { key: 'imf.GGXWDG_NGDP', label: 'الدين الحكومي', unit: '% من الناتج' },
  { key: 'imf.BCA_NGDPD', label: 'الحساب الجاري', unit: '% من الناتج' },
];

/** ماذا بعد؟ آخر سنة فعلية وتوقعات صندوق النقد للسنتين التاليتين — توقعات رسمية تتغير مع كل إصدار. */
export function ForecastStrip() {
  const last = Number(lastActualYear());
  const years = [last, last + 1, last + 2].map(String);
  const results = useQueries({
    queries: ROWS.map((r) => ({
      queryKey: ['series', r.key, 'OMN', String(last), undefined],
      queryFn: ({ signal }: { signal: AbortSignal }) => api<SeriesResponse>('observations', { indicator: r.key, entities: 'OMN', from: String(last) }, signal),
      staleTime: 10 * 60_000,
    })),
  });
  if (results.some((r) => r.isLoading)) return <Loading height={180} />;

  return (
    <div className="card">
      <div className="table-scroll">
        <table className="forecast-table">
          <thead>
            <tr><th />{years.map((y, i) => <th key={y} className="num">{y}<small>{i === 0 ? 'فعلي' : 'توقع'}</small></th>)}</tr>
          </thead>
          <tbody>
            {ROWS.map((r, ri) => {
              const pts = new Map(results[ri].data?.series[0]?.points ?? []);
              return (
                <tr key={r.key}>
                  <th scope="row">{r.label}<small className="muted">{r.unit}</small></th>
                  {years.map((y, i) => <td key={y} className={`num${i > 0 ? ' forecast' : ''}`}>{pts.has(y) ? formatNumber(pts.get(y)!, 1) : '—'}</td>)}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <p className="source-note">توقعات صندوق النقد الدولي (آفاق الاقتصاد العالمي) كما نُشرت؛ ليست توقعات المنصة، وتتغير مع كل إصدار.</p>
    </div>
  );
}
