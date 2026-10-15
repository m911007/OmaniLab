import { useLatest } from '../hooks';
import { StatCard } from './StatCard';

/** بطاقة رقم رئيسي لمؤشر وكيان: آخر قيمة (حتى maxPeriod لتجاوز التوقعات). */
export function KeyStat({ entity, indicator, label, maxPeriod, compactValue, goodWhen }: { entity: string; indicator: string; label: string; maxPeriod?: string; compactValue?: boolean; goodWhen?: 'up' | 'down' }) {
  const { data, isLoading } = useLatest(entity, [indicator], maxPeriod);
  return <StatCard label={label} item={data?.items[0]} loading={isLoading} compactValue={compactValue} goodWhen={goodWhen} />;
}
