import { Link } from 'react-router-dom';
import type { ScorecardItem } from '@/core/api';
import { Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useScorecard } from '@/core/hooks';

const STRONG = ['strength', 'eroding'];
const WEAK = ['challenge', 'recovering'];

function Row({ item }: { item: ScorecardItem }) {
  return (
    <li>
      <Link to={`/indicator/${item.key}`}>{item.name_ar}</Link>
      <span className="muted">
        {' '}{item.world.percentile >= 50 ? 'أفضل من' : 'أسوأ من'} <bdi className="num">{formatNumber(item.world.percentile >= 50 ? item.world.percentile : 100 - item.world.percentile, 0)}%</bdi> من دول العالم
        {item.gcc && <> · المرتبة {item.gcc.rank} خليجيًا</>}
        {item.trend === 'improving' ? ' · يتحسن' : item.trend === 'worsening' ? ' · يتراجع' : ''}
      </span>
    </li>
  );
}

/** أين تتقدم عُمان وأين تتأخر: أعلى ثلاث نقاط قوة وأشد ثلاثة تحديات من بطاقة الأداء (مقارنة بالعالم). */
export function ScorecardHighlights() {
  const { data, isLoading } = useScorecard();
  if (isLoading) return <Loading height={180} />;
  if (!data) return null;
  const strengths = data.items.filter((i) => STRONG.includes(i.class)).sort((a, b) => b.world.percentile - a.world.percentile).slice(0, 3);
  const challenges = data.items.filter((i) => WEAK.includes(i.class)).sort((a, b) => a.world.percentile - b.world.percentile).slice(0, 3);
  return (
    <div className="grid cols-2 highlights">
      <div className="card">
        <h3 className="card-title"><span className="chip good"><span aria-hidden="true">▲</span> نقاط قوة</span></h3>
        <ul className="highlight-list">{strengths.map((i) => <Row key={i.key} item={i} />)}</ul>
      </div>
      <div className="card">
        <h3 className="card-title"><span className="chip critical"><span aria-hidden="true">▼</span> تحديات</span></h3>
        <ul className="highlight-list">{challenges.map((i) => <Row key={i.key} item={i} />)}</ul>
      </div>
      <p className="source-note" style={{ gridColumn: '1 / -1' }}>من {data.items.length} مؤشرًا في <Link to="/scorecard">بطاقة الأداء</Link>: الموقع بين دول العالم في آخر سنة متاحة، والاتجاه مقارنةً بخمس سنوات سابقة.</p>
    </div>
  );
}
