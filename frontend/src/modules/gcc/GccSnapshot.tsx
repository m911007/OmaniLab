import { useQueries } from '@tanstack/react-query';
import { api, type Dataset, type LatestResponse } from '@/core/api';
import { ChartCard } from '@/core/components/ChartCard';
import { Loading } from '@/core/components/States';
import { GCC_CODES } from '@/core/colors';
import { entityName, formatPeriod, formatValue } from '@/core/format';

export interface SnapshotRow {
  indicator: string;
  label: string;
  maxPeriod?: string;
  /** ما يُعدّ الأفضل لتظليل الأعلى/الأدنى؛ بلا تحديد لا تظليل */
  better?: 'high' | 'low';
}

/** جدول لقطة: كل مؤشر في صف وكل دولة خليجية في عمود، مع إبراز الأفضل بنص (لا باللون وحده). */
export function GccSnapshot({ rows }: { rows: SnapshotRow[] }) {
  const queries = useQueries({
    queries: GCC_CODES.map((code) => ({
      queryKey: ['latest', code, rows.map((r) => r.indicator).join(','), rows.map((r) => r.maxPeriod).join(',')],
      // maxPeriod يختلف لكل مؤشر، فنطلب كل مؤشر منفردًا ضمن الاستعلام نفسه
      queryFn: async ({ signal }: { signal: AbortSignal }) => {
        const parts = await Promise.all(rows.map((r) => api<LatestResponse>(`entities/${code}/latest`, { indicators: r.indicator, max_period: r.maxPeriod }, signal)));
        return { entity: parts[0].entity, items: parts.flatMap((p) => p.items) };
      },
      staleTime: 5 * 60_000,
    })),
  });

  if (queries.some((q) => q.isLoading)) return <Loading height={260} />;

  const datasets: Dataset[] = queries.flatMap((q) => q.data?.items.map((i) => i.dataset) ?? []);

  return (
    <ChartCard title="لقطة مقارنة: آخر قيمة لكل دولة" datasets={datasets} note="الفترة تختلف بين المؤشرات؛ تُعرض آخر فترة فعلية متاحة لكل دولة.">
      <div className="table-scroll">
        <table className="wide">
          <thead>
            <tr>
              <th>المؤشر</th>
              {queries.map((q, i) => (
                <th key={GCC_CODES[i]}>{q.data ? entityName(q.data.entity) : GCC_CODES[i]}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((r) => {
              const cells = queries.map((q) => q.data?.items.find((i) => i.indicator.key === r.indicator));
              const vals = cells.map((c) => c?.value).filter((v): v is number => v !== undefined);
              const best = r.better ? (r.better === 'high' ? Math.max(...vals) : Math.min(...vals)) : undefined;
              const unit = cells.find(Boolean)?.indicator.unit;
              return (
                <tr key={r.indicator}>
                  <td>
                    {r.label}
                    {unit && <span className="muted"> ({unit})</span>}
                  </td>
                  {cells.map((c, i) => (
                    <td key={GCC_CODES[i]} className="num" style={{ fontWeight: c && best === c.value ? 800 : 400 }}>
                      {c ? (
                        <>
                          {formatValue(c.value, c.indicator.unit)}
                          {best === c.value && vals.length > 1 && <span aria-label="الأفضل"> ★</span>}
                          <div className="muted" style={{ fontSize: 11 }}>{formatPeriod(c.period)}</div>
                        </>
                      ) : (
                        '—'
                      )}
                    </td>
                  ))}
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <p className="muted" style={{ fontSize: 12, marginTop: 8 }}>★ الأفضل بين دول الخليج في المؤشر (الأعلى أو الأدنى بحسب طبيعته).</p>
    </ChartCard>
  );
}
