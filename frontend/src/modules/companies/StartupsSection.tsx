import { useEffect, useState } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { RankedBars } from '@/core/components/RankedBars';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { SourceNote } from '@/core/components/SourceNote';
import { formatNumber } from '@/core/format';
import { useStartups, useStartupStats, type CountItem } from '@/core/hooks';
import { StatLabel } from '@/core/components/IndicatorIcon';

const bars = (items: CountItem[], limit = 12) => items.slice(0, limit).map((i, n) => ({ key: i.label, rank: n + 1, label: i.label, value: i.count, color: 'var(--series-1)' }));

/** الشركات الناشئة العُمانية من منصة عُمان للشركات الناشئة: توزيعها بالمرحلة والقطاع وحجم الفريق وسنة التأسيس، وقائمة قابلة للتصفية. */
export function StartupsSection() {
  const stats = useStartupStats();
  const [text, setText] = useState('');
  const [q, setQ] = useState('');
  const [sector, setSector] = useState('');
  const [stage, setStage] = useState('');
  const [page, setPage] = useState(1);
  useEffect(() => {
    const t = setTimeout(() => {
      setQ(text.trim());
      setPage(1);
    }, 300);
    return () => clearTimeout(t);
  }, [text]);
  const list = useStartups({ q: q || undefined, sector: sector || undefined, stage: stage || undefined, page, per_page: 12 });
  const pages = list.data ? Math.max(1, Math.ceil(list.data.total / list.data.per_page)) : 1;
  const s = stats.data;
  const datasets = s?.dataset ? [s.dataset] : [];

  return (
    <section className="section" id="startups">
      <h2>الشركات الناشئة</h2>
      <p className="muted" style={{ margin: '4px 0 12px' }}>
        المشاريع والشركات الناشئة المسجّلة في منصة «عِش» (مركز عُمان للشركات الناشئة): مرحلة كل شركة وقطاعها وحجم فريقها وسنة تأسيسها وما تبحث عنه. التسجيل في المنصة طوعي ولا يعني حصر كل الشركات الناشئة في عُمان. القائمة الكاملة خلف تسجيل الدخول في المنصة؛ سحبها مالك المنصة بحسابه الخاص إلى لقطة تُحدَّث يدويًا، أما الصفحة العلنية فتعرض 9 شركات فقط.
      </p>
      {s && (
        <div className="grid cols-4">
          <div className="card stat"><StatLabel>تعلن المنصة عن</StatLabel><span className="stat-value num">{s.platform_count != null ? formatNumber(s.platform_count, 0) : '—'}</span><span className="muted">شركة ناشئة · {s.platform_links != null ? `${formatNumber(s.platform_links, 0)} رابطًا في المنظومة` : ''}</span></div>
          <div className="card stat"><StatLabel>المتاح هنا</StatLabel><span className="stat-value num">{formatNumber(s.total, 0)}</span><span className="muted">من لقطة القائمة الكاملة</span></div>
          <div className="card stat"><StatLabel>أكثر ما تبحث عنه</StatLabel><span className="stat-value" style={{ fontSize: 20 }}>{s.seeking[0]?.label ?? '—'}</span><span className="muted">{s.seeking[0] ? `${formatNumber(s.seeking[0].count, 0)} شركة` : ''}</span></div>
        </div>
      )}

      <div className="grid cols-2" style={{ marginTop: 12 }}>
        <ChartCard title="الشركات الناشئة حسب القطاع" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars(s?.by_sector ?? [])} />}
        </ChartCard>
        <ChartCard title="حسب مرحلة النمو" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars(s?.by_stage ?? [])} />}
        </ChartCard>
        <ChartCard title="حالة النشاط" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars([{ label: 'نشطة (أو غير موسومة)', count: Math.max(0, (s?.total ?? 0) - (s?.by_status.find((x) => x.label === 'غير نشط')?.count ?? 0)) }, ...(s?.by_status ?? [])])} />}
        </ChartCard>
        <ChartCard title="الشكل القانوني" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars(s?.by_legal_type ?? [])} />}
        </ChartCard>
        <ChartCard title="حجم الفريق (عدد الموظفين)" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars(s?.by_employees ?? [])} />}
        </ChartCard>
        <ChartCard title="ما تبحث عنه الشركات الناشئة" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars(s?.seeking ?? [])} />}
        </ChartCard>
        <ChartCard title="حجم التمويل المطلوب (دولار)" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars(s?.by_funding ?? [])} />}
        </ChartCard>
        <ChartCard title="القطاعات الفرعية الأكثر انتشارًا" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars(s?.by_subsector ?? [], 15)} />}
        </ChartCard>
        <ChartCard title="سنة التأسيس" datasets={datasets}>
          {stats.isLoading ? <Loading height={240} /> : <RankedBars items={bars([...(s?.by_founded_year ?? [])].reverse(), 15)} />}
        </ChartCard>
      </div>

      <div className="card" style={{ marginTop: 12 }}>
        <div className="controls">
          <label className="field">
            بحث
            <input type="search" value={text} onChange={(e) => setText(e.target.value)} placeholder="اسم الشركة الناشئة" style={{ minWidth: 220 }} aria-label="بحث في الشركات الناشئة" />
          </label>
          <label className="field">
            القطاع
            <select value={sector} onChange={(e) => { setSector(e.target.value); setPage(1); }}>
              <option value="">الكل</option>
              {s?.by_sector.map((x) => <option key={x.label} value={x.label}>{x.label} ({x.count})</option>)}
            </select>
          </label>
          <label className="field">
            المرحلة
            <select value={stage} onChange={(e) => { setStage(e.target.value); setPage(1); }}>
              <option value="">الكل</option>
              {s?.by_stage.map((x) => <option key={x.label} value={x.label}>{x.label} ({x.count})</option>)}
            </select>
          </label>
        </div>
        {list.isLoading ? <Loading height={260} /> : list.error ? <ErrorState error={list.error as Error} /> : list.data && list.data.data.length === 0 ? <Empty>لا شركات ناشئة تطابق هذا الاختيار.</Empty> : (
          <div className="table-scroll">
            <table className="wide" aria-label="الشركات الناشئة">
              <thead>
                <tr><th>الشركة</th><th>المرحلة</th><th>القطاع</th><th>الفريق</th><th>التأسيس</th><th>تبحث عن</th><th>التمويل المطلوب</th></tr>
              </thead>
              <tbody>
                {list.data?.data.map((c) => (
                  <tr key={c.code}>
                    <td>
                      {c.url ? <a href={c.url} target="_blank" rel="noreferrer noopener" style={{ fontWeight: 700 }}>{c.name}</a> : c.name}
                      {c.blurb && <div className="muted" style={{ fontSize: 12, maxWidth: 360 }}>{c.blurb}</div>}
                    </td>
                    <td>{c.stage ?? '—'}{c.activity_status && <div className="muted" style={{ fontSize: 12 }}>{c.activity_status}</div>}</td>
                    <td>{(c.sector ?? []).join('، ') || '—'}{c.subsector && c.subsector.length > 0 && <div className="muted" style={{ fontSize: 12 }}>{c.subsector.join('، ')}</div>}</td>
                    <td className="num">{c.employees ?? '—'}</td>
                    <td className="num">{c.founded_year ?? '—'}</td>
                    <td style={{ fontSize: 12 }}>{(c.seeking ?? []).join('، ') || '—'}</td>
                    <td className="num" style={{ fontSize: 12 }}>{c.funding_sought ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {list.data && (
          <div className="controls" style={{ marginTop: 12, justifyContent: 'space-between' }}>
            <span className="muted"><span className="num">{formatNumber(list.data.total, 0)}</span> شركة ناشئة · صفحة <span className="num">{list.data.page}</span> من <span className="num">{pages}</span></span>
            <span style={{ display: 'inline-flex', gap: 8 }}>
              <button className="btn" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>السابق</button>
              <button className="btn" disabled={page >= pages} onClick={() => setPage((p) => p + 1)}>التالي</button>
            </span>
          </div>
        )}
        {list.data?.dataset && <SourceNote datasets={[list.data.dataset]} note="المصدر: منصة عُمان للشركات الناشئة (omanstartuphub.om). كل اسم يقود إلى صفحة الشركة الأصلية؛ تسجيل الشركات طوعي." />}
      </div>
    </section>
  );
}
