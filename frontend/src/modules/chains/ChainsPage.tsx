import { Fragment, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/core/api';
import { PageHead } from '@/core/components/PageHead';
import { Loading, ErrorState } from '@/core/components/States';
import { Segmented } from '@/core/components/Segmented';
import { useAuth } from '@/core/auth';
import { formatDateTime, formatNumber } from '@/core/format';
import { ChainExplorer } from './ChainExplorer';
import { UploadsPanel } from './UploadsPanel';
import { BASIS, CONFIDENCE, FREQ, KIND, PRICE_TYPE, useChain, useChainMutation, useChainRevisions, useChains, type ChainEdge, type ChainFull, type ChainNode, type SeriesLink } from './api';

const pct = (v: string | number | null) => (v == null ? '—' : `${formatNumber(Number(v) * 100, 1)}%`);
const confTone = (c: string) => (c === 'high' ? 'good' : c === 'medium' ? 'warning' : 'critical');

/** قائمة السلاسل، أو سلسلة واحدة للمراجعة والتعديل (لوحة إدارة سلاسل القيمة). */
export default function ChainsPage() {
  const { slug } = useParams();
  return slug ? <ChainDetail slug={slug} /> : <ChainList />;
}

function ChainList() {
  const { user, ready } = useAuth();
  const q = useChains();
  return (
    <>
      <PageHead title="سلاسل القيمة" lead="العمود الفقري لذكاء السوق: لكل منتج سلسلته من المادة الخام إلى المنتج النهائي، بأوزان تكلفة موثّقة المصدر ونطاقاتها، وسلاسل أسعار لكل مرحلة. المحلل يراجعها ويعدّلها هنا دون تعديل الكود." />
      {ready && !user && <div className="card tone-info"><Link to="/login">سجّل الدخول</Link> لعرض سلاسل القيمة.</div>}
      {q.isLoading && user && <Loading height={160} />}
      {q.error && <ErrorState error={q.error as Error} onRetry={() => void q.refetch()} />}
      <div className="grid cols-3">
        {q.data?.data.map((c) => (
          <Link key={c.slug} to={`/market-intel/chains/${c.slug}`} className="card chain-card">
            <h3>{c.name_ar}</h3>
            <small className="muted" dir="ltr">{c.name_en}</small>
            <div className="intel-chips">
              <span className="chip">{c.status === 'review' ? 'بانتظار مراجعة المحلل' : c.status === 'published' ? 'منشورة' : 'مسودة'}</span>
              <span className="chip">النسخة {c.version}</span>
              {c.is_demo && <span className="chip warning">بيانات تجريبية</span>}
            </div>
            <p className="muted">{c.nodes} عقدة · {c.edges} حافة · {c.missing_weights > 0 ? <b>{c.missing_weights} وزنًا ينقصه مصدر</b> : 'كل الأوزان موثّقة'}{c.flagged_nodes > 0 ? ` · ⚠ ${c.flagged_nodes} تعارض إحصائي` : ''}</p>
          </Link>
        ))}
      </div>
      {user && <UploadsPanel canEdit={!!user.can_edit_chains} />}
    </>
  );
}

function SeriesBadge({ s }: { s: SeriesLink }) {
  return (
    <div className="series-line">
      <div>
        <b>{s.label_ar}</b> <span className="muted" dir="ltr">({s.indicator_key} · {s.entity_code})</span>
      </div>
      <div className="intel-chips">
        <span className="chip">{PRICE_TYPE[s.price_type]}</span>
        <span className={`chip ${confTone(s.confidence)}`}>{CONFIDENCE[s.confidence]}</span>
        <span className="chip">{s.role === 'primary' ? 'أساسية' : s.role === 'alternative' ? 'بديلة' : 'تقريبية (proxy)'}</span>
        {s.market && <span className="chip">{s.market}{s.incoterm ? ` · ${s.incoterm}` : ''}</span>}
        <span className="chip">{FREQ[s.frequency] ?? s.frequency}</span>
        {s.is_demo && <span className="chip warning">بيانات تجريبية</span>}
      </div>
      <small className="muted">
        {s.latest
          ? <>آخر قيمة <b className="num">{formatNumber(s.latest.value, 2)}</b> {s.unit ?? ''} للفترة <span dir="ltr">{s.latest.period}</span> · {s.latest.points} نقطة · جُلبت {formatDateTime(s.latest.updated_at)} · المجموعة {s.latest.dataset} ({s.latest.dataset_status})</>
          : <>لا بيانات بعد{s.license_note ? ` — ${s.license_note}` : ' (الجلب جارٍ أو المصدر غير مفعّل)'}</>}
      </small>
    </div>
  );
}

function EdgeEditor({ edge, slug, onDone }: { edge: ChainEdge; slug: string; onDone: () => void }) {
  const m = useChainMutation(slug);
  const [f, setF] = useState({
    weight: edge.weight ?? '', weight_min: edge.weight_min ?? '', weight_max: edge.weight_max ?? '', weight_basis: edge.weight_basis,
    conversion_factor: edge.conversion_factor ?? '', conversion_min: edge.conversion_min ?? '', conversion_max: edge.conversion_max ?? '', conversion_unit: edge.conversion_unit ?? '', notes: edge.notes ?? '', change_note: '',
  });
  const num = (v: string) => (v === '' ? null : Number(v));
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => setF({ ...f, [k]: e.target.value });
  return (
    <form
      className="chain-edit"
      onSubmit={(e) => {
        e.preventDefault();
        m.mutate(
          { method: 'PATCH', path: `/edges/${edge.id}`, body: { ...f, weight: num(f.weight), weight_min: num(f.weight_min), weight_max: num(f.weight_max), conversion_factor: num(f.conversion_factor), conversion_min: num(f.conversion_min), conversion_max: num(f.conversion_max), change_note: f.change_note || 'تعديل الوزن/عامل التحويل' } },
          { onSuccess: onDone },
        );
      }}
    >
      <div className="chain-edit-grid">
        <label>الوزن (0–1)<input type="number" step="0.001" min="0" max="1" value={f.weight} onChange={set('weight')} /></label>
        <label>الأدنى<input type="number" step="0.001" min="0" max="1" value={f.weight_min} onChange={set('weight_min')} /></label>
        <label>الأعلى<input type="number" step="0.001" min="0" max="1" value={f.weight_max} onChange={set('weight_max')} /></label>
        <label>أساس الوزن<select value={f.weight_basis} onChange={set('weight_basis')}>{Object.entries(BASIS).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
        <label>عامل التحويل<input type="number" step="any" min="0" value={f.conversion_factor} onChange={set('conversion_factor')} /></label>
        <label>أدنى<input type="number" step="any" min="0" value={f.conversion_min} onChange={set('conversion_min')} /></label>
        <label>أعلى<input type="number" step="any" min="0" value={f.conversion_max} onChange={set('conversion_max')} /></label>
        <label>وحدة التحويل<input type="text" value={f.conversion_unit} onChange={set('conversion_unit')} /></label>
      </div>
      <label className="intel-field"><span>ملاحظات</span><textarea rows={2} value={f.notes} onChange={set('notes')} /></label>
      <label className="intel-field"><span>سبب التعديل (يُحفظ في سجل النسخ)</span><input type="text" value={f.change_note} onChange={set('change_note')} placeholder="مثال: وزن من تقرير شركة أسمنت عُمان 2024" /></label>
      <div className="intel-chips">
        <button className="btn" type="submit" disabled={m.isPending}>حفظ</button>
        <button className="btn" type="button" onClick={onDone}>إلغاء</button>
        {m.error && <span role="alert" className="muted">{(m.error as Error).message}</span>}
      </div>
    </form>
  );
}

function SourceForm({ edge, slug, onDone }: { edge: ChainEdge; slug: string; onDone: () => void }) {
  const m = useChainMutation(slug);
  const [f, setF] = useState({ title: '', url: '', publisher: '', year: '', kind: 'company_report', reliability: 'medium', applies_to: 'weight', excerpt: '' });
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) => setF({ ...f, [k]: e.target.value });
  return (
    <form className="chain-edit" onSubmit={(e) => { e.preventDefault(); m.mutate({ method: 'POST', path: `/edges/${edge.id}/sources`, body: { ...f, url: f.url || null, year: f.year ? Number(f.year) : null, publisher: f.publisher || null } }, { onSuccess: onDone }); }}>
      <div className="chain-edit-grid">
        <label style={{ gridColumn: 'span 2' }}>العنوان<input type="text" required value={f.title} onChange={set('title')} /></label>
        <label style={{ gridColumn: 'span 2' }}>الرابط (https)<input type="url" dir="ltr" value={f.url} onChange={set('url')} /></label>
        <label>الناشر<input type="text" value={f.publisher} onChange={set('publisher')} /></label>
        <label>السنة<input type="number" value={f.year} onChange={set('year')} /></label>
        <label>النوع<select value={f.kind} onChange={set('kind')}>{[['company_report', 'تقرير شركة'], ['agency', 'جهة رسمية/دولية'], ['academic', 'أكاديمي'], ['industry', 'صناعي'], ['io_table', 'جداول مدخلات ومخرجات'], ['trade_press', 'صحافة متخصصة'], ['vendor', 'مورد/تجاري'], ['derived', 'مشتق']].map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
        <label>الموثوقية<select value={f.reliability} onChange={set('reliability')}>{Object.entries(CONFIDENCE).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
        <label>ينطبق على<select value={f.applies_to} onChange={set('applies_to')}><option value="weight">الوزن</option><option value="conversion">عامل التحويل</option><option value="both">كليهما</option></select></label>
      </div>
      <label className="intel-field"><span>المقتطف الداعم</span><textarea rows={2} value={f.excerpt} onChange={set('excerpt')} /></label>
      <div className="intel-chips">
        <button className="btn" type="submit" disabled={m.isPending}>إضافة المصدر</button>
        <button className="btn" type="button" onClick={onDone}>إلغاء</button>
        {m.error && <span role="alert" className="muted">{(m.error as Error).message}</span>}
      </div>
    </form>
  );
}

function SeriesForm({ node, slug, onDone }: { node: ChainNode; slug: string; onDone: () => void }) {
  const m = useChainMutation(slug);
  const [q, setQ] = useState('');
  const search = useQuery({ queryKey: ['intel-indicators', q], queryFn: ({ signal }) => api<{ data: { key: string; name_ar: string; unit: string | null; frequency: string; dataset: string }[] }>('intel/indicators', { q }, signal), enabled: q.length >= 2 });
  const [f, setF] = useState({ indicator_key: '', entity_code: 'OMN', label_ar: '', price_type: 'index', confidence: 'medium', role: 'proxy', market: '', incoterm: '', unit: '', currency: '' });
  const set = (k: keyof typeof f) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => setF({ ...f, [k]: e.target.value });
  return (
    <form className="chain-edit" onSubmit={(e) => { e.preventDefault(); m.mutate({ method: 'POST', path: `/nodes/${node.id}/series`, body: { ...f, market: f.market || null, incoterm: f.incoterm || null, unit: f.unit || null, currency: f.currency || null } }, { onSuccess: onDone }); }}>
      <label className="intel-field"><span>ابحث في مؤشرات النظام</span><input type="search" value={q} onChange={(e) => setQ(e.target.value)} placeholder="cement أو ctm.uv أو الإسمنت" /></label>
      {search.data && (
        <div className="intel-results">
          {search.data.data.map((i) => <button key={i.key} type="button" className="intel-result" onClick={() => setF({ ...f, indicator_key: i.key, label_ar: i.name_ar, unit: i.unit ?? '' })}><strong dir="ltr">{i.key}</strong><span>{i.name_ar} · {FREQ[i.frequency] ?? i.frequency}</span></button>)}
        </div>
      )}
      <div className="chain-edit-grid">
        <label style={{ gridColumn: 'span 2' }}>المؤشر<input type="text" dir="ltr" required value={f.indicator_key} onChange={set('indicator_key')} /></label>
        <label>الكيان<input type="text" dir="ltr" required value={f.entity_code} onChange={set('entity_code')} /></label>
        <label style={{ gridColumn: 'span 2' }}>التسمية<input type="text" required value={f.label_ar} onChange={set('label_ar')} /></label>
        <label>نوع السعر<select value={f.price_type} onChange={set('price_type')}>{Object.entries(PRICE_TYPE).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
        <label>الموثوقية<select value={f.confidence} onChange={set('confidence')}>{Object.entries(CONFIDENCE).map(([k, v]) => <option key={k} value={k}>{v}</option>)}</select></label>
        <label>الدور<select value={f.role} onChange={set('role')}><option value="primary">أساسية</option><option value="alternative">بديلة</option><option value="proxy">تقريبية</option></select></label>
        <label>السوق<input type="text" value={f.market} onChange={set('market')} /></label>
        <label>شروط التسليم<select value={f.incoterm} onChange={set('incoterm')}><option value="">—</option>{['FOB', 'CFR', 'CIF', 'EXW', 'DAP', 'DDP', 'FCA'].map((x) => <option key={x}>{x}</option>)}</select></label>
        <label>الوحدة<input type="text" value={f.unit} onChange={set('unit')} /></label>
        <label>العملة<input type="text" dir="ltr" maxLength={3} value={f.currency} onChange={set('currency')} /></label>
      </div>
      <div className="intel-chips">
        <button className="btn" type="submit" disabled={m.isPending}>ربط السلسلة</button>
        <button className="btn" type="button" onClick={onDone}>إلغاء</button>
        {m.error && <span role="alert" className="muted">{(m.error as Error).message}</span>}
      </div>
    </form>
  );
}

function ChainDetail({ slug }: { slug: string }) {
  const { user } = useAuth();
  const q = useChain(slug);
  const revisions = useChainRevisions(slug);
  const canEdit = !!user?.can_edit_chains;
  const [editing, setEditing] = useState<{ type: 'edge' | 'source' | 'series'; id: number } | null>(null);
  const [tab, setTab] = useState<'explore' | 'manage'>('explore');
  const data = q.data as ChainFull | undefined;
  const byId = useMemo(() => new Map((data?.nodes ?? []).map((n) => [n.id, n])), [data]);
  const groups = useMemo(() => {
    const g = new Map<number, ChainEdge[]>();
    for (const e of data?.edges ?? []) g.set(e.to_node_id, [...(g.get(e.to_node_id) ?? []), e]);
    return [...g.entries()].sort((a, b) => (byId.get(a[0])?.stage ?? 0) - (byId.get(b[0])?.stage ?? 0));
  }, [data, byId]);
  const stages = useMemo(() => {
    const s = new Map<number, ChainNode[]>();
    for (const n of data?.nodes ?? []) s.set(n.stage, [...(s.get(n.stage) ?? []), n]);
    return [...s.entries()].sort((a, b) => a[0] - b[0]);
  }, [data]);

  if (q.isLoading) return <Loading height={240} />;
  if (q.error) return <ErrorState error={q.error as Error} onRetry={() => void q.refetch()} />;
  if (!data) return null;
  const c = data.chain;

  return (
    <>
      <PageHead title={`سلسلة القيمة: ${c.name_ar}`} lead={c.description ?? ''} />
      <p><Link to="/market-intel/chains">← كل السلاسل</Link></p>
      <div className="card">
        <div className="intel-chips">
          <span className="chip">{c.status === 'review' ? 'بانتظار مراجعة المحلل' : c.status === 'published' ? 'منشورة' : 'مسودة'}</span>
          <span className="chip">النسخة {c.version} · {formatDateTime(c.updated_at)}</span>
          {c.is_demo && <span className="chip warning">بيانات تجريبية</span>}
          {!canEdit && <span className="chip">عرض فقط (التعديل للمحلل ومدير النظام)</span>}
        </div>
        {c.route_note && <p className="muted" style={{ marginBottom: 0 }}>{c.route_note}</p>}
      </div>
      <div className="controls" style={{ marginTop: 12 }}>
        <Segmented label="العرض" value={tab} onChange={setTab} options={[{ value: 'explore', label: 'المستكشف' }, { value: 'manage', label: 'الأوزان والمصادر (الإدارة)' }]} />
      </div>
      {tab === 'explore' && <ChainExplorer slug={slug} data={data} />}
      {tab === 'manage' && (<>

      <section className="section">
        <h2>مراحل السلسلة</h2>
        <div className="chain-stages">
          {stages.map(([stage, nodes]) => (
            <div key={stage} className="chain-stage">
              <small className="muted">المرحلة {stage}</small>
              {nodes.map((n) => (
                <a key={n.id} href={`#node-${n.id}`} className={`chain-node kind-${n.kind}`}>
                  <b>{n.name_ar}</b>
                  <small>{KIND[n.kind]}{n.hs_codes.length ? ` · HS ${n.hs_codes.join('، ')}` : ''}</small>
                </a>
              ))}
            </div>
          ))}
        </div>
      </section>

      <section className="section">
        <h2>الأوزان وعوامل التحويل ومصادرها</h2>
        <p className="lead">الوزن = حصة المدخل من تكلفة العقدة التالية، بنطاق أدنى/أعلى يُستخدم في محاكاة الصدمة. كل وزن يحمل أساسه ومصادره بموثوقيتها؛ «ينقصه مصدر» يعني أن الحافة لا تشارك في الحساب حتى يضيف المحلل وزنًا موثّقًا.</p>
        {groups.map(([to, edges]) => {
          const target = byId.get(to);
          const known = edges.filter((e) => e.weight != null);
          const sum = known.reduce((a, e) => a + Number(e.weight), 0);
          const lo = known.reduce((a, e) => a + Number(e.weight_min ?? e.weight), 0);
          const hi = known.reduce((a, e) => a + Number(e.weight_max ?? e.weight), 0);
          return (
            <div key={to} className="card heat-wrap" style={{ marginBottom: 12 }}>
              <div className="controls" style={{ justifyContent: 'space-between' }}>
                <strong>مدخلات «{target?.name_ar}»</strong>
                <span className={`chip ${Math.abs(sum - 1) <= 0.05 ? 'good' : 'warning'}`}>مجموع الأوزان المعروفة {pct(sum)} (النطاق {pct(lo)}–{pct(hi)}){edges.length > known.length ? ` · ${edges.length - known.length} ينقصها مصدر` : ''}</span>
              </div>
              <div className="heat-scroll">
                <table className="heat-table chain-table">
                  <thead><tr><th>المدخل</th><th className="num">الوزن</th><th className="num">النطاق</th><th>الأساس</th><th>عامل التحويل</th><th>المصادر</th>{canEdit && <th />}</tr></thead>
                  <tbody>
                    {edges.map((e) => (
                      <Fragment key={e.id}>
                        <tr>
                          <th scope="row">{byId.get(e.from_node_id)?.name_ar}{e.notes && <small>{e.notes}</small>}</th>
                          <td className="plain num"><strong>{pct(e.weight)}</strong>{e.weight != null && <span className="w-bar"><i style={{ insetInlineStart: `${Number(e.weight_min ?? e.weight) * 100}%`, width: `${(Number(e.weight_max ?? e.weight) - Number(e.weight_min ?? e.weight)) * 100}%` }} /><b style={{ insetInlineStart: `${Number(e.weight) * 100}%` }} /></span>}</td>
                          <td className="plain num">{e.weight_min != null ? `${pct(e.weight_min)}–${pct(e.weight_max)}` : '—'}</td>
                          <td className="plain"><span className={`chip ${e.weight_basis === 'missing' ? 'critical' : e.weight_basis === 'derived' || e.weight_basis === 'analyst' ? 'warning' : ''}`}>{BASIS[e.weight_basis] ?? e.weight_basis}</span></td>
                          <td className="plain">{e.conversion_factor != null ? <><b className="num">{formatNumber(Number(e.conversion_factor), 3)}</b> {e.conversion_unit}{e.conversion_min != null && <small dir="ltr">{formatNumber(Number(e.conversion_min), 3)}–{formatNumber(Number(e.conversion_max), 3)}</small>}</> : '—'}{e.logistics_note && <small>{e.logistics_note}</small>}</td>
                          <td className="plain chain-sources">
                            {e.sources.length === 0 && <span className="muted">لا مصدر</span>}
                            {e.sources.map((s) => (
                              <div key={s.id} title={s.excerpt ?? ''}>
                                <span className={`chip ${confTone(s.reliability)}`}>{CONFIDENCE[s.reliability]}</span>{' '}
                                {s.url ? <a href={s.url} target="_blank" rel="noopener noreferrer">{s.title}</a> : s.title}
                                <small>{s.publisher}{s.year ? ` (${s.year})` : ''} · {s.applies_to === 'weight' ? 'للوزن' : s.applies_to === 'conversion' ? 'للتحويل' : 'لكليهما'}{s.excerpt ? ` — ${s.excerpt}` : ''}</small>
                              </div>
                            ))}
                          </td>
                          {canEdit && <td className="plain"><button className="btn" type="button" onClick={() => setEditing({ type: 'edge', id: e.id })}>تعديل</button> <button className="btn" type="button" onClick={() => setEditing({ type: 'source', id: e.id })}>+ مصدر</button></td>}
                        </tr>
                        {editing?.id === e.id && editing.type !== 'series' && (
                          <tr><td colSpan={7} className="plain">{editing.type === 'edge' ? <EdgeEditor edge={e} slug={slug} onDone={() => setEditing(null)} /> : <SourceForm edge={e} slug={slug} onDone={() => setEditing(null)} />}</td></tr>
                        )}
                      </Fragment>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          );
        })}
      </section>

      <section className="section">
        <h2>العقد وسلاسل أسعارها</h2>
        <p className="lead">لكل عقدة: السلاسل السعرية المرتبطة بنوع السعر (مرجعي، مؤشر، قيمة جمركية، عقود آجلة) وموثوقيتها وسوقها وشروط تسليمها وآخر قيمة وتاريخ جلبها ووتيرة تحديثها؛ ونتيجة التحقق الإحصائي من أوزان مدخلاتها.</p>
        {data.nodes.map((n) => (
          <div key={n.id} id={`node-${n.id}`} className={`card chain-node-card kind-${n.kind}`}>
            <div className="controls" style={{ justifyContent: 'space-between' }}>
              <div><h3 style={{ margin: 0 }}>{n.name_ar}</h3><small className="muted">{KIND[n.kind]} · المرحلة {n.stage}{n.unit ? ` · الوحدة: ${n.unit}` : ''}{n.hs_codes.length ? ` · HS ${n.hs_codes.join('، ')}` : ''}</small></div>
              {canEdit && <button className="btn" type="button" onClick={() => setEditing({ type: 'series', id: n.id })}>+ سلسلة سعرية</button>}
            </div>
            {n.notes && <p className="muted">{n.notes}</p>}
            {n.series.length === 0 && n.kind !== 'conversion' && <p className="muted">لا سلسلة سعرية مرتبطة بعد.</p>}
            {n.kind === 'conversion' && n.series.length === 0 && <p className="muted">تكاليف تحويل تُعامل ثابتة في محاكاة الصدمة.</p>}
            {n.series.map((s) => <SeriesBadge key={s.id} s={s} />)}
            {editing?.type === 'series' && editing.id === n.id && <SeriesForm node={n} slug={slug} onDone={() => setEditing(null)} />}
            {n.check && (
              <div className={`chain-check ${n.check.flagged ? 'flagged' : ''}`}>
                <b>{n.check.flagged ? '⚠ تعارض إحصائي مع الأوزان المقدّرة' : 'التحقق الإحصائي: متسق مع الأوزان'}</b>
                <small className="muted"> — انحدار التغير الشهري ({n.check.window_from}–{n.check.window_to}، {n.check.n_obs} شهرًا، R² {formatNumber(n.check.r2, 2)})، {formatDateTime(n.check.computed_at)}</small>
                <ul>
                  {n.check.results.inputs.map((i) => (
                    <li key={i.from}>{i.name_ar}: مرونة {formatNumber(i.beta, 2)} (±{formatNumber(i.se, 2)}) بتأخر {i.lag_months} شهر مقابل وزن {pct(i.weight)} [{pct(i.weight_min)}–{pct(i.weight_max)}]{i.conflict ? ' ⚠' : ''}</li>
                  ))}
                </ul>
              </div>
            )}
          </div>
        ))}
      </section>

      <section className="section">
        <h2>سجل النسخ</h2>
        <ul>
          {revisions.data?.data.map((r) => <li key={r.id}>النسخة {r.version} · {formatDateTime(r.created_at)} · {r.editor ?? 'النظام'} — {r.note}</li>)}
        </ul>
      </section>
      </>)}
    </>
  );
}
