import { Link } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { api } from '@/core/api';
import { Loading, ErrorState } from '@/core/components/States';
import { useAuth } from '@/core/auth';
import { formatDateTime, formatNumber } from '@/core/format';

interface Horizon { expected_pct: number; low_pct: number; high_pct: number; drivers: { input: string; series: string; contribution_pct: number; recent_change_pct: number | null }[] }
interface Row {
  node_id: number;
  node: string;
  chain: string;
  chain_slug: string;
  target_series: string;
  base_period: string;
  issued_at: string;
  fit: { n: number; r2: number };
  horizons: Record<string, Horizon>;
  recommendations: string[];
  org_purchase: { count: number; label: string } | null;
  accuracy: { n: number; mae_pts: number; direction_hit: number; naive_mae_pts: number; in_band: number; backtest: number } | null;
  skill: number | null;
}

const pct = (v: number) => `${v > 0 ? '+' : ''}${formatNumber(v, 1)}%`;
const tone = (v: number) => (v >= 3 ? 'critical' : v >= 1 ? 'warning' : v <= -3 ? 'good' : '');

/** لوحة الإنذار المبكر: ما المتوقع لأسعار المنتجات خلال 1 و3 و6 أشهر بسبب ما حدث فعلًا في مدخلاتها، ولماذا، وماذا نفعل. */
export function EarlyWarningBoard() {
  const { user } = useAuth();
  const q = useQuery({ queryKey: ['early-warning'], queryFn: ({ signal }) => api<{ data: Row[]; method_note: string }>('intel/early-warning', {}, signal), enabled: !!user });
  if (!user) return <section className="section"><div className="card tone-info"><Link to="/login">سجّل الدخول</Link> لعرض الإنذار المبكر.</div></section>;
  if (q.isLoading) return <Loading height={200} />;
  if (q.error) return <ErrorState error={q.error as Error} onRetry={() => void q.refetch()} />;
  const rows = q.data!.data;
  const mine = rows.filter((r) => r.org_purchase);

  return (
    <section className="section">
      <h2>الإنذار المبكر</h2>
      <p className="lead">المدخلات في أعلى السلسلة تتحرك قبل المنتج النهائي. هذه اللوحة تحسب أثر ما حدث فعلًا في أسعار المدخلات ولم ينعكس بعد على سعر كل منتج، مع المدخل المسبّب ونطاق الثقة وما يُنصح به.</p>
      {mine.length > 0 && (
        <div className="mi-signals">
          {mine.map((r) => {
            const h = r.horizons['3'] ?? r.horizons['1'];
            const d = h?.drivers.find((x) => x.recent_change_pct != null);
            return h && (
              <div key={r.node_id} className={`card mi-signal ${h.expected_pct >= 1 ? 'risk' : h.expected_pct <= -1 ? 'opportunity' : 'info'}`}>
                <small>يخص مشتريات جهتكم: {r.org_purchase!.label}</small>
                <strong>{r.node}: {pct(h.expected_pct)} متوقعة خلال 3 أشهر</strong>
                <p>{d && d.recent_change_pct != null ? `${d.input} تغيّر ${pct(d.recent_change_pct)} خلال آخر 3 أشهر، والأثر المتوقع على ${r.node} الذي تشترونه ${pct(h.low_pct)} إلى ${pct(h.high_pct)} خلال الأشهر الثلاثة القادمة.` : ''} {r.recommendations[0] ?? ''}</p>
              </div>
            );
          })}
        </div>
      )}
      {rows.length === 0 ? (
        <p className="muted">لا توقعات بعد؛ تصدر أسبوعيًا عندما تتوفر لكل عقدة ومدخلاتها 48 شهرًا من البيانات.</p>
      ) : (
        <div className="card heat-wrap">
          <div className="heat-scroll">
            <table className="heat-table intel-table" aria-label="لوحة الإنذار المبكر">
              <thead><tr><th>المنتج / المرحلة</th><th className="num">خلال شهر</th><th className="num">خلال 3 أشهر</th><th className="num">خلال 6 أشهر</th><th>المدخل المسبّب</th><th>التوصية</th><th>الدقة السابقة</th></tr></thead>
              <tbody>
                {rows.map((r) => (
                  <tr key={r.node_id}>
                    <th scope="row"><Link to={`/market-intel/chains/${r.chain_slug}`}>{r.node}</Link><small>{r.chain} · {r.target_series.split('|')[0]} · حتى {r.base_period}</small>{r.org_purchase && <small className="mi-reached">تشترونه</small>}</th>
                    {['1', '3', '6'].map((k) => {
                      const h = r.horizons[k];
                      return <td key={k} className={`plain num ew-${tone(h?.expected_pct ?? 0)}`}>{h ? <><b>{pct(h.expected_pct)}</b><small dir="ltr">{pct(h.low_pct)} … {pct(h.high_pct)}</small></> : '—'}</td>;
                    })}
                    <td className="plain">{(r.horizons['3']?.drivers ?? []).slice(0, 2).map((d) => <div key={d.input}>{d.input}: <span className="num">{pct(d.contribution_pct)}</span>{d.recent_change_pct != null && <small>تغيّره آخر 3 أشهر {pct(d.recent_change_pct)}</small>}</div>)}</td>
                    <td className="plain">{r.recommendations.length ? r.recommendations.map((x) => <div key={x}>{x}</div>) : <span className="muted">لا إجراء عاجل</span>}</td>
                    <td className="plain">
                      {r.accuracy ? (
                        <>
                          <span className={`chip ${r.skill != null && r.skill > 0.05 ? 'good' : r.skill != null && r.skill <= 0 ? 'critical' : 'warning'}`}>{r.skill != null && r.skill > 0 ? `أفضل من «لا تغيير» بـ${formatNumber(r.skill * 100, 0)}%` : 'لا يتفوق على «لا تغيير»'}</span>
                          <small>خطأ متوسط {formatNumber(r.accuracy.mae_pts, 1)} نقطة (الساذج {formatNumber(r.accuracy.naive_mae_pts, 1)}) · الاتجاه صحيح {formatNumber(r.accuracy.direction_hit * 100, 0)}% · داخل النطاق {formatNumber(r.accuracy.in_band * 100, 0)}% · {r.accuracy.n} توقعًا ({r.accuracy.backtest} اختبار رجعي)</small>
                        </>
                      ) : <span className="muted">لم يحن تقييم توقع بعد</span>}
                      <small>R² {formatNumber(r.fit.r2, 2)} · {r.fit.n} شهرًا</small>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="source-note">{q.data!.method_note} أُصدرت {rows[0] ? formatDateTime(rows[0].issued_at) : ''}. المصادر شهرية (مؤشرات أسعار المنتجين، قيم جمركية، أسعار سلع) فالتوقع يُحدَّث أسبوعيًا لا لحظيًا. الدقة لأفق 3 أشهر من اختبار رجعي متدحرج (24 شهرًا، كل توقع بالبيانات المتاحة وقته فقط) ثم من التوقعات الحية عند حلولها، مقارنةً بالافتراض الساذج «لا تغيير»؛ وحين لا يتفوق النموذج لا نوصي بإجراء شراء بناءً عليه.</p>
        </div>
      )}
    </section>
  );
}
