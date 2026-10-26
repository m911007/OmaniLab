import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router-dom';
import { api } from '@/core/api';
import { Loading } from '@/core/components/States';
import { formatDate, formatNumber, formatPeriod } from '@/core/format';

interface Correction {
  indicator_key: string;
  name_ar: string | null;
  entity_code: string;
  period: string;
  status: 'applied' | 'excluded' | 'suspected' | 'dismissed';
  original: number;
  corrected: number | null;
  note: string;
  detected_at: string;
}

const STATUS: Record<Correction['status'], { cls: string; label: string }> = {
  applied: { cls: 'good', label: '✓ صُحّح' },
  excluded: { cls: 'critical', label: '✕ مستبعد' },
  dismissed: { cls: 'neutral', label: '● لا خطأ' },
  suspected: { cls: 'warning', label: '! مشتبه به' },
};

/** سجل التصحيحات: ما صُحّح آليًا، وما استُبعد لخطأ في المصدر، وما رُوجع وثبتت صحته، وما ينتظر المراجعة (لعُمان والمؤشرات العالمية). */
export function CorrectionsLog() {
  const { data, isLoading } = useQuery({
    queryKey: ['corrections'],
    queryFn: ({ signal }) => api<{ data: Correction[] }>('corrections', {}, signal).then((r) => r.data),
    staleTime: 10 * 60_000,
  });
  if (isLoading) return <Loading height={120} />;
  if (!data?.length) return <p className="muted">لا تصحيحات مسجّلة حاليًا.</p>;
  const count = (st: Correction['status']) => data.filter((c) => c.status === st).length;

  return (
    <div className="card corrections">
      <h3 className="card-title">سجل التصحيحات</h3>
      <p className="muted">{count('applied')} نقطة صُحّحت آليًا (انقطاع وحدة)، و{count('excluded')} استُبعدت لخطأ في المصدر نفسه (بلا قيمة بديلة مختلقة)، و{count('dismissed')} رُوجعت وثبتت صحتها، و{count('suspected')} تنتظر المراجعة.</p>
      <div className="table-scroll" style={{ maxHeight: 360, overflowY: 'auto' }}>
        <table className="wide">
          <thead><tr><th>المؤشر</th><th>الفترة</th><th>الحالة</th><th>كما نُشر</th><th>بعد التصحيح</th><th>السبب</th><th>رُصد</th></tr></thead>
          <tbody>
            {data.map((c) => (
              <tr key={`${c.indicator_key}-${c.entity_code}-${c.period}`}>
                <td><Link to={`/indicator/${c.indicator_key}?entity=${c.entity_code}`}>{c.name_ar ?? c.indicator_key}</Link> <small className="muted">{c.entity_code}</small></td>
                <td className="num">{formatPeriod(c.period)}</td>
                <td><span className={`chip ${STATUS[c.status].cls}`}>{STATUS[c.status].label}</span></td>
                <td className="num">{formatNumber(c.original, 3)}</td>
                <td className="num">{c.corrected == null ? '—' : formatNumber(c.corrected, 1)}</td>
                <td><small>{c.note}</small></td>
                <td><small>{formatDate(c.detected_at)}</small></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
