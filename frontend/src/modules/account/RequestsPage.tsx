import { useEffect, useState } from 'react';
import { Link, Navigate } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { api, apiSend, type ChartRequestRow } from '@/core/api';
import { useAuth } from '@/core/auth';
import { formatDateTime } from '@/core/format';

const LABEL: Record<ChartRequestRow['status'], string> = { pending: 'بانتظار الوكيل', in_progress: 'قيد التنفيذ', done: 'نُفِّذ', rejected: 'مرفوض' };
const TONE: Record<ChartRequestRow['status'], string> = { pending: 'warning', in_progress: 'warning', done: 'good', rejected: 'critical' };

export default function RequestsPage() {
  const { user, ready } = useAuth();
  const [rows, setRows] = useState<ChartRequestRow[] | null>(null);
  const [filter, setFilter] = useState<ChartRequestRow['status'] | 'all'>('all');

  const load = () => api<{ data: ChartRequestRow[] }>('chart-requests').then((r) => setRows(r.data));
  useEffect(() => {
    if (user) {
      void load();
    }
  }, [user]);

  if (ready && !user) {
    return <Navigate to="/login" replace />;
  }
  const shown = (rows ?? []).filter((r) => filter === 'all' || r.status === filter);
  const setStatus = async (id: number, status: ChartRequestRow['status']) => {
    await apiSend('PATCH', `chart-requests/${id}`, { status });
    await load();
  };

  return (
    <>
      <PageHead title={user?.is_admin ? 'طلبات تحديث الرسوم' : 'طلباتي'} lead="الطلبات التي ينفّذها وكيل الذكاء الاصطناعي في فترة تشغيله. افتح الرسم من الرابط، ومن زر ✎ على أي رسم تضيف طلبًا أو ملاحظة أو تعدّل الشرح." />
      <div className="toggle" role="group" aria-label="تصفية الحالة" style={{ marginBottom: 12 }}>
        {(['all', 'pending', 'in_progress', 'done', 'rejected'] as const).map((s) => (
          <button key={s} aria-pressed={filter === s} onClick={() => setFilter(s)}>
            {s === 'all' ? 'الكل' : LABEL[s]}
          </button>
        ))}
      </div>
      {rows === null ? null : shown.length === 0 ? (
        <p className="muted">لا طلبات.</p>
      ) : (
        <ul className="tools-list">
          {shown.map((r) => (
            <li key={r.id}>
              <span className={`chip ${TONE[r.status]}`}>{LABEL[r.status]}</span> <strong>{r.chart_title}</strong>{' '}
              <Link to={r.page_path}>فتح الصفحة ←</Link>
              <p>{r.message}</p>
              {r.agent_response && <div className="callout">{r.agent_response}</div>}
              <span className="muted">
                {r.user?.name} · {formatDateTime(r.created_at)}
              </span>
              {user?.is_admin && (
                <span style={{ marginInlineStart: 10, display: 'inline-flex', gap: 6 }}>
                  {(['in_progress', 'done', 'rejected'] as const).map((s) => (
                    <button key={s} className="btn" onClick={() => void setStatus(r.id, s)} disabled={r.status === s}>
                      {LABEL[s]}
                    </button>
                  ))}
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
