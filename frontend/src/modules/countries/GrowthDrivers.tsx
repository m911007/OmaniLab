import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { SourceNote } from '@/core/components/SourceNote';
import { Loading } from '@/core/components/States';
import { entityName, formatNumber } from '@/core/format';
import { useRanking } from '@/core/hooks';

const LAST_ACTUAL = new Date().getFullYear() - 1;
const YEARS = Array.from({ length: 8 }, (_, i) => String(LAST_ACTUAL - 4 + i));

/** من يقود نمو الاقتصاد العالمي؟ مساهمة كل اقتصاد = نموه الحقيقي × حصته من الناتج العالمي بتعادل القوة الشرائية (نقطة مئوية). */
export function GrowthDrivers() {
  const [year, setYear] = useState(String(LAST_ACTUAL));
  const growth = useRanking({ indicator: 'imf.NGDP_RPCH', period: year, limit: 300, type: 'country' });
  const share = useRanking({ indicator: 'imf.PPPSH', period: year, limit: 300, type: 'country' });

  const view = useMemo(() => {
    if (!growth.data || !share.data) return null;
    const weights = new Map(share.data.items.map((i) => [i.entity.code, i.value]));
    const rows = growth.data.items
      .filter((i) => weights.has(i.entity.code))
      .map((i) => ({ entity: i.entity, growth: i.value, weight: weights.get(i.entity.code)!, contribution: (i.value * weights.get(i.entity.code)!) / 100 }))
      .sort((a, b) => b.contribution - a.contribution);
    const total = rows.reduce((s, r) => s + r.contribution, 0);
    const top = rows.slice(0, 10);
    const oman = rows.find((r) => r.entity.code === 'OMN');
    const omanRank = oman ? rows.indexOf(oman) + 1 : null;
    return { rows, top, total, oman, omanRank, topShare: top.slice(0, 3).reduce((s, r) => s + r.contribution, 0) / (total || 1) };
  }, [growth.data, share.data]);

  const max = Math.max(...(view?.top.map((r) => Math.abs(r.contribution)) ?? [1]), 0.001);

  return (
    <section className="section card" aria-labelledby="gd-h">
      <div className="card-head" style={{ flexWrap: 'wrap', gap: 10 }}>
        <div>
          <h2 id="gd-h">من يقود نمو الاقتصاد العالمي؟</h2>
          <p className="lead" style={{ margin: 0 }}>مساهمة كل اقتصاد في نمو الناتج العالمي، بالنقطة المئوية = نموه الحقيقي × حصته من الناتج العالمي (بتعادل القوة الشرائية).</p>
        </div>
        <div className="toggle" role="group" aria-label="السنة">
          {YEARS.map((y) => (
            <button key={y} aria-pressed={y === year} onClick={() => setYear(y)}>
              {y}
              {Number(y) > LAST_ACTUAL ? '*' : ''}
            </button>
          ))}
        </div>
      </div>
      {(growth.isLoading || share.isLoading) && <Loading height={220} />}
      {view && (
        <>
          <p className="narrative-line">
            نما الاقتصاد العالمي بنحو <strong className="num">{formatNumber(view.total, 2)}</strong> نقطة مئوية في {year}؛ ثلاثة اقتصادات تصدّرت المساهمة تمثّل <strong className="num">{formatNumber(view.topShare * 100, 0)}%</strong> منه.
            {view.oman && view.omanRank && (
              <> وتساهم عُمان بـ <strong className="num">{formatNumber(view.oman.contribution, 3)}</strong> نقطة (المرتبة <span className="num">{view.omanRank}</span> من <span className="num">{view.rows.length}</span>).</>
            )}
          </p>
          <div className="drivers" role="list">
            {view.top.map((r, i) => (
              <div className="driver-row" role="listitem" key={r.entity.code}>
                <span className="rk num">{i + 1}</span>
                <Link to={`/markets/${r.entity.code}`}>{entityName(r.entity)}</Link>
                <span className="gov-track" aria-hidden="true">
                  <span style={{ width: `${(Math.abs(r.contribution) / max) * 100}%`, background: r.contribution >= 0 ? 'var(--good)' : 'var(--critical)' }} />
                </span>
                <span className="num">{formatNumber(r.contribution, 2)} نقطة</span>
                <span className="muted num">نمو {formatNumber(r.growth, 1)}% · حصة {formatNumber(r.weight, 1)}%</span>
              </div>
            ))}
          </div>
          <p className="source-note">
            {Number(year) > LAST_ACTUAL ? '* توقعات صندوق النقد. ' : ''}الحصص بتعادل القوة الشرائية من صندوق النقد (WEO). المجموع محسوب من الاقتصادات الظاهرة في البيانات.
          </p>
          {growth.data?.dataset && <SourceNote datasets={[growth.data.dataset]} />}
        </>
      )}
    </section>
  );
}
