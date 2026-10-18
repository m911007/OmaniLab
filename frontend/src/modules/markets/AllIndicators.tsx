import { useMemo, useState } from 'react';
import { Loading } from '@/core/components/States';
import { formatNumber, formatPeriod, formatValue } from '@/core/format';
import { useEntityIndicators } from '@/core/hooks';

/** كل مؤشرات الدولة المتاحة بآخر قيمة، مجمَّعة حسب المجموعة، مع بحث. */
export function AllIndicators({ code, name }: { code: string; name: string }) {
  const { data, isLoading } = useEntityIndicators(code);
  const [q, setQ] = useState('');
  const groups = useMemo(() => {
    const term = q.trim().toLowerCase();
    const map = new Map<string, { name: string; rows: NonNullable<typeof data>['data'] }>();
    for (const r of data?.data ?? []) {
      if (term && !`${r.name_ar} ${r.key}`.toLowerCase().includes(term)) continue;
      const g = map.get(r.dataset.key) ?? { name: r.dataset.name_ar, rows: [] };
      g.rows.push(r);
      map.set(r.dataset.key, g);
    }
    return [...map.entries()].sort((a, b) => b[1].rows.length - a[1].rows.length);
  }, [data, q]);
  const total = data?.data.length ?? 0;

  return (
    <section className="section card" aria-labelledby="all-ind-h">
      <div className="card-head" style={{ flexWrap: 'wrap', gap: 10 }}>
        <h2 id="all-ind-h">كل مؤشرات {name} ({formatNumber(total, 0)})</h2>
        <input type="search" placeholder="ابحث في المؤشرات…" value={q} onChange={(e) => setQ(e.target.value)} aria-label="بحث" />
      </div>
      {isLoading && <Loading height={160} />}
      {groups.map(([key, g]) => (
        <details key={key} className="ind-group" open={q.trim() !== '' || groups.length === 1}>
          <summary>
            {g.name} <span className="muted num">({g.rows.length})</span>
          </summary>
          <div className="table-scroll">
            <table>
              <tbody>
                {g.rows.map((r) => (
                  <tr key={r.key}>
                    <td>{r.name_ar}</td>
                    <td className="num">{formatValue(r.value, r.unit)}{r.unit ? <span className="muted"> {r.unit}</span> : null}</td>
                    <td className="muted num">{formatPeriod(r.period)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </details>
      ))}
      {!isLoading && groups.length === 0 && <p className="muted">لا نتائج.</p>}
    </section>
  );
}
