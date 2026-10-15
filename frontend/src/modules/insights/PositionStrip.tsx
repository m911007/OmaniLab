import type { ScorecardItem } from '@/core/api';
import { COUNTRY_NAMES } from '@/core/groups';
import { formatValue } from '@/core/format';

const pos = (pct: number): string => `${Math.min(100, Math.max(0, pct))}%`;

/**
 * شريط الموقع: يمثّل ترتيب الدول من الأضعف (يسارًا) إلى الأقوى (يمينًا) بعد توجيه المؤشر حسب قطبيته،
 * وتظهر عُمان بعلامة كبيرة، ودول الخليج بنقاط، والوسيط العالمي بخط. المناطق الطرفية (30% و70%) هي حدود التصنيف.
 */
export function PositionStrip({ item, weakUntil, strongFrom }: { item: ScorecardItem; weakUntil: number; strongFrom: number }) {
  const peers = (item.gcc?.items ?? []).filter((p) => p.code !== 'OMN' && p.percentile !== null);
  const summary = `عُمان في المئين ${Math.round(item.world.percentile)} عالميًا`;
  return (
    <div className="strip" role="img" aria-label={`${summary}. ${peers.map((p) => `${COUNTRY_NAMES[p.code] ?? p.code}: ${formatValue(p.value, item.unit)}`).join('، ')}`}>
      <div className="strip-track">
        <span className="strip-zone weak" style={{ width: pos(weakUntil) }} />
        <span className="strip-zone strong" style={{ width: pos(100 - strongFrom) }} />
        <span className="strip-median" style={{ left: '50%' }} title="الوسيط العالمي" />
        {peers.map((p) => (
          <span key={p.code} className="strip-peer" style={{ left: pos(p.percentile ?? 0) }} title={`${COUNTRY_NAMES[p.code] ?? p.code}: ${formatValue(p.value, item.unit)}`} />
        ))}
        <span className="strip-me" style={{ left: pos(item.world.percentile) }} title={`عُمان: ${formatValue(item.value, item.unit)}`} />
      </div>
      <div className="strip-scale" aria-hidden="true">
        <span>الأضعف</span>
        <span>الأقوى</span>
      </div>
    </div>
  );
}
