import type { Scorecard, ScorecardItem } from '@/core/api';
import { COUNTRY_NAMES } from '@/core/groups';
import { formatValue } from '@/core/format';

/** نقاط الأولوية: كلما كان الموقع العالمي أضعف، والاتجاه أسوأ، والفجوة عن أفضل جار خليجي أكبر، ارتفعت الأولوية. */
function priorityScore(i: ScorecardItem): number {
  let s = 100 - i.world.percentile;
  if (i.trend === 'worsening') {
    s += 25;
  } else if (i.trend === 'improving') {
    s -= 15;
  }
  if (i.gcc && i.gcc.rank >= i.gcc.of - 1) {
    s += 10;
  }
  return s;
}

export function topPriorities(card: Scorecard, n = 6): ScorecardItem[] {
  return card.items
    .filter((i) => i.world.percentile < 45 || (i.trend === 'worsening' && i.world.percentile < 70))
    .sort((a, b) => priorityScore(b) - priorityScore(a))
    .slice(0, n);
}

function sentence(i: ScorecardItem): string {
  const parts = [`المرتبة ${i.world.rank} من ${i.world.of} عالميًا`];
  if (i.trend === 'worsening') {
    parts.push('والاتجاه إلى التراجع');
  } else if (i.trend === 'improving') {
    parts.push('لكنها تتحسن');
  } else if (i.trend === 'stable') {
    parts.push('دون تحسن ملحوظ');
  }
  return parts.join('، ');
}

export function Priorities({ card }: { card: Scorecard }) {
  const items = topPriorities(card);
  if (items.length === 0) {
    return <p className="muted">لا توجد مؤشرات في منطقة الخطر وفق المعايير الحالية.</p>;
  }
  return (
    <ol className="priorities">
      {items.map((i, idx) => {
        const best = i.gcc?.best;
        const behind = best && best.code !== 'OMN';
        return (
          <li key={i.key} className="card priority">
            <span className="priority-n num" aria-hidden="true">
              {idx + 1}
            </span>
            <div>
              <strong>{i.name_ar}</strong>
              <p className="priority-now">
                <span className="num">{formatValue(i.value, i.unit)}</span> {i.unit ?? ''} — {sentence(i)}.
              </p>
              {behind && best && (
                <p className="priority-gap muted">
                  أفضل أداء خليجي: {COUNTRY_NAMES[best.code] ?? best.code} (<span className="num">{formatValue(best.value, i.unit)}</span>) — عُمان <span className="num">{i.gcc?.rank}</span> من <span className="num">{i.gcc?.of}</span> خليجيًا.
                </p>
              )}
            </div>
          </li>
        );
      })}
    </ol>
  );
}
