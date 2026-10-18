import { useQueries } from '@tanstack/react-query';
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { Segmented } from '@/core/components/Segmented';
import { api, type LatestItem, type LatestResponse } from '@/core/api';
import { formatNumber, formatPeriod, relativeTime } from '@/core/format';

type Row = { indicator: string; entity: 'WLD' | 'USA'; label?: string };
interface Group {
  id: string;
  title: string;
  rows: Row[];
}

const W = (indicator: string, label?: string): Row => ({ indicator, entity: 'WLD', label });
const U = (indicator: string, label?: string): Row => ({ indicator, entity: 'USA', label });

const GROUPS: Group[] = [
  { id: 'energy', title: 'النفط والغاز والوقود', rows: [W('eia.brent_d'), W('eia.wti_d'), W('imf.pcps.poildub', 'خام دبي (شهري)'), W('eia.henryhub_d'), W('imf.pcps.pngaseu'), W('imf.pcps.pngasjp'), W('eia.diesel_ny_d'), W('eia.gasoline_ny_d'), W('eia.jet_fuel_d'), W('eia.propane_d'), W('imf.pcps.pcoalau')] },
  { id: 'grains', title: 'الحبوب والزيوت النباتية', rows: [W('imf.pcps.pwheamt'), W('imf.pcps.pmaizmt'), W('imf.pcps.pricenpq'), W('imf.pcps.pbarl'), W('imf.pcps.psoyb'), W('imf.pcps.psoil'), W('imf.pcps.ppoil'), W('imf.pcps.psuno'), W('imf.pcps.proil'), W('imf.pcps.polvoil')] },
  { id: 'food', title: 'الغذاء: السكر والبن واللحوم والفاكهة', rows: [W('imf.pcps.psugaisa'), W('imf.pcps.pcoffotm'), W('imf.pcps.pcoffrob'), W('imf.pcps.pcoco'), W('imf.pcps.ptea'), W('imf.pcps.pbeef'), W('imf.pcps.plamb'), W('imf.pcps.ppoult'), W('imf.pcps.ppork'), W('imf.pcps.pbansop'), W('imf.pcps.porang'), W('imf.pcps.pgnuts')] },
  { id: 'metals', title: 'المعادن', rows: [W('imf.pcps.pgold'), W('imf.pcps.psilver'), W('imf.pcps.pcopp'), W('imf.pcps.palum'), W('imf.pcps.pzinc'), W('imf.pcps.pnick'), W('imf.pcps.ptin'), W('imf.pcps.plead'), W('imf.pcps.piorecr'), W('imf.pcps.puran')] },
  { id: 'fert', title: 'الأسمدة والمواد الزراعية', rows: [W('imf.pcps.purea'), W('imf.pcps.pdap'), W('imf.pcps.ppotash'), W('imf.pcps.pcottind'), W('imf.pcps.prubb')] },
  { id: 'fx', title: 'العملات مقابل الدولار', rows: [U('fx.usd_eur'), U('fx.usd_gbp'), U('fx.usd_jpy'), U('fx.usd_cny'), U('fx.usd_inr'), U('fx.usd_chf'), U('fx.usd_krw')] },
  { id: 'us', title: 'السوق والفائدة الأمريكية', rows: [U('us.sp500'), U('us.djia'), U('us.vix'), U('us.yield_2y'), U('us.yield_10y'), U('us.yield_30y'), U('us.effr'), U('us.sofr')] },
];

const FREQ: Record<string, string> = { D: 'يومي', M: 'شهري', Q: 'فصلي', A: 'سنوي' };

/** الواجهة تقبل حتى 40 مؤشرًا للطلب الواحد: تُقسَّم القائمة دفعات من 30. */
function useLatestBatch(entity: 'WLD' | 'USA', keys: string[]) {
  const chunks = useMemo(() => Array.from({ length: Math.ceil(keys.length / 30) }, (_, i) => keys.slice(i * 30, i * 30 + 30)), [keys]);
  const results = useQueries({
    queries: chunks.map((chunk) => ({
      queryKey: ['prices-now', entity, chunk.join(',')],
      queryFn: ({ signal }: { signal: AbortSignal }) => api<LatestResponse>(`entities/${entity}/latest`, { indicators: chunk.join(',') }, signal),
      refetchInterval: 60_000,
      staleTime: 30_000,
    })),
  });
  return {
    items: results.flatMap((r) => r.data?.items ?? []),
    updatedAt: Math.max(0, ...results.map((r) => r.dataUpdatedAt ?? 0)),
  };
}

/** الأسعار الآن: آخر سعر منشور لكل سلعة، يُحدَّث العرض كل دقيقة. التردد (يومي/شهري) والمصدر ظاهران لكل صف: لا أسعار لحظية على مستوى الصفقة. */
export default function PricesNowPage() {
  const wld = useLatestBatch('WLD', useMemo(() => [...new Set(GROUPS.flatMap((g) => g.rows.filter((r) => r.entity === 'WLD').map((r) => r.indicator)))], []));
  const usa = useLatestBatch('USA', useMemo(() => [...new Set(GROUPS.flatMap((g) => g.rows.filter((r) => r.entity === 'USA').map((r) => r.indicator)))], []));
  const [group, setGroup] = useState<string>('all');
  const [now, setNow] = useState(Date.now());
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 30_000);
    return () => clearInterval(t);
  }, []);

  const items = useMemo(() => {
    const map = new Map<string, LatestItem>();
    for (const i of [...wld.items, ...usa.items]) map.set(i.indicator.key, i);
    return map;
  }, [wld.items, usa.items]);
  const updated = Math.max(wld.updatedAt, usa.updatedAt);
  const shown = GROUPS.filter((g) => group === 'all' || g.id === group);

  return (
    <>
      <PageHead
        title="الأسعار الآن"
        lead="آخر الأسعار المنشورة للنفط والغاز والديزل والبنزين والحبوب والغذاء والمعادن والأسمدة والعملات. يتجدد العرض كل دقيقة، وتتجدد البيانات من مصادرها الرسمية تلقائيًا (النفط والوقود والعملات والأسهم يوميًا، وبقية السلع شهريًا). هي أسعار رسمية منشورة وليست أسعار صفقات لحظية."
      />
      <div className="card-head no-print" style={{ flexWrap: 'wrap', gap: 10, marginBottom: 12 }}>
        <Segmented label="المجموعة" value={group} onChange={setGroup} options={[{ value: 'all', label: 'الكل' }, ...GROUPS.map((g) => ({ value: g.id, label: g.title.split(' ')[0]! }))]} />
        <span className="muted">آخر تحديث للعرض: {updated ? relativeTime(new Date(updated).toISOString(), now) : '…'}</span>
      </div>

      {shown.map((g) => (
        <section key={g.id} className="section card" aria-labelledby={`pn-${g.id}`}>
          <h2 id={`pn-${g.id}`}>{g.title}</h2>
          <div className="table-scroll">
            <table className="wide prices-table">
              <thead>
                <tr><th>السلعة</th><th>آخر سعر</th><th>التغيّر</th><th>التاريخ</th><th>التردد</th><th>المصدر</th></tr>
              </thead>
              <tbody>
                {g.rows.map((r) => {
                  const it = items.get(r.indicator);
                  if (!it) {
                    return (
                      <tr key={r.indicator} className="muted"><td>{r.label ?? r.indicator}</td><td colSpan={5}>لا بيانات بعد</td></tr>
                    );
                  }
                  const change = it.previous ? ((it.value - it.previous.value) / Math.abs(it.previous.value)) * 100 : null;
                  return (
                    <tr key={r.indicator}>
                      <td>
                        <Link to={`/commodities`}>{r.label ?? it.indicator.name_ar}</Link>
                      </td>
                      <td className="num"><strong>{formatNumber(it.value, it.value < 10 ? 3 : it.value < 1000 ? 2 : 0)}</strong> <span className="muted">{it.indicator.unit}</span></td>
                      <td className={`num ${change == null ? '' : change >= 0 ? 'up' : 'down'}`}>{change == null ? '—' : `${change >= 0 ? '▲' : '▼'} ${formatNumber(Math.abs(change), 2)}%`}</td>
                      <td className="num">{formatPeriod(it.period)}</td>
                      <td>{FREQ[it.indicator.frequency] ?? it.indicator.frequency}</td>
                      <td className="muted">{it.dataset.source.name_ar}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </section>
      ))}
      <p className="source-note">التغيّر مقارنة بالقراءة السابقة لنفس السلسلة (اليوم السابق للسلاسل اليومية، والشهر السابق للشهرية). تفاصيل المصادر وإمكانية المقارنة بين مصادرها في صفحة «النفط والسلع».</p>
    </>
  );
}
