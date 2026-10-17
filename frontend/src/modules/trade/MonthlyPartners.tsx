import { Loading } from '@/core/components/States';
import { compact, entityName, formatPeriod } from '@/core/format';
import { useLatest, useRelations } from '@/core/hooks';

function Side({ indicator, period, title }: { indicator: string; period: string; title: string }) {
  const { data, isLoading } = useRelations({ indicator, entity: 'OMN', period, limit: 8 });
  return (
    <div className="card">
      <h3>{title}</h3>
      {isLoading && <Loading height={160} />}
      {data && (
        <ol className="plain-list">
          {data.items.map((i) => (
            <li key={i.entity.code}>
              {entityName(i.entity)} — <span className="num">{compact(i.value)}</span> دولار {i.share != null && <span className="muted">(<span className="num">{Math.round(i.share * 100)}%</span>)</span>}
            </li>
          ))}
        </ol>
      )}
    </div>
  );
}

/** أكبر الشركاء في آخر شهر تتوافر فيه أرقام صندوق النقد. */
export function MonthlyPartners() {
  const { data } = useLatest('OMN', ['imf.imts_exports']);
  const period = data?.items[0]?.period ?? '';
  if (!period) {
    return null;
  }
  return (
    <div className="grid cols-2" style={{ gridColumn: '1 / -1' }}>
      <Side indicator="imf.imts_exports_partner" period={period} title={`أكبر وجهات الصادرات — ${formatPeriod(period)}`} />
      <Side indicator="imf.imts_imports_partner" period={period} title={`أكبر مصادر الواردات — ${formatPeriod(period)}`} />
    </div>
  );
}
