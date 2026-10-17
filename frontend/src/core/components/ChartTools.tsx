import { useEffect, useState, type FormEvent } from 'react';
import { api, apiSend, type ChartNoteRow, type ChartRequestRow } from '../api';
import { useAuth } from '../auth';
import { formatDateTime } from '../format';
import type { Explanation } from '../explain';
import { resetExplanation, saveExplanation } from '../explanationOverrides';

type Tab = 'request' | 'notes' | 'edit';

const STATUS: Record<ChartRequestRow['status'], string> = { pending: 'بانتظار الوكيل', in_progress: 'قيد التنفيذ', done: 'نُفِّذ', rejected: 'مرفوض' };

interface Props {
  chartKey: string;
  title: string;
  explanation: Explanation | undefined;
  hasOverride: boolean;
  onClose: () => void;
}

const toLines = (e: Explanation | undefined, field: 'read' | 'terms'): string =>
  field === 'read' ? (e?.read ?? []).join('\n') : (e?.terms ?? []).map((t) => `${t.term}: ${t.definition}`).join('\n');

/** أدوات المسجَّلين على الرسم: طلب تحديث ينفذه الوكيل، وملاحظات داخلية، وتعديل الشرح. */
export function ChartTools({ chartKey, title, explanation, hasOverride, onClose }: Props) {
  const { user } = useAuth();
  const [tab, setTab] = useState<Tab>('request');

  return (
    <div className="explain chart-tools" role="region" aria-label="أدوات الرسم">
      <div className="tools-head">
        <button className="btn explain-back" onClick={onClose} aria-label="العودة إلى الرسم" title="العودة إلى الرسم">
          ↩
        </button>
        <div className="toggle" role="tablist" aria-label="أدوات الرسم">
          {(
            [
              ['request', 'طلب تحديث'],
              ['notes', 'ملاحظات'],
              ['edit', 'تعديل الشرح'],
            ] as const
          ).map(([v, label]) => (
            <button key={v} role="tab" aria-selected={tab === v} aria-pressed={tab === v} onClick={() => setTab(v)}>
              {label}
            </button>
          ))}
        </div>
      </div>
      {tab === 'request' && <RequestTab chartKey={chartKey} title={title} />}
      {tab === 'notes' && <NotesTab chartKey={chartKey} canModerate={!!user?.is_admin} userId={user?.id ?? 0} />}
      {tab === 'edit' && <EditTab chartKey={chartKey} explanation={explanation} hasOverride={hasOverride} />}
    </div>
  );
}

function RequestTab({ chartKey, title }: { chartKey: string; title: string }) {
  const [message, setMessage] = useState('');
  const [rows, setRows] = useState<ChartRequestRow[]>([]);
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  const load = () => api<{ data: ChartRequestRow[] }>('chart-requests', { chart_key: chartKey }).then((r) => setRows(r.data)).catch(() => undefined);
  useEffect(() => {
    void load();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [chartKey]);

  const submit = async (e: FormEvent) => {
    e.preventDefault();
    setBusy(true);
    setError('');
    try {
      await apiSend('POST', 'chart-requests', { chart_key: chartKey, chart_title: title, page_path: window.location.pathname, message });
      setMessage('');
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'تعذّر الإرسال');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="tools-body">
      <p className="muted">اكتب ما تريده من هذا الرسم (مؤشر إضافي، مصدر آخر، فترة أطول، تصحيح…). ينفّذه وكيل الذكاء الاصطناعي في فترة تشغيله ويسجّل ردّه هنا.</p>
      <form onSubmit={submit} className="tools-form">
        <textarea value={message} onChange={(e) => setMessage(e.target.value)} rows={3} maxLength={2000} placeholder="مثال: أضيفوا مقارنة مع الهند" aria-label="نص الطلب" required minLength={5} />
        <button className="btn" disabled={busy || message.trim().length < 5}>
          إرسال الطلب
        </button>
        {error && <span className="form-error" role="alert">{error}</span>}
      </form>
      {rows.length > 0 && (
        <ul className="tools-list">
          {rows.map((r) => (
            <li key={r.id}>
              <span className={`chip ${r.status === 'done' ? 'good' : r.status === 'rejected' ? 'critical' : 'warning'}`}>{STATUS[r.status]}</span> {r.message}
              {r.agent_response && <div className="callout">{r.agent_response}</div>}
              <span className="muted"> · {r.user?.name} · {formatDateTime(r.created_at)}</span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

function NotesTab({ chartKey, canModerate, userId }: { chartKey: string; canModerate: boolean; userId: number }) {
  const [notes, setNotes] = useState<ChartNoteRow[]>([]);
  const [body, setBody] = useState('');
  const [error, setError] = useState('');

  const load = () => api<{ data: ChartNoteRow[] }>('chart-notes', { chart_key: chartKey }).then((r) => setNotes(r.data)).catch(() => undefined);
  useEffect(() => {
    void load();
  }, [chartKey]);

  const add = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    try {
      await apiSend('POST', 'chart-notes', { chart_key: chartKey, body });
      setBody('');
      await load();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'تعذّر الحفظ');
    }
  };
  const remove = async (id: number) => {
    await apiSend('DELETE', `chart-notes/${id}`);
    await load();
  };

  return (
    <div className="tools-body">
      <p className="muted">ملاحظات داخلية يراها المسجَّلون فقط (لا تظهر للزوار).</p>
      <ul className="tools-list">
        {notes.length === 0 && <li className="muted">لا ملاحظات بعد.</li>}
        {notes.map((n) => (
          <li key={n.id}>
            {n.body}
            <span className="muted"> · {n.user?.name} · {formatDateTime(n.created_at)}</span>
            {(canModerate || n.user?.id === userId) && (
              <button className="btn" style={{ marginInlineStart: 8 }} onClick={() => void remove(n.id)} aria-label="حذف الملاحظة">
                حذف
              </button>
            )}
          </li>
        ))}
      </ul>
      <form onSubmit={add} className="tools-form">
        <textarea value={body} onChange={(e) => setBody(e.target.value)} rows={2} maxLength={2000} placeholder="أضف ملاحظة…" aria-label="نص الملاحظة" />
        <button className="btn" disabled={body.trim().length < 2}>
          إضافة ملاحظة
        </button>
        {error && <span className="form-error" role="alert">{error}</span>}
      </form>
    </div>
  );
}

function EditTab({ chartKey, explanation, hasOverride }: { chartKey: string; explanation: Explanation | undefined; hasOverride: boolean }) {
  const [meaning, setMeaning] = useState(explanation?.meaning ?? '');
  const [shows, setShows] = useState(explanation?.shows ?? '');
  const [read, setRead] = useState(toLines(explanation, 'read'));
  const [why, setWhy] = useState(explanation?.why ?? '');
  const [terms, setTerms] = useState(toLines(explanation, 'terms'));
  const [caution, setCaution] = useState(explanation?.caution ?? '');
  const [msg, setMsg] = useState('');
  const [error, setError] = useState('');

  const save = async (e: FormEvent) => {
    e.preventDefault();
    setError('');
    setMsg('');
    const parsedTerms = terms
      .split('\n')
      .map((l) => l.trim())
      .filter(Boolean)
      .map((l) => {
        const i = l.indexOf(':');
        return i > 0 ? { term: l.slice(0, i).trim(), definition: l.slice(i + 1).trim() } : null;
      })
      .filter((t): t is { term: string; definition: string } => !!t && t.definition !== '');
    try {
      await saveExplanation(chartKey, {
        meaning: meaning.trim(),
        shows: shows.trim(),
        read: read.split('\n').map((l) => l.trim()).filter(Boolean),
        why: why.trim(),
        ...(parsedTerms.length ? { terms: parsedTerms } : {}),
        ...(caution.trim() ? { caution: caution.trim() } : {}),
      });
      setMsg('حُفظ الشرح.');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'تعذّر الحفظ');
    }
  };

  return (
    <form className="tools-body tools-form" onSubmit={save}>
      <label>ماذا يعني هذا القياس؟<textarea value={meaning} onChange={(e) => setMeaning(e.target.value)} rows={3} required /></label>
      <label>ماذا يوضح الرسم؟<textarea value={shows} onChange={(e) => setShows(e.target.value)} rows={3} required /></label>
      <label>كيف أقرأه؟ (سطر لكل نقطة)<textarea value={read} onChange={(e) => setRead(e.target.value)} rows={4} required /></label>
      <label>لماذا يهمّ؟<textarea value={why} onChange={(e) => setWhy(e.target.value)} rows={2} required /></label>
      <label>مصطلحات (سطر لكل مصطلح: «مصطلح: تعريف»)<textarea value={terms} onChange={(e) => setTerms(e.target.value)} rows={3} /></label>
      <label>تنبيه (اختياري)<textarea value={caution} onChange={(e) => setCaution(e.target.value)} rows={2} /></label>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
        <button className="btn">حفظ الشرح</button>
        {hasOverride && (
          <button type="button" className="btn" onClick={() => void resetExplanation(chartKey).then(() => setMsg('أُعيد الشرح الافتراضي.'))}>
            استعادة الافتراضي
          </button>
        )}
      </div>
      {msg && <span className="muted" role="status">{msg}</span>}
      {error && <span className="form-error" role="alert">{error}</span>}
    </form>
  );
}
