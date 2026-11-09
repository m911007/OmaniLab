import { useMemo } from 'react';
import { ReadingGrid } from './ReadingGrid';
import { ErrorState, Loading } from './States';
import { formatNumber, formatPeriod, shortUnit } from '../format';

/** «عن عام 2024» للسنوي، «عن الربع 2 2025» أو «عن مايو 2025» لغيره: تحديد صريح لفترة المقارنة. */
export const sinceLabel = (period: string | null): string => (period ? (/^\d{4}$/.test(period) ? `عن عام ${period}` : `عن ${formatPeriod(period)}`) : 'عن الفترة السابقة');
import { DeltaPill, StatLabel } from './IndicatorIcon';
import { useLines, type LineSpec } from '../hooks';
import { lastActualYear } from '../lines';

export interface TileSpec {
  indicator: string;
  label: string;
  /** level: القيمة نفسها · yoy: التغيّر % عن الفترة نفسها قبل سنة (للمستويات الشهرية والسنوية) */
  mode?: 'level' | 'yoy';
  digits?: number;
  suffix?: string;
  goodWhen?: 'up' | 'down';
  meta?: string;
}

export interface TileValue {
  value: number;
  period: string;
  /** الفرق عن الفترة السابقة (النقطة السابقة في السلسلة) */
  delta: number | null;
  previous: number | null;
  /** فترة القيمة السابقة المقارَن بها */
  previousPeriod: string | null;
}

const monthsBack = (period: string, n: number): string => {
  const [y, m] = period.split('-').map(Number);
  const t = y * 12 + (m - 1) - n;
  return `${Math.floor(t / 12)}-${String((t % 12) + 1).padStart(2, '0')}`;
};

/** القيمة المعروضة لبطاقة: آخر نقطة فعلية (IMF حتى آخر سنة فعلية)، والتغيّر عن السابقة؛ yoy يقارن بالفترة نفسها قبل سنة. */
export function tileValue(points: readonly [string, number][], spec: TileSpec, maxActual: string): TileValue | null {
  const pts = [...points].filter((p) => (spec.indicator.startsWith('imf.') ? p[0].slice(0, 4) <= maxActual : true)).sort((a, b) => a[0].localeCompare(b[0]));
  if (pts.length === 0) return null;
  const last = pts[pts.length - 1];
  if (spec.mode === 'yoy') {
    const prior = last[0].length === 7 ? monthsBack(last[0], 12) : String(Number(last[0]) - 1);
    const base = pts.find((p) => p[0] === prior)?.[1];
    if (!base) return null;
    const prevBase = pts.find((p) => p[0] === (last[0].length === 7 ? monthsBack(last[0], 24) : String(Number(last[0]) - 2)))?.[1];
    const prevVal = pts.find((p) => p[0] === prior)?.[1];
    const yoy = ((last[1] - base) / Math.abs(base)) * 100;
    const prevYoy = prevVal !== undefined && prevBase ? ((prevVal - prevBase) / Math.abs(prevBase)) * 100 : null;
    return { value: yoy, period: last[0], previous: prevYoy, previousPeriod: prior, delta: prevYoy === null ? null : yoy - prevYoy };
  }
  const prev = pts.length > 1 ? pts[pts.length - 2][1] : null;
  return { value: last[1], period: last[0], previous: prev, previousPeriod: pts.length > 1 ? pts[pts.length - 2][0] : null, delta: prev === null ? null : last[1] - prev };
}

const tone = (d: number | null, goodWhen?: 'up' | 'down'): 'good' | 'critical' | undefined => (d === null || !goodWhen || Math.abs(d) < 1e-9 ? undefined : (d > 0) === (goodWhen === 'up') ? 'good' : 'critical');

/**
 * رأس صفحة موضوعية: جملة تقييم من قالب ثابت وبطاقات بحجم موحّد (القيمة، التغيّر عن السابق، المصدر)،
 * قبل أي رسم. الألوان تدل على اتجاه التغيّر الأفضل/الأسوأ لا على هدف رسمي.
 */
export function HeadlineStrip({ tiles, sentence, from }: { tiles: readonly TileSpec[]; sentence?: (v: Record<string, TileValue | null>) => string | null; from?: string }) {
  const lines = useMemo<LineSpec[]>(() => tiles.map((t) => ({ indicator: t.indicator, entity: 'OMN' })), [tiles]);
  const { isLoading, error, series } = useLines(lines, { from: from ?? String(new Date().getFullYear() - 4) });
  const maxActual = lastActualYear();
  const values = useMemo(() => Object.fromEntries(tiles.map((t) => [t.indicator, tileValue(series.find((s) => s.id.startsWith(`${t.indicator}|`))?.points ?? [], t, maxActual)])), [tiles, series, maxActual]);
  if (isLoading) return <Loading height={160} />;
  if (error) return <ErrorState error={error} />;
  const text = sentence?.(values) ?? null;
  return (
    <>
      {text && <p className="callout fiscal-assess" role="status">{text}</p>}
      <ReadingGrid className="fiscal-tiles">
        {tiles.map((t) => {
          const v = values[t.indicator];
          const d = tone(v?.delta ?? null, t.goodWhen);
          const digits = t.digits ?? 1;
          return (
            <div key={t.indicator} className="card tile" style={{ borderInlineStartColor: d === 'good' ? 'var(--good)' : d === 'critical' ? 'var(--critical)' : undefined }}>
              <StatLabel>{t.label}</StatLabel>
              <span className="stat-value num">{v ? `${formatNumber(v.value, digits)}${t.suffix ?? ''}` : '—'}</span>
              {v && v.delta !== null && (
                <span className="stat-deltarow">
                  {Math.abs(v.delta) < 0.5 * 10 ** -digits ? (
                    <DeltaPill direction="flat" tone="flat">ثابت</DeltaPill>
                  ) : (
                    <DeltaPill direction={v.delta > 0 ? 'up' : 'down'} tone={d === 'good' ? 'good' : d === 'critical' ? 'bad' : 'flat'}>
                      <bdi className="num">{`${v.delta > 0 ? '+' : '−'}${formatNumber(Math.abs(v.delta), digits)}`}</bdi>
                      {t.mode === 'yoy' || t.suffix?.includes('%') ? ' نقطة' : ''}
                    </DeltaPill>
                  )}
                  <span className="reading-since">{sinceLabel(v.previousPeriod)}</span>
                </span>
              )}
              <span className="stat-meta">
                {v ? formatPeriod(v.period) : ''}
                {t.meta ? ` — ${shortUnit(t.meta)}` : ''}
              </span>
            </div>
          );
        })}
      </ReadingGrid>
    </>
  );
}
