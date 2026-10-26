import { useEffect, useMemo, useRef, useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/core/api';
import { Segmented } from '@/core/components/Segmented';
import { LineChartView } from '@/core/components/LineChartView';
import { Loading } from '@/core/components/States';
import { formatDateTime, formatNumber, compact } from '@/core/format';
import { CONFIDENCE, FREQ, KIND, PRICE_TYPE, useChainAnalytics, useChains, useSeriesHistory, type ChainFull, type ChainNode, type NodeAnalytics } from './api';
import { effectiveWeights, propagateRange } from './shock';

const pct = (v: number | null | undefined, d = 1) => (v == null ? '—' : `${v > 0 ? '+' : ''}${formatNumber(v, d)}%`);
const share = (v: number | null | undefined) => (v == null ? '—' : `${formatNumber(v * 100, 1)}%`);
const RANGES = [
  { value: '6m', label: '6 أشهر', months: 6 },
  { value: '1y', label: 'سنة', months: 12 },
  { value: '5y', label: '5 سنوات', months: 60 },
  { value: 'all', label: 'الكل', months: 0 },
];
const fromFor = (months: number) => {
  if (!months) return undefined;
  const d = new Date();
  d.setMonth(d.getMonth() - months);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
};

/** يقيس عرض العنصر ليتكيف الرسم مع المساحة المتاحة (بما فيها التكبير والتصغير). */
function useWidth<T extends HTMLElement>(): [React.RefObject<T>, number] {
  const ref = useRef<T>(null);
  const [w, setW] = useState(900);
  useEffect(() => {
    if (!ref.current || typeof ResizeObserver === 'undefined') return;
    const ro = new ResizeObserver(([e]) => setW(Math.round(e.contentRect.width)));
    ro.observe(ref.current);
    return () => ro.disconnect();
  }, []);
  return [ref, w];
}

/**
 * رسم السلسلة: أعمدة بالمراحل (من الخام يمينًا إلى النهائي يسارًا)، والعقد أزرار HTML (نص عربي سليم ويمكن الوصول إليها بلوحة المفاتيح)،
 * والحواف SVG خلفها بسماكة الوزن. العرض يتكيف مع المساحة، وتحت حد أدنى يظهر تمرير أفقي بدل تصغير النص.
 */
function ChainGraph({ data, selected, onSelect, impact }: { data: ChainFull; selected: number | null; onSelect: (id: number) => void; impact: Record<number, { mid: number }> | null }) {
  const [ref, cw] = useWidth<HTMLDivElement>();
  const stages = [...new Set(data.nodes.map((n) => n.stage))].sort((a, b) => a - b);
  const colW = Math.max(160, Math.min(250, Math.floor((cw - 8) / stages.length)));
  const boxW = colW - 34;
  const boxH = 64;
  const gap = 16;
  const maxRows = Math.max(...stages.map((st) => data.nodes.filter((n) => n.stage === st).length));
  const width = Math.max(cw - 8, stages.length * colW);
  const height = maxRows * (boxH + gap) + 24;
  const pos = new Map<number, { x: number; y: number }>();
  stages.forEach((st, i) => {
    const col = data.nodes.filter((n) => n.stage === st);
    const offset = (height - col.length * (boxH + gap) + gap) / 2;
    col.forEach((n, j) => pos.set(n.id, { x: width - (i + 1) * colW + (colW - boxW) / 2, y: offset + j * (boxH + gap) }));
  });
  const bg = (n: ChainNode) => {
    const v = impact?.[n.id]?.mid ?? 0;
    if (!impact || Math.abs(v) < 0.05) return undefined;
    const a = Math.min(0.85, 0.18 + Math.abs(v) / 25);
    return v > 0 ? `rgba(196, 68, 65, ${a})` : `rgba(39, 150, 121, ${a})`;
  };
  return (
    <div className="chain-graph" ref={ref} role="group" aria-label={`رسم سلسلة ${data.chain.name_ar}`}>
      <div className="chain-canvas" style={{ width, height }}>
        <svg width={width} height={height} aria-hidden="true">
          {data.edges.map((e) => {
            const a = pos.get(e.from_node_id);
            const b = pos.get(e.to_node_id);
            if (!a || !b) return null;
            const x1 = a.x;
            const y1 = a.y + boxH / 2;
            const x2 = b.x + boxW;
            const y2 = b.y + boxH / 2;
            const w = e.weight == null ? null : Number(e.weight);
            const mx = (x1 + x2) / 2;
            return (
              <g key={e.id}>
                <path d={`M${x1},${y1} C${mx},${y1} ${mx},${y2} ${x2},${y2}`} fill="none" stroke={w == null ? 'var(--border)' : 'var(--accent)'} strokeOpacity={w == null ? 0.8 : 0.5} strokeWidth={w == null ? 1.2 : 1.5 + w * 9} strokeDasharray={w == null ? '5 4' : undefined} />
                <text x={mx} y={(y1 + y2) / 2 - 6} textAnchor="middle" fontSize="11" fill="var(--text-secondary)" style={{ paintOrder: 'stroke', stroke: 'var(--surface-1)', strokeWidth: 3 }}>{w == null ? 'وزن ناقص' : share(w)}</text>
              </g>
            );
          })}
        </svg>
        {data.nodes.map((n) => {
          const p = pos.get(n.id)!;
          const v = impact?.[n.id]?.mid;
          return (
            <button key={n.id} type="button" className={`chain-gnode kind-${n.kind}${selected === n.id ? ' selected' : ''}`} style={{ left: p.x, top: p.y, width: boxW, height: boxH, background: bg(n) }} onClick={() => onSelect(n.id)} aria-label={n.name_ar} aria-pressed={selected === n.id} title={n.name_ar}>
              <b>{n.name_ar}</b>
              <small>{v != null && Math.abs(v) >= 0.05 ? `الأثر ${pct(v)}` : KIND[n.kind]}</small>
            </button>
          );
        })}
      </div>
    </div>
  );
}

function NodeCard({ slug, data, node, analytics, eff, finalName }: { slug: string; data: ChainFull; node: ChainNode; analytics: NodeAnalytics | undefined; eff: { mid: number; low: number; high: number } | undefined; finalName: string }) {
  const [seriesId, setSeriesId] = useState<number | null>((node.series.find((x) => x.latest && x.latest.points > 1) ?? node.series[0])?.id ?? null);
  const [range, setRange] = useState('5y');
  const h = useSeriesHistory(slug, seriesId, fromFor(RANGES.find((r) => r.value === range)!.months));
  const s = node.series.find((x) => x.id === seriesId);
  const out = data.edges.filter((e) => e.from_node_id === node.id);
  const name = (id: number) => data.nodes.find((n) => n.id === id)?.name_ar ?? '';
  return (
    <div className="card mi-nodecard">
      <div className="controls" style={{ justifyContent: 'space-between' }}>
        <div>
          <h3 style={{ margin: 0 }}>{node.name_ar}</h3>
          <small className="muted">{KIND[node.kind]}{node.unit ? ` · ${node.unit}` : ''}{node.hs_codes.length ? ` · HS ${node.hs_codes.join('، ')}` : ''}</small>
        </div>
        {eff && <span className="chip">وزنه الفعلي في سعر {finalName}: <b className="num">{share(eff.mid)}</b> ({share(eff.low)}–{share(eff.high)})</span>}
      </div>
      {node.notes && <p className="muted">{node.notes}</p>}

      {out.length > 0 && (
        <p>{out.map((e) => <span key={e.id} className="chip" style={{ marginInlineEnd: 6 }}>حصته من تكلفة {name(e.to_node_id)}: {e.weight == null ? 'ينقصها مصدر' : `${share(Number(e.weight))} (${share(Number(e.weight_min ?? e.weight))}–${share(Number(e.weight_max ?? e.weight))})`}{e.conversion_factor != null ? ` · ${formatNumber(Number(e.conversion_factor), 3)} ${e.conversion_unit ?? ''}` : ''}</span>)}</p>
      )}

      {node.series.length > 0 ? (
        <>
          <div className="intel-chips" role="group" aria-label="السلسلة السعرية">
            {node.series.map((x) => <button key={x.id} type="button" className={`chip${x.id === seriesId ? ' good' : ''}`} onClick={() => setSeriesId(x.id)}>{x.label_ar.replace(/^.*?: /, '')} · {PRICE_TYPE[x.price_type]}</button>)}
          </div>
          {s && (
            <p className="muted mi-hint">
              {PRICE_TYPE[s.price_type]} · {CONFIDENCE[s.confidence]}{s.market ? ` · ${s.market}` : ''}{s.incoterm ? ` · ${s.incoterm}` : ''} · {FREQ[s.frequency] ?? s.frequency} (لا يُحدَّث لحظيًا)
              {s.latest ? <> · آخر قيمة <b className="num">{formatNumber(s.latest.value, 2)}</b> {s.unit ?? ''} للفترة <span dir="ltr">{s.latest.period}</span>، جُلبت {formatDateTime(s.latest.updated_at)} من {s.latest.dataset}</> : ' · لا بيانات بعد'}
            </p>
          )}
          <div className="controls"><Segmented label="المدى" value={range} onChange={setRange} options={RANGES.map((r) => ({ value: r.value, label: r.label }))} /></div>
          {h.isLoading ? <Loading height={200} /> : h.data && h.data.points.length > 1 ? (
            <LineChartView series={[{ id: String(seriesId), label: s?.label_ar ?? '', color: 'var(--series-1)', points: h.data.points }]} unit={s?.unit} height={240} zeroLine={false} />
          ) : <p className="muted">لا نقاط كافية لهذا المدى.</p>}
        </>
      ) : <p className="muted">{node.kind === 'conversion' ? 'تكاليف تحويل بلا سلسلة سعرية؛ تُعامل ثابتة في المحاكاة.' : 'لا سلسلة سعرية مرتبطة بعد.'}</p>}

      <div className="grid cols-3" style={{ marginTop: 10 }}>
        <div>
          <h4>مؤشر المخاطر</h4>
          {analytics?.risk ? (
            <>
              <p><b className="num" style={{ fontSize: 22 }}>{formatNumber(analytics.risk.score, 0)}</b> / 100</p>
              <ul className="mi-list">{Object.values(analytics.risk.components).map((c) => <li key={c.label}>{c.label}: {c.label.includes('HHI') ? formatNumber(c.value, 0) : share(c.value)} ← {formatNumber(c.score, 0)}</li>)}</ul>
              <small className="muted">{analytics.risk.note}</small>
            </>
          ) : <p className="muted">غير متاح بعد.</p>}
        </div>
        <div>
          <h4>الدول المصدّرة {analytics?.trade ? `(${analytics.trade.year}، HS ${analytics.trade.hs4})` : ''}</h4>
          {analytics?.trade ? (
            <>
              <ul className="mi-list">{analytics.trade.exporters.slice(0, 6).map((x) => <li key={x.iso}>{x.name_ar}: {share(x.share)} <small className="muted">({compact(x.value_usd)} $)</small></li>)}</ul>
              <small className="muted">التجارة العالمية {compact(analytics.trade.total_usd)} دولار · HHI {formatNumber(analytics.trade.hhi, 0)} · BACI عبر OEC</small>
            </>
          ) : <p className="muted">يُحسب أسبوعيًا من BACI.</p>}
        </div>
        <div>
          <h4>التأخر حتى المنتج النهائي</h4>
          {analytics?.lag ? (
            <>
              <p>أعلى ارتباط بعد <b className="num">{analytics.lag.best_lag}</b> شهر (ر = {formatNumber(analytics.lag.best_corr, 2)}، {analytics.lag.n} شهرًا)</p>
              <div className="lag-bars" aria-label="الارتباط حسب التأخر">
                {analytics.lag.by_lag.map((l) => <span key={l.lag} title={`تأخر ${l.lag}: ${formatNumber(l.corr, 2)}`} style={{ height: `${Math.max(2, Math.abs(l.corr) * 60)}px`, background: l.lag === analytics.lag!.best_lag ? 'var(--accent)' : l.corr < 0 ? '#c44441' : 'var(--series-2)' }} />)}
              </div>
              <small className="muted">0 ← 12 شهرًا؛ ارتباط التغيرات الشهرية لسلسلة العقدة مع سلسلة المنتج النهائي.</small>
            </>
          ) : <p className="muted">يحتاج 24 شهرًا مشتركًا على الأقل.</p>}
        </div>
      </div>
    </div>
  );
}

function ShockPanel({ data, shock, setShock }: { data: ChainFull; shock: { node: number; pct: number }; setShock: (s: { node: number; pct: number }) => void }) {
  const impacts = useMemo(() => propagateRange(data.nodes, data.edges, { [shock.node]: shock.pct }), [data, shock]);
  const final = data.nodes.find((n) => n.kind === 'final');
  const downstream = data.nodes.filter((n) => n.id !== shock.node && Math.abs(impacts[n.id].mid) >= 0.01).sort((a, b) => a.stage - b.stage);
  const src = data.nodes.find((n) => n.id === shock.node);
  return (
    <div className="card">
      <h3>محاكاة انتشار الصدمة السعرية</h3>
      <div className="controls">
        <label className="intel-field" style={{ minWidth: 220 }}>
          <span>المدخل</span>
          <select value={shock.node} onChange={(e) => setShock({ ...shock, node: Number(e.target.value) })} aria-label="عقدة الصدمة">
            {data.nodes.filter((n) => n.kind !== 'final').map((n) => <option key={n.id} value={n.id}>{n.name_ar}</option>)}
          </select>
        </label>
        <label className="intel-field" style={{ flex: 1 }}>
          <span>التغير في السعر: <b className="num">{pct(shock.pct, 0)}</b></span>
          <input type="range" min={-50} max={50} step={1} value={shock.pct} onChange={(e) => setShock({ ...shock, pct: Number(e.target.value) })} aria-label="نسبة الصدمة" />
        </label>
      </div>
      {final && src && (
        <p className="mi-shock-headline">
          إذا تغيّر سعر <b>{src.name_ar}</b> بنسبة <b className="num">{pct(shock.pct, 0)}</b> فالأثر المتوقع على سعر <b>{final.name_ar}</b>: <b className="num">{pct(impacts[final.id].mid)}</b> (النطاق {pct(impacts[final.id].low)} إلى {pct(impacts[final.id].high)}).
        </p>
      )}
      {downstream.length > 0 ? (
        <table className="heat-table" aria-label="أثر الصدمة">
          <thead><tr><th>المرحلة اللاحقة</th><th className="num">الأثر المركزي</th><th className="num">النطاق</th></tr></thead>
          <tbody>{downstream.map((n) => <tr key={n.id}><th scope="row">{n.name_ar}</th><td className="plain num"><b>{pct(impacts[n.id].mid, 2)}</b></td><td className="plain num">{pct(impacts[n.id].low, 2)} – {pct(impacts[n.id].high, 2)}</td></tr>)}</tbody>
        </table>
      ) : <p className="muted">لا أثر لاحقًا: المدخل غير موصول بأوزان موثّقة إلى المراحل التالية.</p>}
      <p className="source-note">الحساب: أثر كل مرحلة = Σ (وزن المدخل × أثره)، بالأوزان المركزية وبالحدود الدنيا/العليا للنطاق. يفترض انتقالًا كاملًا للتكلفة إلى السعر وثبات تكاليف التحويل والهوامش؛ التوقيت يقرأ من تحليل التأخر. الحواف التي ينقصها مصدر لا تنقل الأثر.</p>
    </div>
  );
}

function CompareChains({ data, analytics }: { data: ChainFull; analytics: NodeAnalytics[] }) {
  const chains = useChains();
  const [other, setOther] = useState<string>('');
  const otherFull = useQuery({ queryKey: ['chain', other], queryFn: ({ signal }) => api<ChainFull>(`intel/chains/${other}`, {}, signal), enabled: !!other });
  const otherAn = useChainAnalytics(other || undefined);
  const profile = (d: ChainFull, an: NodeAnalytics[]) => {
    const final = d.nodes.find((n) => n.kind === 'final');
    if (!final) return null;
    const eff = effectiveWeights(d.nodes, d.edges, final.id);
    const rows = d.nodes.filter((n) => ['raw', 'energy', 'consumable'].includes(n.kind)).map((n) => {
      const a = an.find((x) => x.node_id === n.id);
      return { name: n.name_ar, eff: eff[n.id]?.mid ?? 0, vol: a?.volatility ?? null, hhi: a?.trade?.hhi ?? null };
    }).sort((a, b) => b.eff - a.eff);
    const covered = rows.reduce((s, r) => s + r.eff, 0);
    const sensitivity = rows.reduce((s, r) => s + r.eff * (r.vol ?? 0), 0);
    const concentration = rows.reduce((s, r) => s + r.eff * (r.hhi ?? 0), 0) / (covered || 1);
    return { final: final.name_ar, rows, covered, sensitivity, concentration };
  };
  const mine = profile(data, analytics);
  const theirs = otherFull.data && otherAn.data ? profile(otherFull.data, otherAn.data.nodes) : null;
  const col = (p: ReturnType<typeof profile>, title: string) => p && (
    <div className="card">
      <h4>{title}</h4>
      <p className="muted">حساسية المنتج للمدخلات (Σ الوزن الفعلي × التقلب السنوي): <b className="num">{share(p.sensitivity)}</b> · تركّز موزون للمصدّرين: <b className="num">{formatNumber(p.concentration, 0)}</b> HHI · الأوزان المغطّاة {share(p.covered)}</p>
      <table className="heat-table"><thead><tr><th>المدخل</th><th className="num">وزنه الفعلي</th><th className="num">التقلب</th><th className="num">HHI</th></tr></thead>
        <tbody>{p.rows.map((r) => <tr key={r.name}><th scope="row">{r.name}</th><td className="plain num">{share(r.eff)}</td><td className="plain num">{share(r.vol)}</td><td className="plain num">{r.hhi == null ? '—' : formatNumber(r.hhi, 0)}</td></tr>)}</tbody>
      </table>
    </div>
  );
  return (
    <div>
      <div className="controls">
        <label className="intel-field" style={{ minWidth: 260 }}>
          <span>قارن مع سلسلة أخرى (منتج بديل أو مكافئ)</span>
          <select value={other} onChange={(e) => setOther(e.target.value)} aria-label="السلسلة المقارنة">
            <option value="">اختر سلسلة</option>
            {chains.data?.data.filter((c) => c.slug !== data.chain.slug).map((c) => <option key={c.slug} value={c.slug}>{c.name_ar}</option>)}
          </select>
        </label>
      </div>
      <div className="grid cols-2">
        {col(mine, data.chain.name_ar)}
        {other && (theirs ? col(theirs, otherFull.data!.chain.name_ar) : <Loading height={160} />)}
      </div>
      {mine && theirs && (
        <p className="mi-shock-headline">{mine.sensitivity > theirs.sensitivity ? data.chain.name_ar : otherFull.data!.chain.name_ar} أكثر حساسية لتقلب المدخلات ({share(Math.max(mine.sensitivity, theirs.sensitivity))} مقابل {share(Math.min(mine.sensitivity, theirs.sensitivity))})، و{mine.concentration > theirs.concentration ? data.chain.name_ar : otherFull.data!.chain.name_ar} أكثر تعرضًا لتركّز الدول المصدّرة.</p>
      )}
      <p className="source-note">المقارنة تعتمد على الأوزان الموثّقة والتقلب التاريخي (آخر 36 شهرًا) وتركّز المصدّرين (BACI)؛ المدخلات بلا سلسلة سعرية أو بلا وزن لا تدخل الحساب، ونسبة «الأوزان المغطّاة» تبيّن ذلك.</p>
    </div>
  );
}

/** مستكشف سلسلة القيمة: الرسم، وبطاقة العقدة، ومحاكاة الصدمة، والتأخر، ومقارنة السلاسل. */
export function ChainExplorer({ slug, data }: { slug: string; data: ChainFull }) {
  const an = useChainAnalytics(slug);
  const final = data.nodes.find((n) => n.kind === 'final');
  const firstRaw = data.nodes.find((n) => n.kind !== 'final' && n.kind !== 'conversion');
  const [selected, setSelected] = useState<number | null>(final?.id ?? null);
  const [view, setView] = useState<'node' | 'shock' | 'compare'>('node');
  const [shock, setShock] = useState({ node: firstRaw?.id ?? data.nodes[0].id, pct: 10 });
  const eff = useMemo(() => (final ? effectiveWeights(data.nodes, data.edges, final.id) : {}), [data, final]);
  const impact = useMemo(() => (view === 'shock' ? propagateRange(data.nodes, data.edges, { [shock.node]: shock.pct }) : null), [data, view, shock]);
  const node = data.nodes.find((n) => n.id === selected);
  const analyticsFor = (id: number) => an.data?.nodes.find((x) => x.node_id === id);

  return (
    <section className="section">
      <div className="controls" style={{ justifyContent: 'space-between' }}>
        <h2 style={{ margin: 0 }}>المستكشف</h2>
        <Segmented label="أداة" value={view} onChange={setView} options={[{ value: 'node', label: 'بطاقة العقدة' }, { value: 'shock', label: 'محاكاة الصدمة' }, { value: 'compare', label: 'مقارنة السلاسل' }]} />
      </div>
      <ChainGraph data={data} selected={view === 'shock' ? shock.node : selected} onSelect={(id) => (view === 'shock' ? setShock({ ...shock, node: id }) : (setSelected(id), setView('node')))} impact={impact} />
      <p className="muted mi-hint">اضغط عقدة لعرض بطاقتها. سماكة الخط = الوزن؛ الخط المتقطع = وزن ينقصه مصدر. {view === 'shock' ? 'الأحمر ارتفاع والأخضر انخفاض متوقع.' : ''}</p>
      {view === 'node' && node && <NodeCard key={node.id} slug={slug} data={data} node={node} analytics={analyticsFor(node.id)} eff={eff[node.id]} finalName={final?.name_ar ?? ''} />}
      {view === 'shock' && <ShockPanel data={data} shock={shock} setShock={setShock} />}
      {view === 'compare' && <CompareChains data={data} analytics={an.data?.nodes ?? []} />}
    </section>
  );
}
