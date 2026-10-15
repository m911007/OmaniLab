import { useMemo } from 'react';
import { useLatest, useSeries } from '../hooks';
import { StatCard } from './StatCard';

/** بطاقة رقم رئيسي لمؤشر وكيان: آخر قيمة (حتى maxPeriod لتجاوز التوقعات). */
export function KeyStat({ entity, indicator, label, maxPeriod, compactValue, goodWhen, trend }: { entity: string; indicator: string; label: string; maxPeriod?: string; compactValue?: boolean; goodWhen?: 'up' | 'down'; trend?: boolean }) {
  const { data, isLoading } = useLatest(entity, [indicator], maxPeriod);
  const from = String(new Date().getFullYear() - 10);
  const { data: series } = useSeries(indicator, trend ? [entity] : [], { from });
  const spark = useMemo(
    () =>
      (series?.series[0]?.points ?? [])
        .filter(([p]) => !maxPeriod || p <= maxPeriod)
        .slice(-14)
        .map((p) => p[1]),
    [series, maxPeriod],
  );
  return <StatCard label={label} item={data?.items[0]} loading={isLoading} compactValue={compactValue} goodWhen={goodWhen} spark={trend ? spark : undefined} />;
}
