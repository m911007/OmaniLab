import { useMemo } from 'react';
import { Link } from 'react-router-dom';
import { Loading } from '@/core/components/States';
import { compact, entityName } from '@/core/format';
import { useRanking } from '@/core/hooks';

/** الميزان التجاري مع كل شريك (صادرات − واردات): أكبر الفوائض وأكبر العجوزات، من بيانات منافذ لسنة كاملة. */
export function PartnerBalance({ year }: { year: string }) {
  const exp = useRanking({ indicator: 'manafeth.EXPORTS_TO', period: year, limit: 300, type: 'country' });
  const imp = useRanking({ indicator: 'manafeth.IMPORTS_FROM', period: year, limit: 300, type: 'country' });

  const view = useMemo(() => {
    if (!exp.data || !imp.data) return null;
    const rows = new Map<string, { entity: (typeof exp.data.items)[number]['entity']; exp: number; imp: number }>();
    for (const i of exp.data.items) rows.set(i.entity.code, { entity: i.entity, exp: i.value, imp: 0 });
    for (const i of imp.data.items) {
      const r = rows.get(i.entity.code) ?? { entity: i.entity, exp: 0, imp: 0 };
      r.imp = i.value;
      rows.set(i.entity.code, r);
    }
    const list = [...rows.values()].map((r) => ({ ...r, balance: r.exp - r.imp })).sort((a, b) => b.balance - a.balance);
    return { surplus: list.filter((r) => r.balance > 0).slice(0, 8), deficit: list.filter((r) => r.balance < 0).reverse().slice(0, 8) };
  }, [exp.data, imp.data]);

  const max = Math.max(1, ...(view ? [...view.surplus, ...view.deficit].map((r) => Math.abs(r.balance)) : [1]));
  const bars = (rows: NonNullable<typeof view>['surplus'], good: boolean) => (
    <div className="drivers">
      {rows.map((r) => (
        <div className="balance-row" key={r.entity.code}>
          <Link to={`/world/markets/${r.entity.code}`}>{entityName(r.entity)}</Link>
          <span className="gov-track" aria-hidden="true">
            <span style={{ width: `${(Math.abs(r.balance) / max) * 100}%`, background: good ? 'var(--good)' : 'var(--critical)' }} />
          </span>
          <span className="num">{good ? '+' : '−'}{compact(Math.abs(r.balance) / 1000)} م.ر.ع</span>
        </div>
      ))}
    </div>
  );

  return (
    <section className="section card" aria-labelledby="pb-h">
      <h2 id="pb-h">الميزان التجاري مع الشركاء — {year}</h2>
      <p className="lead">أين تحقق عُمان فائضًا (صادراتها إلى الشريك أكبر من وارداتها منه) وأين تسجّل عجزًا. من الإحصاءات الجمركية الرسمية (منافذ)؛ النفط الخام غير الموزَّع على دول لا يظهر هنا.</p>
      {(exp.isLoading || imp.isLoading) && <Loading height={200} />}
      {view && (
        <div className="grid cols-2">
          <div><h3>أكبر الفوائض</h3>{view.surplus.length ? bars(view.surplus, true) : <p className="muted">لا شيء.</p>}</div>
          <div><h3>أكبر العجوزات</h3>{view.deficit.length ? bars(view.deficit, false) : <p className="muted">لا شيء.</p>}</div>
        </div>
      )}
    </section>
  );
}
