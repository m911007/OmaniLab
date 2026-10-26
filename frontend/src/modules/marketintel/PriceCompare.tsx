import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, apiSend } from '@/core/api';
import { Loading, ErrorState } from '@/core/components/States';
import { LineChartView } from '@/core/components/LineChartView';
import { useAuth } from '@/core/auth';
import { formatDateTime, formatNumber } from '@/core/format';
import { CountryPicker } from './CountryPicker';
import { bookmarkletCode, parseProductInput } from './productUrl';
import { isPending, useCapabilities, usePrices, type Offer, type PriceResult } from './useMarketIntel';

const QUICK: { group: string; items: string[] }[] = [
  { group: 'تطبيقات وبرامج', items: ['Netflix', 'WhatsApp', 'Procreate', 'Microsoft Office'] },
  { group: 'ألعاب', items: ['FIFA', 'Minecraft', 'Elden Ring', 'Cyberpunk'] },
  { group: 'بقالة وأغذية', items: ['Nutella', 'Coca Cola', 'olive oil', 'Nescafe'] },
  { group: 'إلكترونيات وغيرها (تتطلب مفتاح Google Shopping)', items: ['iPhone 15', 'PlayStation 5', 'MacBook Air', 'Toyota زيت محرك'] },
];

interface History {
  query: string;
  rounds: { at: string; min_omr: number; by_country: Record<string, number> }[];
  insight: { verdict: 'low' | 'typical' | 'high'; label: string; current: number; low: number; high: number; median: number; n: number } | null;
}
interface Watch {
  id: number;
  query: string;
  countries: string;
  target_omr: number | null;
  last_min_omr: number | null;
  first_min_omr: number | null;
  last_best: { title: string; store: string; country: string; omr: number; link: string | null } | null;
  reached_at: string | null;
  last_checked_at: string | null;
}

/** تكلفة الوصول التقديرية إلى عُمان: (السعر + الشحن) × (1 + الرسم الجمركي) × (1 + ضريبة القيمة المضافة). كلها افتراضات قابلة للتعديل. */
export function landedOmr(o: Offer, a: { ship: number; duty: number; vat: number }): number {
  if (o.country === 'OM') return o.omr;
  const cif = o.omr * (1 + a.ship / 100);
  return cif * (1 + a.duty / 100) * (1 + a.vat / 100);
}

function Bookmarklet() {
  const ref = useRef<HTMLAnchorElement>(null);
  useEffect(() => {
    // يُضبط الرابط بعد العرض كي لا يمر javascript: عبر React (يحذّر منه)؛ هو كود يعمل في متصفح المستخدم فقط.
    ref.current?.setAttribute('href', bookmarkletCode(window.location.origin));
  }, []);
  return (
    <details className="card mi-tip">
      <summary><b>قارن من أي متجر بنقرة</b> — مثل إضافات المتصفح (Honey وKeepa) لكن بلا تثبيت</summary>
      <ol>
        <li>اسحب هذا الزر إلى شريط الإشارات المرجعية في متصفحك: <a ref={ref} className="btn" onClick={(e) => e.preventDefault()}>🔎 قارن في المخبر</a></li>
        <li>افتح صفحة أي منتج في أمازون أو نون أو علي إكسبريس أو غيرها، ثم اضغط الإشارة.</li>
        <li>تفتح هنا مقارنة باسم المنتج بين المتاجر والدول، مع السعر الواصل إلى عُمان وتاريخ السعر.</li>
      </ol>
      <p className="muted mi-hint">الإشارة تقرأ عنوان الصفحة المفتوحة في متصفحك فقط وترسله إلى هذه الصفحة؛ لا تجمع بيانات ولا تكشط المتجر. يمكنك أيضًا لصق رابط المنتج مباشرة في خانة البحث.</p>
    </details>
  );
}

/** مقارنة أسعار منتج (أي منتج أو رابطه) في متاجر عدة دول بالريال العُماني، مع السعر الواصل وتاريخ السعر وقائمة المتابعة. */
export function PriceCompare() {
  const { user } = useAuth();
  const caps = useCapabilities();
  const qc = useQueryClient();
  const [params] = useSearchParams();
  const [text, setText] = useState(params.get('q') ?? '');
  const [countries, setCountries] = useState<string[]>(['OM', 'AE', 'SA', 'US']);
  const [submitted, setSubmitted] = useState<{ q: string; countries: string[] } | null>(null);
  const [hidden, setHidden] = useState<string[]>([]);
  const [assume, setAssume] = useState({ ship: 10, duty: 5, vat: 5 });
  const [sortBy, setSortBy] = useState<'omr' | 'landed'>('landed');
  const [target, setTarget] = useState('');
  const parsed = useMemo(() => parseProductInput(text), [text]);
  const enabled = !!user && submitted !== null;
  const q = usePrices(submitted?.q ?? '', submitted?.countries ?? [], enabled);
  const result = q.data && !isPending(q.data) ? (q.data as PriceResult) : null;
  const history = useQuery({
    queryKey: ['mi-price-history', submitted?.q, submitted?.countries.join(','), result?.fetched_at],
    queryFn: ({ signal }) => api<History>('market-intel/price-history', { q: submitted!.q, countries: submitted!.countries.join(',') }, signal),
    enabled: !!user && !!result,
  });
  const watches = useQuery({ queryKey: ['mi-watches'], queryFn: ({ signal }) => api<{ data: Watch[] }>('market-intel/watches', {}, signal), enabled: !!user });
  const addWatch = useMutation({
    mutationFn: () => apiSend<{ data: Watch[] }>('POST', 'market-intel/watches', { q: submitted!.q, countries: submitted!.countries.join(','), target_omr: target ? Number(target) : null }),
    onSuccess: (d) => qc.setQueryData(['mi-watches'], d),
  });
  const delWatch = useMutation({ mutationFn: (id: number) => apiSend<{ data: Watch[] }>('DELETE', `market-intel/watches/${id}`), onSuccess: (d) => qc.setQueryData(['mi-watches'], d) });

  const go = (raw: string, cs = countries) => {
    const t = parseProductInput(raw).query.trim();
    if (t.length >= 2 && cs.length > 0) setSubmitted({ q: t.toLowerCase(), countries: [...cs].sort() });
  };
  // قادم من الإشارة المرجعية أو رابط مشاركة: يبدأ البحث تلقائيًا بعد تسجيل الدخول
  useEffect(() => {
    if (user && params.get('q') && !submitted) go(params.get('q')!);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user]);

  const offers = useMemo(() => {
    if (!result) return [];
    const rows = result.offers.filter((o) => !hidden.includes(o.source)).map((o) => ({ ...o, landed: landedOmr(o, assume) }));
    return rows.sort((a, b) => (sortBy === 'landed' ? a.landed - b.landed : a.omr - b.omr));
  }, [result, hidden, assume, sortBy]);
  const bestLanded = offers[0];
  const omanBest = offers.filter((o) => o.country === 'OM').sort((a, b) => a.omr - b.omr)[0];

  return (
    <section className="section">
      <h2>مقارنة أسعار المتاجر</h2>
      <p className="lead">اكتب اسم أي منتج أو الصق رابطه من أي متجر، فيعرض النظام العروض الحالية في الدول المختارة بالريال العُماني، والسعر الواصل إلى عُمان (افتراض)، وتاريخ السعر وحكمه، ويمكنك متابعته بسعر مستهدف.</p>
      {caps.data && (
        <p className="muted">المصادر: {caps.data.sources.map((s) => `${s.label}${s.enabled ? '' : ' (غير مفعَّل)'}`).join(' · ')}.{!caps.data.shopping && (user?.is_admin ? <> لإضافة متاجر الإلكترونيات والتجزئة (أمازون والمتاجر الصينية…) ضع مفتاح SerpAPI من <Link to="/settings">الإعدادات</Link>.</> : ' متاجر التجزئة العامة تحتاج مفتاحًا يضعه المشرف.')}</p>
      )}
      {!user && <div className="card tone-info"><Link to="/login">سجّل الدخول</Link> لاستخدام مقارنة الأسعار.</div>}

      <form className="card intel-search" onSubmit={(e) => { e.preventDefault(); go(text); }}>
        <label className="intel-field">
          <span>المنتج أو رابطه</span>
          <input type="search" value={text} onChange={(e) => setText(e.target.value)} placeholder="iPhone 15 Pro 256GB أو https://www.amazon.ae/…/dp/B0…" aria-label="منتج لمقارنة سعره" maxLength={600} dir="auto" />
        </label>
        {parsed.url && <p className="muted mi-hint">رابط من <b>{parsed.store}</b>{parsed.id ? ` (المعرّف ${parsed.id})` : ''}: سنبحث عن «<b>{parsed.query || '—'}</b>». {!parsed.query && 'لم نجد اسم المنتج في الرابط؛ اكتب الاسم يدويًا.'}</p>}
        {params.get('from') && <p className="muted mi-hint">قادم من: <span dir="ltr">{params.get('from')}</span></p>}
        {caps.data && <CountryPicker countries={caps.data.countries} value={countries} onChange={setCountries} />}
        <div className="intel-chips">
          <button className="btn" type="submit" disabled={!user || parsed.query.trim().length < 2}>قارن الأسعار</button>
        </div>
        {QUICK.map((g) => (
          <div key={g.group} className="intel-chips">
            <span className="muted">{g.group}:</span>
            {g.items.map((x) => <button key={x} type="button" className="chip" disabled={!user} onClick={() => { setText(x); go(x); }}>{x}</button>)}
          </div>
        ))}
      </form>
      <Bookmarklet />

      {q.error && <ErrorState error={q.error as Error} onRetry={() => void q.refetch()} />}
      {submitted && enabled && !result && !q.error && <div><Loading height={140} /><p className="muted">جارٍ جلب العروض من متاجر {submitted.countries.length} دول…</p></div>}

      {result && (
        result.offers.length === 0 ? (
          <p className="muted">لم نجد عروضًا لهذا البحث في الدول المختارة. جرّب اسمًا أدق أو بالإنجليزية.{result.errors.length > 0 ? ` تعذّر: ${result.errors.join('، ')}.` : ''}</p>
        ) : (
          <>
            <div className="grid cols-4 insight-tiles">
              <div className="card tile"><span className="stat-label">الأرخص واصلًا إلى عُمان (افتراض)</span><span className="stat-value num">{formatNumber(bestLanded?.landed ?? 0, 3)}<small className="stat-unit">ر.ع</small></span><span className="stat-meta">{bestLanded?.store} · {bestLanded?.country_ar}</span></div>
              <div className="card tile"><span className="stat-label">أرخص عرض داخل عُمان</span><span className="stat-value num">{omanBest ? formatNumber(omanBest.omr, 3) : '—'}<small className="stat-unit">ر.ع</small></span><span className="stat-meta">{omanBest ? `${omanBest.store}${bestLanded && omanBest.omr > bestLanded.landed ? ` · الاستيراد أوفر بـ${formatNumber((1 - bestLanded.landed / omanBest.omr) * 100, 0)}%` : ' · الأوفر محليًا'}` : 'لا عروض من عُمان'}</span></div>
              <div className="card tile"><span className="stat-label">الوسيط (كل الدول)</span><span className="stat-value num">{formatNumber(result.summary.median ?? 0, 3)}<small className="stat-unit">ر.ع</small></span><span className="stat-meta">{result.summary.count} عرضًا</span></div>
              <div className={`card tile mi-insight ${history.data?.insight?.verdict ?? ''}`}>
                <span className="stat-label">حكم السعر (مقابل تاريخه)</span>
                <span className="stat-value" style={{ fontSize: 18 }}>{history.data?.insight ? history.data.insight.label : 'يُبنى التاريخ مع كل مقارنة'}</span>
                <span className="stat-meta">{history.data?.insight ? `المدى ${formatNumber(history.data.insight.low, 3)}–${formatNumber(history.data.insight.high, 3)} ر.ع · ${history.data.insight.n} جولة` : `${history.data?.rounds.length ?? 0} جولة حتى الآن (يلزم 4)`}</span>
              </div>
            </div>

            <div className="card mi-watchbox">
              <strong>تابع هذا السعر</strong>
              <form className="intel-ask" onSubmit={(e) => { e.preventDefault(); addWatch.mutate(); }}>
                <label className="intel-field" style={{ maxWidth: 220 }}><span>نبّهني عندما يصل إلى (ر.ع)</span><input type="number" step="0.001" min="0" value={target} onChange={(e) => setTarget(e.target.value)} placeholder="اختياري" /></label>
                <button className="btn" type="submit" disabled={addWatch.isPending}>{addWatch.isSuccess ? 'تمت الإضافة ✓' : 'أضف إلى قائمة المتابعة'}</button>
                {addWatch.error && <span role="alert" className="muted">{(addWatch.error as Error).message}</span>}
              </form>
              <small className="muted">يُفحص يوميًا وتُحفظ لقطة سعر في كل فحص (من المصادر المجانية دائمًا، ومن Google Shopping إن ضُبط مفتاحه).</small>
            </div>

            {history.data && history.data.rounds.length > 1 && (
              <div className="card">
                <strong>تاريخ أدنى سعر بين الدول المختارة</strong>
                <LineChartView series={[{ id: 'min', label: 'أدنى سعر (ر.ع)', color: 'var(--series-1)', points: history.data.rounds.map((r) => [r.at.slice(0, 10), r.min_omr] as [string, number]) }]} unit="ر.ع" height={220} zeroLine={false} />
              </div>
            )}

            <div className="card mi-assume">
              <strong>افتراضات السعر الواصل إلى عُمان</strong> <span className="chip warning">افتراض</span>
              <div className="chain-edit-grid" style={{ marginTop: 8 }}>
                <label>الشحن والتأمين (% من السعر)<input type="number" min="0" max="200" value={assume.ship} onChange={(e) => setAssume({ ...assume, ship: Number(e.target.value) })} /></label>
                <label>الرسم الجمركي (%)<input type="number" min="0" max="100" value={assume.duty} onChange={(e) => setAssume({ ...assume, duty: Number(e.target.value) })} /></label>
                <label>ضريبة القيمة المضافة (%)<input type="number" min="0" max="50" value={assume.vat} onChange={(e) => setAssume({ ...assume, vat: Number(e.target.value) })} /></label>
                <label>الترتيب<select value={sortBy} onChange={(e) => setSortBy(e.target.value as 'omr' | 'landed')}><option value="landed">بالسعر الواصل</option><option value="omr">بسعر المتجر</option></select></label>
              </div>
              <small className="muted">الافتراضي: شحن 10% تقديري، والرسم الجمركي الموحد لدول الخليج 5% لمعظم السلع، وضريبة القيمة المضافة في عُمان 5%. عروض عُمان لا يُضاف إليها شيء. السلع المعفاة أو ذات الرسوم الخاصة (التبغ، المركبات…) تختلف؛ عدّل القيم بحسب المنتج.</small>
            </div>

            <div className="intel-chips" role="group" aria-label="المصادر">
              <span className="muted">المصادر:</span>
              {result.sources.map((src) => (
                <button key={src.key} type="button" className={`chip${hidden.includes(src.key) ? '' : ' good'}`} aria-pressed={!hidden.includes(src.key)} onClick={() => setHidden((h) => (h.includes(src.key) ? h.filter((x) => x !== src.key) : [...h, src.key]))}>{src.label} ({src.count})</button>
              ))}
            </div>

            <div className="card heat-wrap">
              <strong>العروض ({offers.length})</strong>
              <div className="heat-scroll">
                <table className="heat-table intel-table" aria-label="عروض المتاجر">
                  <thead><tr><th>المنتج</th><th>المتجر</th><th>الدولة</th><th className="num">السعر المحلي</th><th className="num">ر.ع</th><th className="num">الواصل إلى عُمان</th><th>التقييم</th><th>الرابط</th></tr></thead>
                  <tbody>
                    {offers.slice(0, 80).map((o, i) => (
                      <tr key={`${o.store}-${o.country}-${i}`} className={o === bestLanded ? 'mi-best' : undefined}>
                        <th scope="row" style={{ minWidth: 220 }}>{o.title}<small>{result.sources.find((x) => x.key === o.source)?.label ?? o.source}</small></th>
                        <td className="plain">{o.store}</td>
                        <td className="plain">{o.country_ar}</td>
                        <td className="plain num" dir="ltr">{formatNumber(o.price, 2)} {o.currency}</td>
                        <td className="plain num">{formatNumber(o.omr, 3)}</td>
                        <td className="plain num"><strong>{formatNumber(o.landed, 3)}</strong>{o.country !== 'OM' && <small>افتراض</small>}</td>
                        <td className="plain num">{o.rating != null ? `${formatNumber(o.rating, 1)}${o.reviews ? ` (${formatNumber(o.reviews, 0)})` : ''}` : '—'}</td>
                        <td className="plain">{o.link ? <a href={o.link} target="_blank" rel="noopener noreferrer nofollow">عرض</a> : '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <p className="source-note">وقت الجلب {formatDateTime(result.fetched_at)} (يُخزَّن ساعة). أسعار Open Prices جماعية وقد تكون قديمة؛ وقد تخلط النتائج بين طرازات متقاربة فتحقق من المواصفات. التحويل بأسعار الصرف الحالية.{result.errors.length > 0 ? ` تعذّر: ${result.errors.join('، ')}.` : ''}</p>
            </div>
          </>
        )
      )}

      {user && watches.data && watches.data.data.length > 0 && (
        <div className="card heat-wrap">
          <strong>قائمة متابعتي ({watches.data.data.length}/20)</strong>
          <div className="heat-scroll">
            <table className="heat-table" aria-label="قائمة المتابعة">
              <thead><tr><th>المنتج</th><th>الدول</th><th className="num">المستهدف</th><th className="num">آخر أدنى سعر</th><th className="num">منذ بدء المتابعة</th><th>أفضل عرض</th><th>آخر فحص</th><th /></tr></thead>
              <tbody>
                {watches.data.data.map((w) => {
                  const change = w.first_min_omr && w.last_min_omr ? (w.last_min_omr / w.first_min_omr - 1) * 100 : null;
                  return (
                    <tr key={w.id}>
                      <th scope="row"><button type="button" className="linkish" onClick={() => { setText(w.query); setCountries(w.countries.split(',')); go(w.query, w.countries.split(',')); }}>{w.query}</button>{w.reached_at && <small className="mi-reached">✓ بلغ السعر المستهدف</small>}</th>
                      <td className="plain">{w.countries.replace(/,/g, '، ')}</td>
                      <td className="plain num">{w.target_omr != null ? formatNumber(w.target_omr, 3) : '—'}</td>
                      <td className="plain num">{w.last_min_omr != null ? formatNumber(w.last_min_omr, 3) : 'بانتظار أول فحص'}</td>
                      <td className="plain num" dir="ltr">{change != null ? `${change > 0 ? '+' : ''}${formatNumber(change, 1)}%` : '—'}</td>
                      <td className="plain">{w.last_best ? (w.last_best.link ? <a href={w.last_best.link} target="_blank" rel="noopener noreferrer nofollow">{w.last_best.store}</a> : w.last_best.store) : '—'}</td>
                      <td className="plain">{w.last_checked_at ? formatDateTime(w.last_checked_at) : '—'}</td>
                      <td className="plain"><button type="button" className="btn" onClick={() => delWatch.mutate(w.id)} aria-label={`إزالة ${w.query}`}>إزالة</button></td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </section>
  );
}
