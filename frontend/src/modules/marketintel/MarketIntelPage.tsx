import { useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { PriceCompare } from './PriceCompare';
import { Subscriptions } from './Subscriptions';
import { OrgPrices } from './OrgPrices';
import { EarlyWarningBoard } from './EarlyWarningBoard';
import { ProductPicker } from './ProductPicker';
import { PageHead } from '@/core/components/PageHead';
import { Segmented } from '@/core/components/Segmented';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { Loading, ErrorState } from '@/core/components/States';
import { useAuth } from '@/core/auth';
import type { SeriesItem } from '@/core/hooks';
import { compact, formatDateTime, formatNumber, OMR_PER_USD } from '@/core/format';
import { Marketplaces } from './Marketplaces';
import { isPending, useAnalysis, useAsk, useBrief, useCapabilities, useResolve, type Analysis, type Component, type Mode, type Product, type Signal } from './useMarketIntel';

const COMPONENTS: { key: Component; label: string; hint: string }[] = [
  { key: 'scale', label: 'الحجم', hint: 'قدرة الدولة على التوريد (أو حجم السوق) بالقيمة' },
  { key: 'growth', label: 'النمو', hint: 'معدل نمو سنوي مركّب خلال آخر 4 سنوات' },
  { key: 'price', label: 'السعر', hint: 'سعر الوحدة (دولار/طن) مقابل وسيط المرشَّحين: الأدنى أفضل للمشتري والأعلى أفضل للمصدّر' },
  { key: 'logistics', label: 'اللوجستيات', hint: 'مؤشر الأداء اللوجستي للبنك الدولي (1–5)' },
  { key: 'risk', label: 'المخاطر', hint: 'الاستقرار السياسي (البنك الدولي) ومؤشر INFORM للمخاطر؛ الأعلى أأمن' },
];

function scoreColor(v: number | null): string {
  if (v == null) return 'var(--surface-2)';
  const t = v / 100;
  const [r, g, b] = t < 0.5 ? [196, 68, 65] : t < 0.7 ? [214, 158, 63] : [39, 150, 121];
  return `rgba(${r}, ${g}, ${b}, ${0.3 + Math.abs(t - 0.5) * 1.1})`;
}

function composite(c: { components: Record<Component, number | null> }, w: Record<Component, number>): number | null {
  let sum = 0;
  let weight = 0;
  for (const k of Object.keys(w) as Component[]) {
    const v = c.components[k];
    if (v != null && w[k] > 0) {
      sum += v * w[k];
      weight += w[k];
    }
  }
  return weight > 0 ? sum / weight : null;
}

function hhiLabel(v: number | null): { text: string; tone: string } {
  if (v == null) return { text: '—', tone: '' };
  if (v < 1500) return { text: 'تنوّع جيد', tone: 'good' };
  if (v < 2500) return { text: 'تركّز متوسط', tone: 'warning' };
  return { text: 'تركّز مرتفع', tone: 'critical' };
}


const ComtradeUnit: Record<number, string> = { 5: 'قطعة', 6: 'زوج', 7: 'لتر', 8: 'كغم', 2: 'م²', 4: 'م', 11: 'م³', 12: 'غم', 13: 'رأس' };

const LEVEL: Record<Signal['level'], string> = { risk: 'خطر', opportunity: 'فرصة', info: 'ملاحظة' };

function line(id: string, label: string, color: string, points: [string, number][]): SeriesItem {
  return { id, label, color, points };
}

function TradeIntel() {
  const { user } = useAuth();
  const [mode, setMode] = useState<Mode>('import');
  const [product, setProduct] = useState<Product | null>(null);
  const analysisQ = useAnalysis(product?.id ?? null, mode);
  const analysis = analysisQ.data && !isPending(analysisQ.data) ? (analysisQ.data as Analysis) : null;
  const briefQ = useBrief(product?.id ?? null, mode, analysis !== null);
  const [weights, setWeights] = useState<Record<Component, number> | null>(null);
  const [selected, setSelected] = useState<string[] | null>(null);
  const [question, setQuestion] = useState('');
  const ask = useAsk(product?.id ?? null, mode);
  const caps = useCapabilities();
  const [resolveText, setResolveText] = useState('');
  const resolve = useResolve(resolveText, !!user && caps.data?.ai === true);
  const resolved = resolve.data && !isPending(resolve.data) ? resolve.data.data : [];

  const w = weights ?? analysis?.weights ?? { scale: 0.25, growth: 0.15, price: 0.3, logistics: 0.1, risk: 0.2 };
  const ranked = useMemo(() => (analysis ? analysis.candidates.map((c) => ({ ...c, score: composite(c, w) })).sort((a, b) => (b.score ?? -1) - (a.score ?? -1)) : []), [analysis, w]);
  const priceIsos = selected ?? ranked.slice(0, 5).map((c) => c.iso);
  const uvSeries = useMemo<SeriesItem[]>(
    () => ranked.filter((c) => priceIsos.includes(c.iso) && Object.keys(c.uv_series).length > 1).map((c, i) => line(c.iso, c.name_ar, `var(--series-${(i % 8) + 1})`, Object.entries(c.uv_series).sort(([a], [b]) => a.localeCompare(b)).map(([y, v]) => [y, v]))),
    [ranked, priceIsos.join(',')],
  );
  const omanTrend = useMemo<SeriesItem[]>(() => (analysis ? [line('oman', mode === 'import' ? 'واردات عُمان' : 'صادرات عُمان', 'var(--series-1)', Object.entries(analysis.oman_trend).map(([y, v]) => [y, v.value]))] : []), [analysis, mode]);
  const worldUv = useMemo<SeriesItem[]>(() => (analysis ? [line('world', 'سعر الوحدة العالمي', 'var(--series-2)', Object.entries(analysis.world_trend).filter(([, v]) => v.unit_value != null).map(([y, v]) => [y, v.unit_value!]))] : []), [analysis]);
  const worldValue = useMemo<SeriesItem[]>(() => (analysis ? [line('worldv', 'التجارة العالمية للمنتج', 'var(--series-3)', Object.entries(analysis.world_trend).map(([y, v]) => [y, v.value]))] : []), [analysis]);

  const pick = (p: Product) => {
    setProduct(p);
    setWeights(null);
    setSelected(null);
    setResolveText('');
    ask.reset();
  };

  const s = analysis?.summary;
  const hhi = hhiLabel(s?.hhi_oman ?? null);
  const side = mode === 'import' ? 'الموردين' : 'الأسواق';
  const priceGap = s?.oman_unit_value && s.median_unit_value ? (s.oman_unit_value - s.median_unit_value) / s.median_unit_value : null;
  const brief = briefQ.data && !isPending(briefQ.data) ? briefQ.data : null;
  const omanMfn = analysis?.tariffs_oman.find((t) => t.agreement.includes('Most Favoured'));
  const up = analysis?.unit_prices ?? null;
  const unitLabel = up?.unit && up.unit !== 'كغم' ? up.unit : null;
  const [view, setView] = useState<'summary' | 'ranking' | 'prices' | 'trends' | 'tariffs'>('summary');

  return (
    <>
      <div className="card intel-search">
        <div className="controls">
          <Segmented label="الهدف" value={mode} onChange={setMode} options={[{ value: 'import', label: 'أبحث عن موردين (استيراد)' }, { value: 'export', label: 'أبحث عن أسواق (تصدير)' }]} />
        </div>
        <ProductPicker value={product} onPick={pick} onNoMatch={caps.data?.ai && user ? setResolveText : undefined} />
        {resolveText && (
          <div className="intel-results" aria-live="polite">
            {resolve.data && isPending(resolve.data) && <p className="muted">الذكاء الاصطناعي يحدّد رمز «{resolveText}»…</p>}
            {resolve.data && !isPending(resolve.data) && resolved.length === 0 && <p className="muted">لم يتعرّف على رمز مناسب؛ أعد الصياغة.</p>}
            {resolved.map((p) => (
              <button key={`ai-${p.id}`} type="button" className="intel-result" onClick={() => pick(p)}>
                <strong dir="ltr">{p.hs4}</strong><span>{p.name_ar}</span><span className="chip">اقتراح الذكاء الاصطناعي</span>
              </button>
            ))}
          </div>
        )}
        {caps.data?.ai === false && <p className="muted mi-hint">لا تجد منتجك؟ مع مفتاح Anthropic (من الإعدادات) يحدّد الذكاء الاصطناعي رمزه من وصفك الحر.</p>}
      </div>

      {!product && <p className="muted" style={{ marginTop: 16 }}>اختر منتجًا للبدء. التقرير يجمع: التجارة الثنائية (BACI)، واتجاهات 10 سنوات، والرسوم الجمركية والاتفاقيات (WITS)، وتعقيد المنتج والميزة النسبية (OEC)، وأسعار السلع الشهرية (صندوق النقد)، والمخاطر واللوجستيات (البنك الدولي وINFORM)، ثم إشارات وتوصية.</p>}

      {product && (
        <section className="section" aria-live="polite">
          <h2>{product.name_ar} <small dir="ltr" className="muted">HS {product.hs4}</small></h2>
          {analysisQ.error && <ErrorState error={analysisQ.error as Error} onRetry={() => void analysisQ.refetch()} />}
          {!analysis && !analysisQ.error && (
            <div>
              <Loading height={160} />
              <p className="muted">نجمع التقرير من عدة مصادر (تجارة، رسوم، تعقيد، أسعار سلع)؛ أول طلب للمنتج يستغرق دقيقة تقريبًا ثم يُحفَظ أسبوعًا…</p>
            </div>
          )}

          {analysis && s && (
            <>
              <div className="card mi-reporthead">
                <div>
                  <small className="muted">{analysis.product.section_ar ?? ''}</small>
                  <div className="mi-reporthead-stats">
                    <span><b className="num">{compact(s.market_value)}</b> سوق عالمي (دولار)</span>
                    <span><b className="num">{compact(s.oman_value)}</b> {mode === 'import' ? 'واردات' : 'صادرات'} عُمان</span>
                    {up?.oman_total?.unit_price != null && <span><b className="num">{formatNumber(up.oman_total.unit_price * OMR_PER_USD, 2)}</b> ر.ع / {up.unit}</span>}
                    <span><b className="num">{analysis.signals.length}</b> إشارات</span>
                    <span className="muted">بيانات {s.year} · أُعدّ {formatDateTime(analysis.generated_at)}</span>
                  </div>
                </div>
                <Segmented label="أقسام التقرير" value={view} onChange={setView} options={[{ value: 'summary', label: 'الملخص' }, { value: 'ranking', label: mode === 'import' ? 'الموردون' : 'الأسواق' }, { value: 'prices', label: 'الأسعار والمتاجر' }, { value: 'trends', label: 'الاتجاهات' }, { value: 'tariffs', label: 'الرسوم والاتفاقيات' }]} />
              </div>

              {view === 'summary' && (
                <>
              {analysis.signals.length > 0 && (
                <>
                  <h3>الإشارات الاستخباراتية</h3>
                  <div className="mi-signals">
                    {analysis.signals.map((sig, i) => (
                      <div key={i} className={`card mi-signal ${sig.level}`}>
                        <small>{LEVEL[sig.level]}</small>
                        <strong>{sig.title}</strong>
                        <p>{sig.detail}</p>
                      </div>
                    ))}
                  </div>
                </>
              )}

              <div className="grid cols-4 insight-tiles">
                <div className="card tile"><span className="stat-label">سوق المنتج عالميًا ({s.year})</span><span className="stat-value num">{compact(s.market_value)}</span><span className="stat-meta">دولار</span></div>
                <div className="card tile"><span className="stat-label">{mode === 'import' ? 'واردات عُمان' : 'صادرات عُمان'}</span><span className="stat-value num">{compact(s.oman_value)}</span><span className="stat-meta">دولار{s.oman_top_partner ? ` · أكبر شريك: ${analysis.candidates.find((c) => c.iso === s.oman_top_partner)?.name_ar ?? s.oman_top_partner}` : ''}</span></div>
                <div className="card tile"><span className="stat-label">تركّز شركاء عُمان (HHI)</span><span className="stat-value num">{s.hhi_oman != null ? formatNumber(s.hhi_oman, 0) : '—'}</span><span className="stat-meta"><span className={`chip ${hhi.tone}`}>{hhi.text}</span></span></div>
                <div className="card tile"><span className="stat-label">سعر وحدة عُمان مقابل الوسيط</span><span className="stat-value num">{priceGap != null ? `${priceGap > 0 ? '+' : ''}${formatNumber(priceGap * 100, 0)}%` : '—'}</span><span className="stat-meta">{s.oman_unit_value ? `${formatNumber(s.oman_unit_value, 0)} دولار/طن` : 'بلا كمية'}{mode === 'import' && s.saving_estimate ? ` · وفر مقدَّر ${compact(s.saving_estimate)} دولار` : ''}</span></div>
                <div className="card tile"><span className="stat-label">الميزة النسبية لعُمان (RCA)</span><span className="stat-value num">{analysis.rca != null ? formatNumber(analysis.rca, 2) : '—'}</span><span className="stat-meta">{analysis.rca == null ? '' : analysis.rca >= 1 ? 'أكبر من 1: ميزة ظاهرة' : 'أقل من 1: لا ميزة ظاهرة'}</span></div>
                <div className="card tile"><span className="stat-label">تعقيد المنتج (PCI)</span><span className="stat-value num">{analysis.pci != null ? formatNumber(analysis.pci, 2) : '—'}</span><span className="stat-meta">الأعلى = يحتاج قدرات تصنيع أكثر</span></div>
                <div className="card tile"><span className="stat-label">الرسم الجمركي العُماني (MFN)</span><span className="stat-value num">{omanMfn ? `${formatNumber(omanMfn.tariff, 1)}%` : '—'}</span><span className="stat-meta">{omanMfn ? `${omanMfn.year} · صفر لدول الاتفاقيات` : 'غير متاح'}</span></div>
                {analysis.commodity[0] && (
                  <div className="card tile"><span className="stat-label">{analysis.commodity[0].name_ar} (سعر عالمي)</span><span className="stat-value num">{formatNumber(analysis.commodity[0].value, 1)}</span><span className="stat-meta">{analysis.commodity[0].unit} · {analysis.commodity[0].period}{analysis.commodity[0].change_12m != null ? ` · ${analysis.commodity[0].change_12m > 0 ? '+' : ''}${formatNumber(analysis.commodity[0].change_12m * 100, 0)}% سنويًا` : ''}</span></div>
                )}
              </div>

              <div className="card intel-brief">
                <div className="intel-brief-head">
                  <h3>توصية {brief?.data.source === 'ai' ? 'الذكاء الاصطناعي' : 'التحليل الآلي'}</h3>
                  {brief && <span className={`chip ${brief.data.source === 'ai' ? 'good' : ''}`} title={brief.data.source === 'ai' ? `النموذج: ${brief.data.model}` : 'خدمة الذكاء الاصطناعي غير مفعَّلة؛ هذا تحليل بقواعد ثابتة'}>{brief.data.source === 'ai' ? 'مولَّد بالذكاء الاصطناعي من بيانات النظام' : 'قواعد آلية (بلا ذكاء اصطناعي)'}</span>}
                </div>
                {!brief && <Loading height={120} />}
                {brief && (
                  <>
                    <p>{brief.data.summary}</p>
                    <div className="grid cols-3">
                      <div><h4>التوصيات</h4><ul>{brief.data.recommendations.map((r, i) => <li key={i}>{r}</li>)}</ul></div>
                      <div><h4>المخاطر</h4><ul>{brief.data.risks.map((r, i) => <li key={i}>{r}</li>)}</ul></div>
                      <div><h4>قبل القرار</h4><ul>{brief.data.next_steps.map((r, i) => <li key={i}>{r}</li>)}</ul></div>
                    </div>
                  </>
                )}
                {brief?.ai_enabled && (
                  <form className="intel-ask" onSubmit={(e) => { e.preventDefault(); if (question.trim().length >= 5) ask.mutate(question.trim()); }}>
                    {user ? (
                      <>
                        <input type="text" value={question} onChange={(e) => setQuestion(e.target.value)} placeholder="اسأل المحلل: ما البديل الأفضل للمورد الأكبر؟ هل الرسوم تعيق التصدير للهند؟" aria-label="سؤالك" maxLength={500} />
                        <button className="btn" type="submit" disabled={ask.isPending}>{ask.isPending ? 'جارٍ التحليل…' : 'اسأل'}</button>
                      </>
                    ) : (
                      <span className="muted"><Link to="/login">سجّل الدخول</Link> لتطرح أسئلة حرة على المحلل الذكي.</span>
                    )}
                    {ask.error && <p role="alert" className="muted">{(ask.error as Error).message}</p>}
                    {ask.data && <div className="intel-answer"><strong>الإجابة:</strong> <p style={{ whiteSpace: 'pre-wrap' }}>{ask.data}</p></div>}
                  </form>
                )}
              </div>

                </>
              )}
              {view === 'ranking' && (
                <>
              <div className="card heat-wrap">
                <div className="controls" style={{ justifyContent: 'space-between' }}>
                  <strong>ترتيب {side} حسب التقييم المركّب ({ranked.length})</strong>
                  <button type="button" className="btn" onClick={() => setWeights(null)}>استعادة الأوزان</button>
                </div>
                <div className="intel-weights">
                  {COMPONENTS.map((c) => (
                    <label key={c.key} title={c.hint}>
                      <span>{c.label} <b className="num">{Math.round(w[c.key] * 100)}</b></span>
                      <input type="range" min={0} max={100} value={Math.round(w[c.key] * 100)} onChange={(e) => setWeights({ ...w, [c.key]: Number(e.target.value) / 100 })} aria-label={`وزن ${c.label}`} />
                    </label>
                  ))}
                </div>
                <div className="heat-scroll">
                  <table className="heat-table intel-table" aria-label="ترتيب المرشحين">
                    <thead>
                      <tr>
                        <th>#</th><th>{mode === 'import' ? 'المورد' : 'السوق'}</th><th className="num">الدرجة</th>
                        {COMPONENTS.map((c) => <th key={c.key} title={c.hint}>{c.label}</th>)}
                        <th className="num">القيمة</th><th className="num">النمو السنوي</th><th className="num">سعر الطن</th>{unitLabel && <th className="num" title="من UN Comtrade بالوحدة الطبيعية للمنتج">سعر {unitLabel}</th>}<th className="num">مع عُمان</th><th title={mode === 'import' ? 'الرسم الذي تفرضه عُمان على المنتج من هذه الدولة' : 'الرسم العام (MFN) الذي تفرضه هذه الدولة على المنتج'}>الرسم</th><th>سعر</th>
                      </tr>
                    </thead>
                    <tbody>
                      {ranked.map((c, i) => (
                        <tr key={c.iso}>
                          <td className="plain num">{i + 1}</td>
                          <th scope="row">{c.name_ar}{c.fta && <small title={c.fta}>اتفاقية تجارة حرة</small>}</th>
                          <td style={{ background: scoreColor(c.score) }}><strong className="num">{c.score != null ? formatNumber(c.score, 0) : '—'}</strong></td>
                          {COMPONENTS.map((k) => <td key={k.key} style={{ background: scoreColor(c.components[k.key]) }} className="num">{c.components[k.key] != null ? formatNumber(c.components[k.key]!, 0) : '—'}</td>)}
                          <td className="plain num">{compact(c.value)}<small>{c.share != null ? `${formatNumber(c.share * 100, 1)}%` : ''}</small></td>
                          <td className="plain num" dir="ltr">{c.cagr != null ? `${c.cagr > 0 ? '+' : ''}${formatNumber(c.cagr, 1)}%` : '—'}</td>
                          <td className="plain num">{c.unit_value != null ? formatNumber(c.unit_value, 0) : '—'}<small>{c.price_vs_median != null ? `${c.price_vs_median > 0 ? '+' : ''}${formatNumber(c.price_vs_median * 100, 0)}% عن الوسيط` : 'بلا كمية'}</small></td>
                          {unitLabel && <td className="plain num">{c.item_price != null ? formatNumber(c.item_price, 2) : '—'}<small>{c.item_price != null ? `${formatNumber(c.item_price * OMR_PER_USD, 2)} ر.ع` : ''}</small></td>}
                          <td className="plain num">{c.oman_value > 0 ? compact(c.oman_value) : '—'}</td>
                          <td className="plain num">{c.tariff ? `${formatNumber(c.tariff.tariff, 1)}%` : '—'}{c.tariff && <small>{c.tariff.year}</small>}</td>
                          <td className="plain"><input type="checkbox" checked={priceIsos.includes(c.iso)} onChange={(e) => setSelected(e.target.checked ? [...priceIsos, c.iso] : priceIsos.filter((x) => x !== c.iso))} aria-label={`إظهار ${c.name_ar} في رسم السعر`} /></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <p className="source-note">الدرجات 0–100 (الأخضر الأفضل)؛ الخلية الفارغة بيانات غير متاحة وتُستثنى من التركيب. {analysis.source.note} وقت إعداد التقرير: {formatDateTime(analysis.generated_at)}. المصدر: <a href={analysis.source.url} target="_blank" rel="noreferrer">{analysis.source.name}</a>.</p>
              </div>

                </>
              )}
              {view === 'prices' && (
                <>
              {up && (
                <div className="card heat-wrap">
                  <strong>سعر الوحدة الواحدة ({up.unit ?? 'غير محددة'}) — UN Comtrade {up.year}</strong>
                  <div className="grid cols-3 insight-tiles" style={{ marginTop: 10 }}>
                    <div className="card tile"><span className="stat-label">{mode === 'import' ? 'ما تدفعه عُمان للوحدة' : 'ما تبيع به عُمان الوحدة'}</span><span className="stat-value num">{up.oman_total?.unit_price != null ? formatNumber(up.oman_total.unit_price * OMR_PER_USD, 3) : '—'}<small className="stat-unit">ر.ع/{up.unit ?? 'وحدة'}</small></span><span className="stat-meta">{up.oman_total?.unit_price != null ? `${formatNumber(up.oman_total.unit_price, 2)} دولار${up.oman_total.estimated ? ' · كمية تقديرية' : ''}` : 'لا كمية مصرَّح بها'}</span></div>
                    <div className="card tile"><span className="stat-label">الوسيط العالمي ({mode === 'import' ? 'سعر تصدير الدول' : 'سعر استيراد الدول'})</span><span className="stat-value num">{up.world_median_unit_price != null ? formatNumber(up.world_median_unit_price * OMR_PER_USD, 3) : '—'}<small className="stat-unit">ر.ع</small></span><span className="stat-meta">{up.world_median_unit_price != null ? `${formatNumber(up.world_median_unit_price, 2)} دولار` : ''}</span></div>
                    <div className="card tile"><span className="stat-label">الربع الأرخص عالميًا</span><span className="stat-value num">{up.world_p25_unit_price != null ? formatNumber(up.world_p25_unit_price * OMR_PER_USD, 3) : '—'}<small className="stat-unit">ر.ع</small></span><span className="stat-meta">{up.world_p25_unit_price != null ? `${formatNumber(up.world_p25_unit_price, 2)} دولار` : ''}</span></div>
                  </div>
                  {up.oman_by_partner.length > 0 && (
                    <div className="heat-scroll">
                      <table className="heat-table" aria-label="سعر الوحدة حسب الشريك">
                        <thead><tr><th>{mode === 'import' ? 'المورد إلى عُمان' : 'السوق من عُمان'}</th><th className="num">القيمة</th><th className="num">الكمية</th><th className="num">سعر {up.unit ?? 'الوحدة'} (ر.ع)</th><th className="num">بالدولار</th><th className="num">وزن الوحدة</th></tr></thead>
                        <tbody>
                          {up.oman_by_partner.filter((p) => p.unit_code === up.unit_code).slice(0, 15).map((p) => (
                            <tr key={p.iso}>
                              <th scope="row">{p.name_ar}</th>
                              <td className="plain num">{compact(p.value)}</td>
                              <td className="plain num">{formatNumber(p.qty, 0)}</td>
                              <td className="plain num"><strong>{p.unit_price != null ? formatNumber(p.unit_price * OMR_PER_USD, 3) : '—'}</strong></td>
                              <td className="plain num">{p.unit_price != null ? formatNumber(p.unit_price, 2) : '—'}</td>
                              <td className="plain num">{p.kg_per_unit != null && up.unit !== 'كغم' ? `${formatNumber(p.kg_per_unit, 1)} كغم` : '—'}</td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                  {up.hs6.length > 0 && (
                    <>
                      <strong style={{ display: 'block', marginTop: 14 }}>التفصيل حسب البنود الفرعية (HS6) لعُمان</strong>
                      <div className="heat-scroll">
                        <table className="heat-table" aria-label="البنود الفرعية">
                          <thead><tr><th>البند</th><th>الوصف</th><th className="num">القيمة</th><th className="num">الكمية</th><th className="num">سعر الوحدة (ر.ع)</th></tr></thead>
                          <tbody>
                            {up.hs6.map((h) => (
                              <tr key={h.code}>
                                <td className="plain num" dir="ltr">{h.code}</td>
                                <th scope="row" style={{ minWidth: 260, fontWeight: 400 }}>{h.text}</th>
                                <td className="plain num">{compact(h.value)}</td>
                                <td className="plain num">{h.qty > 0 ? `${formatNumber(h.qty, 0)} ${ComtradeUnit[h.unit_code] ?? ''}` : '—'}</td>
                                <td className="plain num"><strong>{h.unit_price != null ? formatNumber(h.unit_price * OMR_PER_USD, 3) : '—'}</strong></td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </>
                  )}
                  <p className="source-note">UN Comtrade (الواجهة العامة): الكمية بوحدتها الطبيعية كما صرّحت بها عُمان ودول العالم، فسعر الوحدة = القيمة ÷ عدد الوحدات. الكميات التقديرية تُوسَم، والبنود المختلطة (موديلات وأحجام) تعطي متوسطًا لا سعر موديل بعينه.</p>
                </div>
              )}
                  <Marketplaces key={product.id} defaultQuery={product.name_en} customsPerUnitUsd={up?.oman_total?.unit_price ?? null} unit={up?.unit ?? null} />
              <div className="grid cols-2">
                <ChartCard title="سعر الوحدة العالمي عبر السنوات" subtitle="دولار للطن (قيمة ÷ كمية لكل التجارة العالمية)" datasets={[]}>
                  {worldUv[0]?.points.length ? <LineChartView series={worldUv} unit="دولار/طن" height={260} zeroLine={false} /> : <p className="muted">لا كميات كافية.</p>}
                </ChartCard>
                {analysis.commodity.length > 0 ? (
                  <ChartCard title="السعر العالمي الشهري للسلعة" subtitle={`${analysis.commodity.map((c) => c.name_ar).join('، ')} — صندوق النقد الدولي`} datasets={[]}>
                    <LineChartView series={analysis.commodity.map((c, i) => line(c.key, `${c.name_ar} (${c.unit ?? ''})`, `var(--series-${i + 4})`, c.series))} unit={analysis.commodity[0].unit} height={260} zeroLine={false} />
                  </ChartCard>
                ) : (
                  <ChartCard title={`سعر الوحدة حسب ${mode === 'import' ? 'المورد' : 'السوق'}`} subtitle="دولار للطن؛ اختر الدول من عمود «سعر»" datasets={[]}>
                    {uvSeries.length > 0 ? <LineChartView series={uvSeries} unit="دولار/طن" height={260} zeroLine={false} /> : <p className="muted">لا كميات كافية.</p>}
                  </ChartCard>
                )}
              </div>
              {analysis.commodity.length > 0 && (
                <ChartCard title={`سعر الوحدة حسب ${mode === 'import' ? 'المورد' : 'السوق'}`} subtitle="دولار للطن؛ اختر الدول من عمود «سعر» في الجدول" datasets={[]} wide>
                  {uvSeries.length > 0 ? <LineChartView series={uvSeries} unit="دولار/طن" height={300} zeroLine={false} /> : <p className="muted">لا كميات كافية.</p>}
                </ChartCard>
              )}

                </>
              )}
              {view === 'trends' && (
                <>
              <div className="grid cols-2">
                <ChartCard title={mode === 'import' ? 'واردات عُمان من المنتج (10 سنوات)' : 'صادرات عُمان من المنتج (10 سنوات)'} subtitle="دولار، BACI" datasets={[]}>
                  {omanTrend[0]?.points.length ? <LineChartView series={omanTrend} unit="دولار" height={260} /> : <p className="muted">لا تجارة مسجَّلة لعُمان في هذا المنتج.</p>}
                </ChartCard>
                <ChartCard title="حجم التجارة العالمية في المنتج" subtitle="دولار، BACI" datasets={[]}>
                  {worldValue[0]?.points.length ? <LineChartView series={worldValue} unit="دولار" height={260} /> : <p className="muted">غير متاح.</p>}
                </ChartCard>
              </div>
                </>
              )}
              {view === 'tariffs' && (
                <>
              {analysis.tariffs_oman.length > 0 && (
                <div className="card heat-wrap">
                  <strong>الرسوم الجمركية التي تفرضها عُمان على المنتج</strong>
                  <div className="heat-scroll">
                    <table className="heat-table">
                      <thead><tr><th>المعاملة</th><th className="num">الرسم</th><th className="num">السنة</th></tr></thead>
                      <tbody>
                        {analysis.tariffs_oman.map((t) => (
                          <tr key={t.agreement}><th scope="row">{t.agreement.replace('Most Favoured Nation duty rate treatment', 'الدولة الأولى بالرعاية (الرسم العام)').replace('Preferential tariff for', 'تفضيلي:')}</th><td className="plain num">{formatNumber(t.tariff, 2)}%</td><td className="plain num">{t.year}</td></tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                  <p className="source-note">المصدر: WITS (البنك الدولي) عبر OEC؛ آخر سنة منشورة لعُمان، وقد تغيّرت الرسوم بعدها.</p>
                </div>
              )}
                  <div className="card heat-wrap">
                    <strong>اتفاقيات التجارة الحرة النافذة لعُمان بين المرشَّحين</strong>
                    <ul>{ranked.filter((c) => c.fta).map((c) => <li key={c.iso}>{c.name_ar}: {c.fta}</li>)}</ul>
                    {ranked.every((c) => !c.fta) && <p className="muted">لا اتفاقيات تجارة حرة مع المرشَّحين الحاليين.</p>}
                  </div>
                </>
              )}
            </>
          )}
        </section>
      )}
    </>
  );
}

type Tab = 'trade' | 'prices' | 'subs' | 'org' | 'warning';

export default function MarketIntelPage() {
  const [params, setParams] = useSearchParams();
  const tab = (['trade', 'prices', 'subs', 'org', 'warning'] as const).includes(params.get('tab') as Tab) ? (params.get('tab') as Tab) : 'trade';
  return (
    <>
      <PageHead title="ذكاء الأسواق" lead="قرار الشراء والتصدير بالأرقام: موردون وأسواق بتقييم متعدد المعايير وتوصية بالذكاء الاصطناعي، ومقارنة حية لأسعار أي منتج بين متاجر الدول، وأسعار الاشتراكات حول العالم." />
      <div className="controls">
        <Segmented
          label="القسم"
          value={tab}
          onChange={(v) => setParams(v === 'trade' ? {} : { tab: v })}
          options={[{ value: 'trade', label: 'الموردون والأسواق (تجارة)' }, { value: 'prices', label: 'أسعار المتاجر' }, { value: 'subs', label: 'الاشتراكات' }, { value: 'org', label: 'أسعار جهتي' }, { value: 'warning', label: 'الإنذار المبكر' }]}
        />
      </div>
      {tab === 'trade' && <TradeIntel />}
      {tab === 'prices' && <PriceCompare />}
      {tab === 'subs' && <Subscriptions />}
      {tab === 'org' && <OrgPrices />}
      {tab === 'warning' && <EarlyWarningBoard />}
    </>
  );
}
