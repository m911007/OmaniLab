import { useState } from 'react';
import { Link } from 'react-router-dom';
import { Loading, ErrorState } from '@/core/components/States';
import { useAuth } from '@/core/auth';
import { formatDateTime, formatNumber, OMR_PER_USD } from '@/core/format';
import { isPending, useCapabilities, useMarketplaces, type MarketplaceResult } from './useMarketIntel';

/**
 * سعر المنتج في المتاجر الإلكترونية (أمازون، المتاجر الصينية، إيباي، وولمارت، المتاجر الخليجية) مقارنةً بسعر الوحدة الجمركي:
 * الفرق بين ما يُستورد به المنتج وما يُباع به للمستهلك يكشف هامش التجزئة وفرص الاستيراد المباشر.
 */
export function Marketplaces({ defaultQuery, customsPerUnitUsd, unit }: { defaultQuery: string; customsPerUnitUsd: number | null; unit: string | null }) {
  const { user } = useAuth();
  const caps = useCapabilities();
  const [text, setText] = useState(defaultQuery);
  const [q, setQ] = useState('');
  const [group, setGroup] = useState<string>('all');
  const ready = !!user && caps.data?.shopping === true;
  const res = useMarketplaces(q, ready);
  const data = res.data && !isPending(res.data) ? (res.data as MarketplaceResult) : null;
  const customsOmr = customsPerUnitUsd != null && unit === 'قطعة' ? customsPerUnitUsd * OMR_PER_USD : null;
  const offers = data ? data.offers.filter((o) => group === 'all' || o.group === group) : [];

  return (
    <div className="card heat-wrap">
      <strong>المنتج في المتاجر الإلكترونية: أمازون والمتاجر الصينية وغيرها</strong>
      <p className="muted mi-hint">اكتب اسم موديل محدد للحصول على مقارنة دقيقة (مثلًا «Toyota Corolla 2024» أو «iPhone 15 128GB»)؛ الاسم العام للفئة يعطي نتائج متباينة.</p>
      {caps.data && !caps.data.shopping && (
        <div className="tone-info card">تتطلب مفتاح SerpAPI (خدمة مرخّصة لنتائج أمازون وGoogle Shopping وإيباي ووولمارت). {user?.is_admin ? <>ضعه من <Link to="/settings">الإعدادات</Link>.</> : 'يضعه المشرف من صفحة الإعدادات.'} لا نكشط مواقع المتاجر مباشرة لأن شروطها تمنع ذلك.</div>
      )}
      {caps.data?.shopping && !user && <div className="tone-info card"><Link to="/login">سجّل الدخول</Link> لتشغيل المقارنة (تستهلك رصيدًا مدفوعًا).</div>}
      <form className="intel-ask" onSubmit={(e) => { e.preventDefault(); if (text.trim().length >= 2) setQ(text.trim().toLowerCase()); }}>
        <input type="search" value={text} onChange={(e) => setText(e.target.value)} aria-label="اسم المنتج في المتاجر" maxLength={120} dir="auto" />
        <button className="btn" type="submit" disabled={!ready || text.trim().length < 2}>قارن في المتاجر</button>
      </form>
      {res.error && <ErrorState error={res.error as Error} onRetry={() => void res.refetch()} />}
      {q && ready && !data && !res.error && <div><Loading height={120} /><p className="muted">نجمع العروض من أمازون (4 دول) وإيباي ووولمارت وGoogle Shopping…</p></div>}
      {data && (
        <>
          {data.groups.length === 0 ? <p className="muted">لا عروض لهذا الاسم.</p> : (
            <>
              <div className="heat-scroll">
                <table className="heat-table" aria-label="المتاجر حسب المجموعة">
                  <thead><tr><th>المجموعة</th><th className="num">عروض</th><th className="num">الأدنى ر.ع</th><th className="num">الوسيط ر.ع</th><th className="num">الأعلى ر.ع</th>{customsOmr != null && <th className="num">الوسيط ÷ السعر الجمركي</th>}<th>المنصات</th></tr></thead>
                  <tbody>
                    {data.groups.map((g) => (
                      <tr key={g.group}>
                        <th scope="row">{g.label}</th>
                        <td className="plain num">{g.count}</td>
                        <td className="plain num">{formatNumber(g.min, 3)}</td>
                        <td className="plain num"><strong>{formatNumber(g.median, 3)}</strong></td>
                        <td className="plain num">{formatNumber(g.max, 3)}</td>
                        {customsOmr != null && <td className="plain num">{formatNumber(g.median / customsOmr, 2)}×</td>}
                        <td className="plain"><small>{g.platforms.slice(0, 5).join('، ')}</small></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              {customsOmr != null && <p className="muted mi-hint">السعر الجمركي لوحدة الاستيراد إلى عُمان ≈ {formatNumber(customsOmr, 3)} ر.ع للقطعة (قبل الشحن الداخلي والرسوم والهوامش)؛ النسبة أعلاه تقيس فرق التجزئة لكنها تقارن متوسط فئة بموديلات محددة، فاقرأها مؤشرًا لا حسابًا دقيقًا.</p>}
              <div className="intel-chips" role="group" aria-label="تصفية المجموعة">
                <button type="button" className={`chip${group === 'all' ? ' good' : ''}`} onClick={() => setGroup('all')}>الكل ({data.offers.length})</button>
                {data.groups.map((g) => <button key={g.group} type="button" className={`chip${group === g.group ? ' good' : ''}`} onClick={() => setGroup(g.group)}>{g.label} ({g.count})</button>)}
              </div>
              <div className="heat-scroll">
                <table className="heat-table intel-table" aria-label="عروض المتاجر">
                  <thead><tr><th>المنتج</th><th>المنصة</th><th className="num">السعر</th><th className="num">ر.ع</th><th>التقييم</th><th>الرابط</th></tr></thead>
                  <tbody>
                    {offers.slice(0, 60).map((o, i) => (
                      <tr key={`${o.platform}-${i}`}>
                        <th scope="row" style={{ minWidth: 240 }}>{o.title}</th>
                        <td className="plain">{o.platform}</td>
                        <td className="plain num" dir="ltr">{formatNumber(o.price, 2)} {o.currency}</td>
                        <td className="plain num"><strong>{formatNumber(o.omr, 3)}</strong></td>
                        <td className="plain num">{o.rating != null ? `${formatNumber(o.rating, 1)}${o.reviews ? ` (${formatNumber(o.reviews, 0)})` : ''}` : '—'}</td>
                        <td className="plain">{o.link ? <a href={o.link} target="_blank" rel="noopener noreferrer nofollow">فتح</a> : '—'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}
          <p className="source-note">المصدر: SerpAPI (Amazon وeBay وWalmart وGoogle Shopping لأمريكا والإمارات)، وقت الجلب {formatDateTime(data.fetched_at)}، تُخزَّن ساعة. المتاجر الصينية تُعرَف من اسم البائع في Google Shopping. الأسعار قبل الشحن والجمارك غالبًا.{data.errors.length ? ` تعذّر: ${data.errors.join('، ')}.` : ''}</p>
        </>
      )}
    </div>
  );
}
