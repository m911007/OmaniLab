import { Link } from 'react-router-dom';
import type { ScorecardItem } from '@/core/api';
import { Loading } from '@/core/components/States';
import { useScorecard } from '@/core/hooks';
import { headlines } from './headlines';

const GROUPS = [
  { key: 'strengths', title: 'نقاط القوة', tone: 'good', icon: '▲' },
  { key: 'challenges', title: 'التحديات', tone: 'critical', icon: '▼' },
  { key: 'opportunities', title: 'فرص صاعدة', tone: 'neutral', icon: '◆' },
  { key: 'watch', title: 'تحت المراقبة', tone: 'warning', icon: '!' },
] as const;

/** أبرز نتائج بطاقة الأداء لصانع القرار: ثلاثة من كل فئة مع موقعها العالمي، ورابط للتفصيل. */
export function HeadlineBrief() {
  const { data: card, isLoading } = useScorecard();
  if (isLoading) return <Loading height={180} />;
  if (!card) return null;
  const h = headlines(card, 3);

  return (
    <section className="card brief" aria-label="أبرز نتائج بطاقة الأداء">
      <div className="card-head">
        <div>
          <h3 className="card-title">ما الذي يستحق انتباه صانع القرار؟</h3>
          <div className="card-sub">مقارنة عُمان بدول العالم في {card.items.length} مؤشرًا، وبمستواها قبل {card.thresholds.baseline_years} سنوات</div>
        </div>
        <Link to="/scorecard">بطاقة الأداء كاملة ←</Link>
      </div>
      <div className="brief-grid">
        {GROUPS.map((g) => (
          <div key={g.key}>
            <h3>
              <span className={`chip ${g.tone}`}>
                <span aria-hidden="true">{g.icon}</span>
                {g.title}
              </span>
            </h3>
            <List items={h[g.key]} />
          </div>
        ))}
      </div>
    </section>
  );
}

function List({ items }: { items: ScorecardItem[] }) {
  if (items.length === 0) return <p className="muted">لا يوجد.</p>;
  return (
    <ul>
      {items.map((i) => (
        <li key={i.key}>
          {i.name_ar} <span className="muted">— <span className="num">{i.world.rank}</span> من <span className="num">{i.world.of}</span></span>
        </li>
      ))}
    </ul>
  );
}
