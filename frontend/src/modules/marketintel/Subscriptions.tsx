import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Loading, ErrorState } from '@/core/components/States';
import { useAuth } from '@/core/auth';
import { formatNumber } from '@/core/format';
import { CountryPicker } from './CountryPicker';
import { isPending, useCapabilities, useSubscriptions, type SubscriptionResult } from './useMarketIntel';

const QUICK = ['Netflix', 'Spotify Premium', 'YouTube Premium', 'Disney+', 'Shahid VIP', 'Amazon Prime', 'Apple Music', 'ChatGPT Plus', 'Microsoft 365 Personal', 'باقة إنترنت منزلي'];
const PERIOD: Record<string, string> = { month: 'شهريًا', year: 'سنويًا', one_time: 'مرة واحدة' };

/** أسعار اشتراك أي خدمة في عدة دول (لا عُمان وحدها)، يستخرجها الذكاء الاصطناعي من الصفحات الرسمية العامة مع رابط كل سعر وتاريخه. */
export function Subscriptions() {
  const { user } = useAuth();
  const caps = useCapabilities();
  const [text, setText] = useState('');
  const [countries, setCountries] = useState<string[]>(['OM', 'AE', 'SA', 'US', 'GB', 'IN', 'EG', 'TR']);
  const [submitted, setSubmitted] = useState<{ service: string; countries: string[] } | null>(null);
  const ready = !!user && caps.data?.ai === true;
  const q = useSubscriptions(submitted?.service ?? '', submitted?.countries ?? [], ready && submitted !== null);
  const result = q.data && !isPending(q.data) ? (q.data as SubscriptionResult) : null;
  const go = (s: string) => {
    const t = s.trim();
    if (t.length >= 2 && countries.length > 0) setSubmitted({ service: t, countries: [...countries].sort() });
  };
  const cheapest = result?.offers.find((o) => o.omr_monthly != null);

  return (
    <section className="section">
      <h2>مقارنة أسعار الاشتراكات حول العالم</h2>
      <p className="lead">اختر خدمة (بث، موسيقى، برامج، إنترنت…) ودولًا من أي مكان، فيبحث الذكاء الاصطناعي في الصفحات الرسمية للمزوّد ويعرض كل باقة بسعرها المحلي وما يعادله شهريًا بالريال العُماني مع رابط المصدر وتاريخه.</p>
      {caps.data && !caps.data.ai && <div className="card tone-info"><strong>غير مفعَّلة بعد.</strong> تتطلب مفتاح Anthropic (أداة البحث في الويب تعمل من جهة الخدمة). {user?.is_admin ? <>ضعه من <Link to="/settings">الإعدادات</Link>.</> : 'يضعه المشرف من صفحة الإعدادات.'}</div>}
      {caps.data?.ai && !user && <div className="card tone-info"><Link to="/login">سجّل الدخول</Link> لاستخدام هذه الأداة (كل بحث يستهلك رصيد الذكاء الاصطناعي).</div>}

      <form className="card intel-search" onSubmit={(e) => { e.preventDefault(); go(text); }}>
        <label className="intel-field">
          <span>الخدمة أو الباقة</span>
          <input type="search" value={text} onChange={(e) => setText(e.target.value)} placeholder="مثال: Netflix Premium" aria-label="خدمة الاشتراك" maxLength={120} />
        </label>
        {caps.data && <CountryPicker countries={caps.data.countries} value={countries} onChange={setCountries} />}
        <div className="intel-chips">
          <button className="btn" type="submit" disabled={!ready || text.trim().length < 2}>قارن الأسعار</button>
          <span className="muted">أمثلة:</span>
          {QUICK.map((x) => <button key={x} type="button" className="chip" onClick={() => { setText(x); go(x); }}>{x}</button>)}
        </div>
      </form>

      {q.error && <ErrorState error={q.error as Error} onRetry={() => void q.refetch()} />}
      {submitted && ready && !result && !q.error && <div><Loading height={140} /><p className="muted">الذكاء الاصطناعي يبحث في صفحات المزوّد الآن؛ قد يستغرق دقيقة…</p></div>}

      {result && (
        <div className="card heat-wrap">
          {result.offers.length === 0 ? <p className="muted">لم يجد البحث أسعارًا موثَّقة بروابط. جرّب اسمًا أدق للخدمة أو دولًا أخرى.</p> : (
            <>
              {cheapest && <p><strong>الأرخص شهريًا:</strong> {cheapest.country_ar} — {cheapest.plan} بنحو <span className="num">{formatNumber(cheapest.omr_monthly!, 3)}</span> ر.ع/شهر.</p>}
              <div className="heat-scroll">
                <table className="heat-table intel-table">
                  <thead><tr><th>الدولة</th><th>الباقة</th><th className="num">السعر المحلي</th><th>الدورة</th><th className="num">ر.ع / شهر</th><th>التاريخ</th><th>المصدر</th></tr></thead>
                  <tbody>
                    {result.offers.map((o, i) => (
                      <tr key={`${o.country}-${o.plan}-${i}`}>
                        <th scope="row">{o.country_ar}</th>
                        <td className="plain">{o.plan}{o.notes ? <small>{o.notes}</small> : null}</td>
                        <td className="plain num" dir="ltr">{formatNumber(o.price, 2)} {o.currency}</td>
                        <td className="plain">{PERIOD[o.period]}</td>
                        <td className="plain num"><strong>{o.omr_monthly != null ? formatNumber(o.omr_monthly, 3) : '—'}</strong></td>
                        <td className="plain num" dir="ltr">{o.as_of ?? '—'}</td>
                        <td className="plain"><a href={o.url} target="_blank" rel="noopener noreferrer nofollow">الصفحة</a></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
          <p className="source-note"><span className="chip">مستخرَج بالذكاء الاصطناعي</span> من صفحات عامة عبر البحث في الويب، لا من واجهة رسمية؛ لذا تحقق من الرابط قبل الاعتماد. لا تُقبَل الصفوف بلا رابط https وسعر رقمي. الأسعار قد تشمل ضريبة أو عروضًا ترويجية بحسب ملاحظة كل صف. التحويل بأسعار الصرف الحالية.{result.note ? ` ${result.note}` : ''}</p>
        </div>
      )}
    </section>
  );
}
