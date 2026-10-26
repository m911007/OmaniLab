import { CorrectionsLog } from '@/modules/indicator/CorrectionsLog';
import { FreshnessBadge } from '@/core/components/FreshnessBadge';
import { PageHead } from '@/core/components/PageHead';
import { ErrorState, Loading } from '@/core/components/States';
import { formatDateTime, formatNumber, relativeTime } from '@/core/format';
import { useDatasets, useOverview } from '@/core/hooks';

const RUN_LABEL: Record<string, string> = { running: 'جارٍ', unchanged: 'دون تغيير', staged: 'بانتظار الاعتماد', quarantined: 'محجوز', promoted: 'معتمد', failed: 'فشل' };
const RUN_TONE: Record<string, string> = { promoted: 'good', unchanged: 'neutral', running: 'neutral', staged: 'neutral', quarantined: 'warning', failed: 'critical' };
const SEV_LABEL = { error: 'خطأ', warning: 'تحذير', info: 'معلومة' } as const;
const SEV_TONE = { error: 'critical', warning: 'warning', info: 'neutral' } as const;

function every(minutes: number): string {
  if (minutes % 1440 === 0) return minutes === 1440 ? 'يوميًا' : `كل ${minutes / 1440} أيام`;
  if (minutes % 60 === 0) return `كل ${minutes / 60} ساعة`;
  return `كل ${minutes} دقيقة`;
}

export default function StatusPage() {
  const { data: overview, error: oErr, refetch } = useOverview();
  const { data: datasets, isLoading, error } = useDatasets();

  return (
    <>
      <PageHead title="حالة البيانات" lead="كل مجموعة بيانات: مصدرها وإصدارها، ومتى فُحصت آخر مرة، ومتى تغيّرت بياناتنا فعلًا، وموعد الفحص القادم. يجري كل ذلك آليًا دون تدخل بشري." />
      <section className="section" aria-label="سجل التصحيحات">
        <CorrectionsLog />
      </section>

      {oErr && <ErrorState error={oErr as Error} onRetry={() => refetch()} />}

      {overview && (
        <div className="grid cols-4">
          {[
            ['مجموعات البيانات', overview.counts.datasets],
            ['المؤشرات', overview.counts.indicators],
            ['القيم المخزّنة', overview.counts.observations],
            ['الكيانات (دول ومجموعات)', overview.counts.entities],
          ].map(([label, n]) => (
            <div className="card stat" key={label}>
              <span className="stat-label">{label}</span>
              <span className="stat-value num">{formatNumber(Number(n), 0)}</span>
            </div>
          ))}
        </div>
      )}

      <section className="section">
        <h2>مجموعات البيانات</h2>
        <div className="card table-scroll" style={{ marginTop: 12 }}>
          {isLoading ? (
            <Loading height={260} />
          ) : error ? (
            <ErrorState error={error as Error} />
          ) : (
            <table className="wide">
              <thead>
                <tr>
                  <th>المجموعة</th>
                  <th>المصدر والإصدار</th>
                  <th>الحالة وآخر فحص</th>
                  <th>آخر تغيير في البيانات</th>
                  <th>الدورية</th>
                  <th>الفحص القادم</th>
                </tr>
              </thead>
              <tbody>
                {datasets?.map((d) => (
                  <tr key={d.key}>
                    <td>
                      <strong>{d.name_ar}</strong>
                      <div className="muted" style={{ fontSize: 12 }}>{d.key}</div>
                    </td>
                    <td>
                      {d.source.name_ar}
                      <div className="muted" style={{ fontSize: 12 }}>{d.freshness.source_version ?? '—'}</div>
                    </td>
                    <td><FreshnessBadge dataset={d} /></td>
                    <td>
                      {formatDateTime(d.freshness.last_changed_at)}
                    </td>
                    <td>{every(d.freshness.interval_minutes)}</td>
                    <td>{formatDateTime(d.freshness.next_check_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </section>

      {overview?.audit && (
        <section className="section">
          <h2>تدقيق سلامة البيانات</h2>
          <p className="lead muted">يعمل يوميًا على البيانات المعتمدة نفسها: تكرار الكيانات، القيم غير الصالحة، المؤشرات الفارغة والراكدة، ومعدل الاستبعاد. آخر تدقيق {relativeTime(overview.audit.at)}.</p>
          <div className="card" style={{ marginTop: 12 }}>
            {overview.audit.findings.length === 0 ? (
              <span className="chip good">✓ لا مشاكل في سلامة البيانات</span>
            ) : (
              <ul style={{ margin: 0, padding: 0, listStyle: 'none', display: 'grid', gap: 10 }}>
                {overview.audit.findings.map((f) => (
                  <li key={f.check} style={{ display: 'flex', gap: 10, alignItems: 'baseline', flexWrap: 'wrap' }}>
                    <span className={`chip ${SEV_TONE[f.severity]}`}>{SEV_LABEL[f.severity]}</span>
                    <span>
                      {f.message} <strong className="num">({f.count})</strong>
                      {f.examples.length > 0 && <span className="muted"> — {f.examples.slice(0, 3).join(' | ')}</span>}
                    </span>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </section>
      )}

      {overview && (
        <section className="section">
          <h2>آخر التشغيلات</h2>
          <div className="card table-scroll" style={{ marginTop: 12 }}>
            <table>
              <thead>
                <tr>
                  <th>#</th>
                  <th>المجموعة</th>
                  <th>النتيجة</th>
                  <th>السجلات</th>
                  <th>الجديد أو المعدّل</th>
                  <th>مستبعد / مُزال</th>
                  <th>البدء</th>
                </tr>
              </thead>
              <tbody>
                {overview.recent_runs.map((r) => (
                  <tr key={r.id}>
                    <td className="num">{r.id}</td>
                    <td>{r.dataset_name_ar}</td>
                    <td>
                      <span className={`chip ${RUN_TONE[r.status] ?? 'neutral'}`}>{RUN_LABEL[r.status] ?? r.status}</span>
                      {r.error && <div className="muted" style={{ fontSize: 12 }}>{r.error.slice(0, 120)}</div>}
                    </td>
                    <td className="num">{formatNumber(r.records_count, 0)}</td>
                    <td className="num">{formatNumber(r.promoted_count, 0)}</td>
                    <td className="num">{r.rejected_points} / {r.removed_points}</td>
                    <td>{formatDateTime(r.started_at)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      )}
    </>
  );
}
