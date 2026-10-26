import { useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, apiUpload } from '@/core/api';
import { formatDateTime } from '@/core/format';
import { FREQ } from './api';

interface UploadsResponse {
  uploads: { id: number; source_name: string; file_name: string; rows: number; created_at: string; uploader: string | null }[];
  series: { id: number; key: string; label_ar: string; source_name: string; entity_code: string; unit: string | null; frequency: string; points: number; last_period: string | null }[];
}

/**
 * رفع سلاسل أسعار دورية بملف Excel/CSV (مثل مؤشرات مواد البناء من الهيئة العامة للإحصاء GASTAT): العمود الأول الفترة، وكل عمود بعده سلسلة.
 * بعد الرفع تظهر السلسلة في البحث عند ربط سلسلة سعرية بعقدة.
 */
export function UploadsPanel({ canEdit }: { canEdit: boolean }) {
  const qc = useQueryClient();
  const list = useQuery({ queryKey: ['intel-uploads'], queryFn: ({ signal }) => api<UploadsResponse>('intel/uploads', {}, signal) });
  const [f, setF] = useState({ source_name: 'الهيئة العامة للإحصاء السعودية (GASTAT)', source_url: 'https://www.stats.gov.sa', entity_code: 'SAU', unit: 'مؤشر', currency: '', frequency: 'M', notes: '' });
  const [file, setFile] = useState<File | null>(null);
  const up = useMutation({
    mutationFn: () => {
      const fd = new FormData();
      fd.append('file', file!);
      for (const [k, v] of Object.entries(f)) if (v) fd.append(k, v);
      return apiUpload<{ series: { key: string; label_ar: string; points: number; first: string; last: string }[]; warnings: string[] }>('intel/uploads', fd);
    },
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['intel-uploads'] }),
  });
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });

  return (
    <section className="section">
      <h2>سلاسل مرفوعة يدويًا (Excel/CSV)</h2>
      <p className="lead">للمصادر بلا واجهة برمجية مثل مؤشرات أسعار مواد البناء لدى الهيئة العامة للإحصاء السعودية. الصيغة: العمود الأول الفترة (2025-07 أو 2025-07-15 أو 2025-Q3 أو تاريخ Excel)، وكل عمود تالٍ سلسلة اسمها رأس العمود. لكل نقطة يُحفظ الملف والرافع ووقت الرفع.</p>
      {canEdit && (
        <form className="card chain-edit" onSubmit={(e) => { e.preventDefault(); if (file) up.mutate(); }}>
          <div className="chain-edit-grid">
            <label style={{ gridColumn: 'span 2' }}>الجهة المصدر<input type="text" required value={f.source_name} onChange={set('source_name')} /></label>
            <label style={{ gridColumn: 'span 2' }}>رابط المصدر<input type="url" dir="ltr" value={f.source_url} onChange={set('source_url')} /></label>
            <label>الدولة (ISO3)<input type="text" dir="ltr" maxLength={3} required value={f.entity_code} onChange={(e) => setF({ ...f, entity_code: e.target.value.toUpperCase() })} /></label>
            <label>الوحدة<input type="text" value={f.unit} onChange={set('unit')} /></label>
            <label>العملة<input type="text" dir="ltr" maxLength={3} value={f.currency} onChange={(e) => setF({ ...f, currency: e.target.value.toUpperCase() })} /></label>
            <label>الوتيرة<select value={f.frequency} onChange={set('frequency')}>{Object.entries(FREQ).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
          </div>
          <label className="intel-field"><span>الملف (xlsx أو csv، حتى 5 ميغابايت)</span><input type="file" accept=".xlsx,.csv" onChange={(e) => setFile(e.target.files?.[0] ?? null)} aria-label="ملف السلاسل" /></label>
          <div className="intel-chips">
            <button className="btn" type="submit" disabled={!file || up.isPending}>{up.isPending ? 'جارٍ الرفع…' : 'رفع'}</button>
            {up.error && <span role="alert" className="muted">{(up.error as Error).message}</span>}
          </div>
          {up.data && (
            <div>
              <p>رُفعت {up.data.series.length} سلسلة: {up.data.series.map((s) => `${s.label_ar} (${s.points} نقطة، ${s.first}–${s.last})`).join('، ')}.</p>
              {up.data.warnings.length > 0 && <details><summary>{up.data.warnings.length} تحذيرات</summary><ul>{up.data.warnings.map((w, i) => <li key={i}>{w}</li>)}</ul></details>}
            </div>
          )}
        </form>
      )}
      {list.data && list.data.series.length > 0 && (
        <div className="card heat-wrap">
          <table className="heat-table" aria-label="السلاسل المرفوعة">
            <thead><tr><th>السلسلة</th><th>المصدر</th><th>المفتاح</th><th className="num">نقاط</th><th>آخر فترة</th></tr></thead>
            <tbody>{list.data.series.map((s) => <tr key={s.id}><th scope="row">{s.label_ar}</th><td className="plain">{s.source_name} · {s.entity_code}</td><td className="plain" dir="ltr"><small>{s.key}</small></td><td className="plain num">{s.points}</td><td className="plain num" dir="ltr">{s.last_period ?? '—'}</td></tr>)}</tbody>
          </table>
          <p className="source-note">آخر الملفات: {list.data.uploads.slice(0, 5).map((u) => `${u.file_name} (${u.source_name}، ${u.rows} قيمة، ${formatDateTime(u.created_at)}${u.uploader ? `، ${u.uploader}` : ''})`).join(' · ')}</p>
        </div>
      )}
    </section>
  );
}
