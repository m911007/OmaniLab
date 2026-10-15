import { Link } from 'react-router-dom';
import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber, formatPeriod } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';

const LINES: LineSpec[] = [
  { indicator: 'imf.pcps.poildub', entity: 'WLD', label: 'خام دبي (مرجع النفط العُماني)', color: 'var(--series-1)' },
  { indicator: 'imf.pcps.poilbre', entity: 'WLD', label: 'خام برنت', color: 'var(--series-2)' },
];

const FROM = String(new Date().getFullYear() - 3);

/** نبض النفط: آخر سعر شهري وتغيّره الشهري والسنوي ومدى آخر 12 شهرًا، مع مسار ثلاث سنوات. */
export function OilPulse() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: FROM });

  const facts = useMemo(() => {
    const pts = series.find((s) => s.id.startsWith('imf.pcps.poildub'))?.points ?? [];
    if (pts.length < 13) return null;
    const [period, value] = pts[pts.length - 1];
    const prev = pts[pts.length - 2][1];
    const yearAgo = pts[pts.length - 13][1];
    const last12 = pts.slice(-12).map((p) => p[1]);
    return { period, value, mom: ((value - prev) / prev) * 100, yoy: ((value - yearAgo) / yearAgo) * 100, low: Math.min(...last12), high: Math.max(...last12) };
  }, [series]);

  const pct = (v: number) => `${v > 0 ? '+' : ''}${formatNumber(v, 1)}%`;

  return (
    <ChartCard title="نبض النفط" subtitle="متوسط شهري، دولار/برميل" datasets={datasets} actions={<Link to="/commodities">كل الأسواق ←</Link>}>
      {isLoading ? (
        <Loading height={300} />
      ) : error ? (
        <ErrorState error={error} />
      ) : !facts ? (
        <Empty />
      ) : (
        <>
          <div className="pulse">
            <div>
              <div className="stat-label">خام دبي — {formatPeriod(facts.period)}</div>
              <div className="stat-value num">{formatNumber(facts.value, 1)}</div>
            </div>
            <div>
              <div className="stat-label">عن الشهر السابق</div>
              <div className="num pulse-delta" style={{ color: facts.mom >= 0 ? 'var(--good)' : 'var(--critical)' }}>
                {facts.mom >= 0 ? '▲' : '▼'} {pct(facts.mom)}
              </div>
            </div>
            <div>
              <div className="stat-label">عن العام الماضي</div>
              <div className="num pulse-delta" style={{ color: facts.yoy >= 0 ? 'var(--good)' : 'var(--critical)' }}>
                {facts.yoy >= 0 ? '▲' : '▼'} {pct(facts.yoy)}
              </div>
            </div>
            <div>
              <div className="stat-label">مدى 12 شهرًا</div>
              <div className="pulse-delta">
                <span className="num">{formatNumber(facts.low, 0)}</span> – <span className="num">{formatNumber(facts.high, 0)}</span>
              </div>
            </div>
          </div>
          <LineChartView series={series} unit="دولار/برميل" height={240} zeroLine={false} />
        </>
      )}
    </ChartCard>
  );
}
