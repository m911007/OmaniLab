import { useMemo } from 'react';
import { ChartCard } from './ChartCard';
import { RankedBars, type RankedItem } from './RankedBars';
import { Empty, ErrorState, Loading } from './States';
import { entityName, formatPeriod } from '../format';
import { useLatest, useRanking } from '../hooks';

interface Props {
  title: string;
  indicator: string;
  /** الكيان المُبرَز (افتراضيًا عُمان) */
  highlight?: string;
  /** أعلى القيم أفضل؟ يحدد فقط ترتيب العرض؛ الترتيب نفسه تنازلي بالقيمة */
  limit?: number;
  maxPeriod?: string;
  subtitle?: string;
  /** فترة ثابتة للترتيب بدل آخر فترة لدى الكيان المُبرَز (للمؤشرات التي قد لا يملكها) */
  period?: string;
}

/** ترتيب الدول في مؤشر لآخر سنة متاحة لدى الكيان المُبرَز: أعلى N، ثم موقع الكيان إن لم يكن بينها. */
export function WorldRankCard({ title, indicator, highlight = 'OMN', limit = 10, maxPeriod, subtitle, period: fixedPeriod }: Props) {
  const latest = useLatest(highlight, [indicator], maxPeriod);
  const period = fixedPeriod ?? latest.data?.items[0]?.period ?? '';
  const ranking = useRanking({ indicator, period, limit, type: 'country', highlight });

  const items: RankedItem[] = useMemo(() => {
    const data = ranking.data;
    if (!data) return [];
    const base = data.items.map<RankedItem>((i) => ({
      key: i.entity.code,
      rank: i.rank,
      label: entityName(i.entity),
      value: i.value,
      color: i.entity.code === highlight ? 'var(--series-1)' : undefined,
      muted: i.entity.code !== highlight,
    }));
    const inTop = data.items.some((i) => i.entity.code === highlight);
    if (!inTop && data.highlight) {
      base.push({ key: highlight, rank: data.highlight.rank, label: latest.data ? entityName(latest.data.entity) : highlight, value: data.highlight.value, color: 'var(--series-1)' });
    }
    return base;
  }, [ranking.data, highlight, latest.data]);

  const loading = latest.isLoading || ranking.isLoading;
  const error = (latest.error ?? ranking.error) as Error | null;

  return (
    <ChartCard
      title={title}
      subtitle={[subtitle, period && formatPeriod(period), ranking.data?.indicator.unit].filter(Boolean).join(' — ')}
      datasets={ranking.data ? [ranking.data.dataset] : []}
      note={ranking.data ? `الترتيب بين ${ranking.data.count} دولة.` : undefined}
    >
      {loading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : items.length === 0 ? <Empty /> : <RankedBars items={items} unitLabel={ranking.data?.indicator.unit ?? undefined} />}
    </ChartCard>
  );
}
