import { Fragment, useState } from 'react';
import { Link } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, apiSend, apiUpload } from '@/core/api';
import { Loading, ErrorState } from '@/core/components/States';
import { useAuth } from '@/core/auth';
import { formatDateTime, formatNumber } from '@/core/format';
import { StatLabel } from '@/core/components/IndicatorIcon';

interface OrgPrice {
  id: number;
  node_id: number | null;
  node_name: string | null;
  node_unit: string | null;
  chain_name: string | null;
  product_label: string;
  spec: string | null;
  supplier_name: string;
  supplier_country: string | null;
  price: string;
  currency: string;
  unit: string;
  quantity: string | null;
  incoterm: string | null;
  price_date: string;
  price_omr_per_unit: string | null;
  source: string;
}
interface Ref { label_ar: string; price_type: string; confidence: string; market: string | null; incoterm: string | null; period: string; source: string; base_omr: number; landed_omr: number; freight_assumed_pct: number; basis: string }
interface BenchmarkResult {
  status: 'ok' | 'unlinked';
  message?: string;
  node: { name_ar: string; unit: string } | null;
  paid_omr_per_node_unit?: number | null;
  unit_compatible?: boolean;
  references?: Ref[];
  range?: { min: number; median: number; max: number; n: number } | null;
  position?: { gap_vs_median: number; label: string } | null;
  should_cost?: { factor: number; should_cost_omr: number; base_period: string; covered_weight: number; inputs: { input: string; weight: number; from: string; to: string; change: number; series: string }[]; note: string } | null;
  savings_vs_median_omr?: number | null;
  supplier_behaviour?: { verdict: string; label: string; pass_through_up?: number; pass_through_down?: number } | null;
  peer_index?: { month: string; n_orgs: number; median_omr: number; p25_omr: number; p75_omr: number } | null;
  computed_at?: string;
}

const pct = (v: number) => `${v > 0 ? '+' : ''}${formatNumber(v * 100, 1)}%`;

function BenchmarkPanel({ price, orgId }: { price: OrgPrice; orgId: number }) {
  const [assume, setAssume] = useState({ freight_pct: 10, duty_pct: 5 });
  const q = useQuery({ queryKey: ['org-benchmark', price.id, assume], queryFn: ({ signal }) => api<BenchmarkResult>('intel/org/benchmark', { price_id: price.id, org_id: orgId, ...assume }, signal) });
  if (q.isLoading) return <Loading height={120} />;
  if (q.error) return <ErrorState error={q.error as Error} />;
  const r = q.data!;
  if (r.status !== 'ok') return <p className="muted">{r.message}</p>;
  const u = r.node?.unit ?? '';
  return (
    <div className="mi-bench">
      <div className="grid cols-4 insight-tiles">
        <div className="card tile"><StatLabel>ما تدفعه الجهة</StatLabel><span className="stat-value num">{r.paid_omr_per_node_unit != null ? formatNumber(r.paid_omr_per_node_unit, 2) : '—'}<small className="stat-unit">ر.ع/{u}</small></span><span className="stat-meta">{r.unit_compatible ? '' : `وحدة السعر (${price.unit}) لا تتحول إلى ${u}`}</span></div>
        <div className="card tile"><StatLabel>نطاق السوق (واصل تقديري)</StatLabel><span className="stat-value num">{r.range ? formatNumber(r.range.median, 2) : '—'}<small className="stat-unit">ر.ع</small></span><span className="stat-meta">{r.range ? `${formatNumber(r.range.min, 2)}–${formatNumber(r.range.max, 2)} · ${r.range.n} مرجع` : 'لا مراجع سعرية بالمستوى لهذه العقدة'}</span></div>
        <div className={`card tile ${r.position && r.position.gap_vs_median > 0.05 ? 'mi-insight high' : r.position && r.position.gap_vs_median < -0.05 ? 'mi-insight low' : ''}`}><StatLabel>موقع السعر</StatLabel><span className="stat-value" style={{ fontSize: 18 }}>{r.position?.label ?? '—'}</span><span className="stat-meta">{r.position ? `${pct(r.position.gap_vs_median)} عن الوسيط` : ''}</span></div>
        <div className="card tile"><StatLabel>فرصة التوفير التقديرية</StatLabel><span className="stat-value num">{r.savings_vs_median_omr ? formatNumber(r.savings_vs_median_omr, 0) : '—'}<small className="stat-unit">ر.ع</small></span><span className="stat-meta">{price.quantity ? 'لو اشتُريت الكمية بوسيط السوق' : 'أدخل الكمية لحسابها'}</span></div>
      </div>

      {r.should_cost && (
        <div className="card">
          <strong>التكلفة المتوقعة (Should-cost) بمعادلة تعديل السعر</strong>
          <p>سعر التعاقد ({formatNumber(Number(price.price_omr_per_unit), 2)} ر.ع، {r.should_cost.base_period}) × معامل المدخلات <b className="num">{formatNumber(r.should_cost.factor, 3)}</b> = <b className="num">{formatNumber(r.should_cost.should_cost_omr, 2)}</b> ر.ع/{price.unit} اليوم.</p>
          <table className="heat-table"><thead><tr><th>المدخل</th><th className="num">الوزن</th><th className="num">تغيّر سعره</th><th>من–إلى</th></tr></thead>
            <tbody>{r.should_cost.inputs.map((i) => <tr key={i.input}><th scope="row">{i.input}<small dir="ltr">{i.series}</small></th><td className="plain num">{formatNumber(i.weight * 100, 0)}%</td><td className="plain num" dir="ltr">{pct(i.change)}</td><td className="plain" dir="ltr">{i.from} → {i.to}</td></tr>)}</tbody>
          </table>
          <small className="muted">{r.should_cost.note} الأوزان المغطّاة بسلاسل: {formatNumber(r.should_cost.covered_weight * 100, 0)}%.</small>
        </div>
      )}

      {r.references && r.references.length > 0 && (
        <div className="card heat-wrap">
          <strong>المراجع السعرية</strong>
          <div className="chain-edit-grid" style={{ margin: '8px 0' }}>
            <label>شحن وتأمين لأسعار المنشأ (%) <span className="chip warning">افتراض</span><input type="number" min="0" max="200" value={assume.freight_pct} onChange={(e) => setAssume({ ...assume, freight_pct: Number(e.target.value) })} /></label>
            <label>الرسم الجمركي (%)<input type="number" min="0" max="100" value={assume.duty_pct} onChange={(e) => setAssume({ ...assume, duty_pct: Number(e.target.value) })} /></label>
          </div>
          <div className="heat-scroll">
            <table className="heat-table"><thead><tr><th>المرجع</th><th>السوق</th><th>الفترة</th><th className="num">الأساس ر.ع</th><th className="num">واصل ر.ع</th><th>الأساس</th></tr></thead>
              <tbody>{r.references.map((x) => <tr key={x.label_ar + x.market}><th scope="row">{x.label_ar}<small>{x.source}</small></th><td className="plain">{x.market}{x.incoterm ? ` · ${x.incoterm}` : ''}</td><td className="plain" dir="ltr">{x.period}</td><td className="plain num">{formatNumber(x.base_omr, 2)}</td><td className="plain num"><b>{formatNumber(x.landed_omr, 2)}</b></td><td className="plain"><small>{x.basis}</small></td></tr>)}</tbody>
            </table>
          </div>
        </div>
      )}
      <div className="grid cols-2">
        <div className="card"><strong>سلوك المورد</strong><p>{r.supplier_behaviour?.label ?? 'أضف أسعارًا متتالية لنفس المورد والمنتج لقياس سلوكه.'}</p>{r.supplier_behaviour?.pass_through_up != null && <small className="muted">نسبة النقل عند ارتفاع المدخلات {formatNumber(r.supplier_behaviour.pass_through_up, 2)} وعند انخفاضها {formatNumber(r.supplier_behaviour.pass_through_down ?? 0, 2)}</small>}</div>
        <div className="card"><strong>مؤشر الجهات المجمّع (مجهّل)</strong><p>{r.peer_index ? `وسيط ${formatNumber(r.peer_index.median_omr, 2)} ر.ع (${formatNumber(r.peer_index.p25_omr, 2)}–${formatNumber(r.peer_index.p75_omr, 2)}) من ${r.peer_index.n_orgs} جهات، ${r.peer_index.month}` : 'يظهر عندما تُدخل 3 جهات فأكثر أسعارًا للعقدة نفسها (لا أسماء ولا جهات تُكشف).'}</p></div>
      </div>
      {r.computed_at && <p className="source-note">حُسبت {formatDateTime(r.computed_at)} وحُفظت لقطة النتيجة بمدخلاتها للتدقيق.</p>}
    </div>
  );
}

/** أسعار موردي الجهة: إدخال يدوي أو رفع ملف، ومقارنة كل سعر بالسوق والتكلفة المتوقعة. أسعار كل جهة لا تراها غيرها. */
export function OrgPrices() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const memberships = user?.memberships ?? [];
  const [orgId, setOrgId] = useState<number | null>(memberships[0]?.organization_id ?? null);
  const activeOrg = orgId ?? memberships[0]?.organization_id ?? null;
  const prices = useQuery({ queryKey: ['org-prices', activeOrg], queryFn: ({ signal }) => api<{ data: OrgPrice[] }>('intel/org/prices', { org_id: activeOrg }, signal), enabled: !!activeOrg });
  const nodes = useQuery({ queryKey: ['intel-nodes'], queryFn: ({ signal }) => api<{ data: { id: number; name_ar: string; unit: string | null; chain_name: string }[] }>('intel/nodes', {}, signal), enabled: !!user });
  const [f, setF] = useState({ product_label: '', spec: '', supplier_name: '', supplier_country: '', price: '', currency: 'OMR', unit: 'طن', quantity: '', incoterm: '', price_date: new Date().toISOString().slice(0, 10), node_id: '' });
  const [open, setOpen] = useState<number | null>(null);
  const [file, setFile] = useState<File | null>(null);
  const [fileNode, setFileNode] = useState('');
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });
  const add = useMutation({
    mutationFn: () => apiSend('POST', `intel/org/prices?org_id=${activeOrg}`, { ...f, price: Number(f.price), quantity: f.quantity ? Number(f.quantity) : null, node_id: f.node_id ? Number(f.node_id) : null, supplier_country: f.supplier_country || null, incoterm: f.incoterm || null, spec: f.spec || null }),
    onSuccess: () => { void qc.invalidateQueries({ queryKey: ['org-prices'] }); setF({ ...f, product_label: '', price: '', quantity: '', spec: '' }); },
  });
  const upload = useMutation({
    mutationFn: () => { const fd = new FormData(); fd.append('file', file!); if (fileNode) fd.append('node_id', fileNode); return apiUpload<{ inserted: number; warnings: string[] }>(`intel/org/prices/upload?org_id=${activeOrg}`, fd); },
    onSuccess: () => void qc.invalidateQueries({ queryKey: ['org-prices'] }),
  });
  const del = useMutation({ mutationFn: (id: number) => apiSend('DELETE', `intel/org/prices/${id}?org_id=${activeOrg}`), onSuccess: () => void qc.invalidateQueries({ queryKey: ['org-prices'] }) });

  if (!user) return <section className="section"><div className="card tone-info"><Link to="/login">سجّل الدخول</Link> لإدخال أسعار موردي جهتك ومقارنتها بالسوق.</div></section>;
  if (memberships.length === 0) return <section className="section"><div className="card tone-info">حسابك غير مرتبط بجهة بعد. يضيفك مدير النظام إلى جهتك (حكومية أو شركة) بدور موظف مشتريات أو محلل أو مدير جهة.{user.is_admin && <> (للمشرف: <code dir="ltr">php artisan orgs:manage create --name="…"</code> ثم <code dir="ltr">orgs:manage add-member --org=… --email=… --role=…</code>)</>}</div></section>;

  const nodeOptions = nodes.data?.data.map((n) => <option key={n.id} value={n.id}>{n.chain_name} ← {n.name_ar}{n.unit ? ` (${n.unit})` : ''}</option>);
  return (
    <section className="section">
      <h2>أسعار موردي جهتي</h2>
      <p className="lead">أدخل الأسعار التي تدفعها جهتك (قوائم أسعار، عروض، أوامر شراء) واربط كل سعر بعقدة في سلسلة القيمة؛ فيقارنه النظام بنطاق السوق والتكلفة المتوقعة ويقدّر فرص التوفير. أسعار جهتك لا تراها أي جهة أخرى.</p>
      {memberships.length > 1 && (
        <div className="controls"><label className="intel-field" style={{ maxWidth: 320 }}><span>الجهة</span><select value={activeOrg ?? ''} onChange={(e) => setOrgId(Number(e.target.value))}>{memberships.map((m) => <option key={m.organization_id} value={m.organization_id}>{m.name_ar} ({m.role})</option>)}</select></label></div>
      )}
      <details className="card" open>
        <summary><b>إضافة سعر مورد</b></summary>
        <form className="chain-edit" onSubmit={(e) => { e.preventDefault(); add.mutate(); }}>
          <div className="chain-edit-grid">
            <label style={{ gridColumn: 'span 2' }}>المنتج<input type="text" required value={f.product_label} onChange={set('product_label')} placeholder="حديد تسليح 16 مم B500" /></label>
            <label style={{ gridColumn: 'span 2' }}>العقدة في سلسلة القيمة<select value={f.node_id} onChange={set('node_id')}><option value="">— بلا ربط —</option>{nodeOptions}</select></label>
            <label style={{ gridColumn: 'span 2' }}>المورد<input type="text" required value={f.supplier_name} onChange={set('supplier_name')} /></label>
            <label>دولة المورد (ISO3)<input type="text" dir="ltr" maxLength={3} value={f.supplier_country} onChange={(e) => setF({ ...f, supplier_country: e.target.value.toUpperCase() })} /></label>
            <label>التاريخ<input type="date" required value={f.price_date} onChange={set('price_date')} /></label>
            <label>السعر<input type="number" step="any" min="0" required value={f.price} onChange={set('price')} /></label>
            <label>العملة<input type="text" dir="ltr" maxLength={3} required value={f.currency} onChange={(e) => setF({ ...f, currency: e.target.value.toUpperCase() })} /></label>
            <label>الوحدة<select value={f.unit} onChange={set('unit')}>{['طن', 'كغم', 'م³', 'قطعة', 'متر', 'كم', 'لتر'].map((x) => <option key={x}>{x}</option>)}</select></label>
            <label>الكمية<input type="number" step="any" min="0" value={f.quantity} onChange={set('quantity')} /></label>
            <label>شروط التسليم<select value={f.incoterm} onChange={set('incoterm')}><option value="">—</option>{['EXW', 'FCA', 'FOB', 'CFR', 'CIF', 'DAP', 'DDP'].map((x) => <option key={x}>{x}</option>)}</select></label>
            <label style={{ gridColumn: 'span 3' }}>المواصفة<input type="text" value={f.spec} onChange={set('spec')} placeholder="الرتبة، المقاس، المعيار…" /></label>
          </div>
          <div className="intel-chips"><button className="btn" type="submit" disabled={add.isPending}>حفظ السعر</button>{add.error && <span role="alert" className="muted">{(add.error as Error).message}</span>}</div>
        </form>
        <form className="chain-edit" onSubmit={(e) => { e.preventDefault(); if (file) upload.mutate(); }}>
          <b>أو ارفع ملفًا (CSV/XLSX)</b>
          <small className="muted">الأعمدة: التاريخ، المنتج، المورد، السعر، العملة، الوحدة (مطلوبة) + الدولة، الكمية، شروط التسليم، رمز HS، المواصفة (اختيارية).</small>
          <div className="chain-edit-grid">
            <label style={{ gridColumn: 'span 2' }}>الملف<input type="file" accept=".csv,.xlsx" onChange={(e) => setFile(e.target.files?.[0] ?? null)} /></label>
            <label style={{ gridColumn: 'span 2' }}>اربط كل الأسطر بعقدة<select value={fileNode} onChange={(e) => setFileNode(e.target.value)}><option value="">— بلا ربط —</option>{nodeOptions}</select></label>
          </div>
          <div className="intel-chips"><button className="btn" type="submit" disabled={!file || upload.isPending}>رفع</button>{upload.data && <span className="muted">أُضيف {upload.data.inserted} سعرًا{upload.data.warnings.length ? ` · ${upload.data.warnings.length} تحذيرات` : ''}</span>}{upload.error && <span role="alert" className="muted">{(upload.error as Error).message}</span>}</div>
        </form>
      </details>

      {prices.isLoading && <Loading height={120} />}
      {prices.data && (
        <div className="card heat-wrap">
          <strong>أسعار الجهة ({prices.data.data.length})</strong>
          <div className="heat-scroll">
            <table className="heat-table intel-table" aria-label="أسعار الجهة">
              <thead><tr><th>التاريخ</th><th>المنتج</th><th>المورد</th><th className="num">السعر</th><th className="num">ر.ع/وحدة</th><th>العقدة</th><th /></tr></thead>
              <tbody>
                {prices.data.data.map((p) => (
                  <Fragment key={p.id}>
                    <tr>
                      <td className="plain" dir="ltr">{p.price_date}</td>
                      <th scope="row">{p.product_label}{p.spec && <small>{p.spec}</small>}</th>
                      <td className="plain">{p.supplier_name}{p.supplier_country ? ` · ${p.supplier_country}` : ''}</td>
                      <td className="plain num" dir="ltr">{formatNumber(Number(p.price), 2)} {p.currency}/{p.unit}</td>
                      <td className="plain num">{p.price_omr_per_unit ? formatNumber(Number(p.price_omr_per_unit), 2) : '—'}</td>
                      <td className="plain">{p.node_name ? `${p.chain_name} ← ${p.node_name}` : <span className="muted">غير مربوط</span>}</td>
                      <td className="plain"><button type="button" className="btn" onClick={() => setOpen(open === p.id ? null : p.id)}>{open === p.id ? 'إغلاق' : 'قارن بالسوق'}</button> <button type="button" className="btn" onClick={() => del.mutate(p.id)} aria-label={`حذف ${p.product_label}`}>حذف</button></td>
                    </tr>
                    {open === p.id && <tr><td colSpan={7} className="plain"><BenchmarkPanel price={p} orgId={activeOrg!} /></td></tr>}
                  </Fragment>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </section>
  );
}
