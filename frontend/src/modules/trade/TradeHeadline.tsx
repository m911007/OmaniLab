import { useMemo } from 'react';
import { ReadingGrid } from '@/core/components/ReadingGrid';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { balanceOf, coverage, growthPct, useTradeTotals } from './TradeDerived';
import { StatLabel } from '@/core/components/IndicatorIcon';

const lastKey = (m: Map<string, number>) => [...m.keys()].sort().pop();

/** رأس التجارة: صادرات وواردات آخر سنة كاملة (منافذ) والميزان والتغطية ونمو الصادرات، مع جملة تقييم من قالب ثابت. */
export function TradeHeadline() {
  const { isLoading, error, exports, imports, reexports } = useTradeTotals();
  const v = useMemo(() => {
    const y = lastKey(new Map([...exports].filter(([k]) => imports.has(k))));
    if (!y) return null;
    const bal = balanceOf(exports, imports).get(y)!;
    return { y, x: exports.get(y)!, m: imports.get(y)!, re: reexports.get(y), bal, cov: coverage(exports, imports).get(y)!, g: growthPct(exports).get(y) };
  }, [exports, imports, reexports]);
  if (isLoading) return <Loading height={160} />;
  if (error) return <ErrorState error={error} />;
  if (!v) return null;
  const tiles = [
    { label: `الصادرات ${v.y}`, value: formatNumber(v.x, 1), meta: 'B ر.ع — منافذ (دون إعادة التصدير)' },
    { label: `الواردات ${v.y}`, value: formatNumber(v.m, 1), meta: 'B ر.ع — منافذ' },
    { label: 'الميزان التجاري', value: `${v.bal >= 0 ? '+' : ''}${formatNumber(v.bal, 1)}`, meta: 'B ر.ع — مشتق', tone: v.bal >= 0 ? 'var(--good)' : 'var(--critical)' },
    { label: 'تغطية الصادرات للواردات', value: `${formatNumber(v.cov, 0)}%`, meta: 'مشتق' },
    { label: 'نمو الصادرات', value: v.g === undefined ? '—' : `${v.g > 0 ? '+' : ''}${formatNumber(v.g, 1)}%`, meta: `${v.y} عن ${Number(v.y) - 1} — مشتق`, tone: v.g === undefined ? undefined : v.g >= 0 ? 'var(--good)' : 'var(--critical)' },
  ];
  return (
    <>
      <p className="callout fiscal-assess" role="status">
        بلغت صادرات عُمان السلعية {formatNumber(v.x, 1)} مليار ر.ع في {v.y} مقابل واردات {formatNumber(v.m, 1)}، ففائض تجاري {formatNumber(v.bal, 1)} مليار (تغطية {formatNumber(v.cov, 0)}%){v.g !== undefined ? `، والصادرات ${v.g >= 0 ? 'ارتفعت' : 'انخفضت'} ${formatNumber(Math.abs(v.g), 1)}% عن السنة السابقة` : ''}.
      </p>
      <ReadingGrid className="fiscal-tiles">
        {tiles.map((t) => (
          <div key={t.label} className="card tile" style={{ borderInlineStartColor: t.tone }}>
            <StatLabel>{t.label}</StatLabel>
            <span className="stat-value num">{t.value}</span>
            <span className="stat-meta">{t.meta}</span>
          </div>
        ))}
      </ReadingGrid>
    </>
  );
}
