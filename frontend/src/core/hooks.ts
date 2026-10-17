import { useQueries, useQuery } from '@tanstack/react-query';
import { useEffect, useMemo } from 'react';
import {
  api,
  type CompaniesResponse,
  type CompanyDetail,
  type CrossCheckDetail,
  type CrossCheckGroup,
  type CompanyStats,
  type Dataset,
  type EntityLite,
  type IndicatorMeta,
  type LatestResponse,
  type Overview,
  type Point,
  type RankingResponse,
  type RelationsResponse,
  type Signal,
  type Scorecard,
  type SeriesResponse,
  type PlaceStats,
  type PlacesResponse,
  type DirectoryRow,
} from './api';
import { assignColors } from './colors';
import { entityName } from './format';

const FIVE_MIN = 5 * 60_000;

export function useSeries(indicator: string, entities: readonly string[], range: { from?: string; to?: string } = {}) {
  return useQuery({
    queryKey: ['series', indicator, [...entities].sort().join(','), range.from, range.to],
    queryFn: ({ signal }) => api<SeriesResponse>('observations', { indicator, entities: entities.join(','), from: range.from, to: range.to }, signal),
    staleTime: FIVE_MIN,
    enabled: entities.length > 0,
  });
}

export function useLatest(entity: string, indicators: readonly string[], maxPeriod?: string) {
  return useQuery({
    queryKey: ['latest', entity, indicators.join(','), maxPeriod],
    queryFn: ({ signal }) => api<LatestResponse>(`entities/${entity}/latest`, { indicators: indicators.join(','), max_period: maxPeriod }, signal),
    staleTime: FIVE_MIN,
    enabled: indicators.length > 0 && entity !== '',
  });
}

export function useRanking(p: { indicator: string; period: string; limit?: number; type?: 'country' | 'any'; highlight?: string }) {
  return useQuery({
    queryKey: ['ranking', p],
    queryFn: ({ signal }) => api<RankingResponse>('rankings', { ...p }, signal),
    staleTime: FIVE_MIN,
    enabled: p.period !== '',
  });
}

export function useEntities(type: EntityLite['type'] = 'country', withIndicator?: string) {
  return useQuery({
    queryKey: ['entities', type, withIndicator],
    queryFn: ({ signal }) => api<{ data: EntityLite[] }>('entities', { type, with: withIndicator }, signal).then((r) => r.data),
    staleTime: 60 * 60_000,
  });
}

export function useOverview() {
  return useQuery({ queryKey: ['overview'], queryFn: ({ signal }) => api<Overview>('overview', {}, signal), staleTime: 60_000, refetchInterval: 120_000 });
}

export function useDatasets() {
  return useQuery({ queryKey: ['datasets'], queryFn: ({ signal }) => api<{ data: Dataset[] }>('datasets', {}, signal).then((r) => r.data), staleTime: 60_000 });
}

/** سلسلة جاهزة للرسم: لون ثابت، وتسمية، ونقاط. */
export interface SeriesItem {
  id: string;
  label: string;
  color: string;
  points: Point[];
}

/** خط في مخطط: مؤشر + كيان (+ تسمية ولون اختياريان). */
export interface LineSpec {
  indicator: string;
  entity: string;
  label?: string;
  color?: string;
}

/**
 * يجلب خطوطًا من مؤشرات مختلفة بأقل عدد طلبات (طلب لكل مؤشر يضم كياناته)، ويعيدها جاهزة للرسم
 * مع مجموعات البيانات (لإظهار المصدر والحداثة) وبيانات المؤشرات (للوحدة).
 */
export function useLines(lines: readonly LineSpec[], range: { from?: string; to?: string } = {}) {
  const byIndicator = useMemo(() => {
    const m = new Map<string, string[]>();
    for (const l of lines) m.set(l.indicator, [...(m.get(l.indicator) ?? []), l.entity]);
    return [...m.entries()];
  }, [lines]);

  const results = useQueries({
    queries: byIndicator.map(([indicator, entities]) => ({
      queryKey: ['series', indicator, [...new Set(entities)].sort().join(','), range.from, range.to],
      queryFn: ({ signal }: { signal: AbortSignal }) =>
        api<SeriesResponse>('observations', { indicator, entities: [...new Set(entities)].join(','), from: range.from, to: range.to }, signal),
      staleTime: FIVE_MIN,
    })),
  });

  const isLoading = results.some((r) => r.isLoading);
  const error = results.find((r) => r.error)?.error as Error | undefined;

  const { series, datasets, indicators } = useMemo(() => {
    const series: SeriesItem[] = [];
    const datasets = new Map<string, Dataset>();
    const indicators = new Map<string, IndicatorMeta>();
    const colors = assignColors(lines.map((l) => l.entity));
    for (const r of results) {
      if (!r.data) continue;
      datasets.set(r.data.dataset.key, r.data.dataset);
      indicators.set(r.data.indicator.key, r.data.indicator);
    }
    for (const l of lines) {
      const res = results.find((r) => r.data?.indicator.key === l.indicator)?.data;
      const s = res?.series.find((x) => x.entity.code === l.entity);
      if (!res || !s) continue;
      series.push({
        id: `${l.indicator}|${l.entity}`,
        label: l.label ?? entityName(s.entity),
        color: l.color ?? colors[l.entity],
        points: s.points,
      });
    }
    return { series, datasets: [...datasets.values()], indicators: [...indicators.values()] };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lines, results.map((r) => r.dataUpdatedAt).join('|')]);

  return { isLoading, error, series, datasets, indicators };
}

export function usePageTitle(title: string): void {
  useEffect(() => {
    document.title = `${title} — المخبر العماني`;
  }, [title]);
}

export interface CompanyQuery {
  q?: string;
  status?: string;
  category?: string;
  governorate?: string;
  sort?: 'name' | 'created';
  page?: number;
}

export function useCompanies(query: CompanyQuery) {
  return useQuery({
    queryKey: ['companies', query],
    queryFn: ({ signal }) => api<CompaniesResponse>('companies', { ...query, per_page: 20 }, signal),
    staleTime: FIVE_MIN,
    placeholderData: (prev) => prev,
  });
}

export function useCompanyStats() {
  return useQuery({ queryKey: ['company-stats'], queryFn: ({ signal }) => api<CompanyStats>('companies/stats', {}, signal), staleTime: FIVE_MIN });
}

export function useCompany(code: string | null) {
  return useQuery({
    queryKey: ['company', code],
    queryFn: ({ signal }) => api<CompanyDetail>(`companies/${code}`, {}, signal),
    enabled: code !== null,
    staleTime: FIVE_MIN,
  });
}

export function useRelations(p: { indicator: string; entity: string; period: string; direction?: 'out' | 'in'; limit?: number }) {
  return useQuery({
    queryKey: ['relations', p],
    queryFn: ({ signal }) => api<RelationsResponse>('relations', { ...p }, signal),
    staleTime: FIVE_MIN,
    enabled: p.period !== '' && p.entity !== '',
  });
}

export function useScorecard() {
  return useQuery({ queryKey: ['scorecard'], queryFn: ({ signal }) => api<Scorecard>('insights/scorecard', {}, signal), staleTime: 10 * 60_000 });
}

export function useSignals() {
  return useQuery({ queryKey: ['signals'], queryFn: ({ signal }) => api<{ data: Signal[] }>('insights/signals', {}, signal).then((r) => r.data), staleTime: 5 * 60_000, refetchInterval: 15 * 60_000 });
}

/** القياسات المنشورة من عدة مصادر وحكم مطابقتها لكيان (الافتراضي عُمان). */
export function useCrosschecks(entity = 'OMN') {
  return useQuery({ queryKey: ['crosschecks', entity], queryFn: ({ signal }) => api<{ data: CrossCheckGroup[] }>('insights/crosschecks', { entity }, signal).then((r) => r.data), staleTime: 10 * 60_000 });
}

export function useCrosscheckDetail(indicator: string, entity: string, enabled: boolean) {
  return useQuery({ queryKey: ['crosscheck-detail', indicator, entity], queryFn: ({ signal }) => api<CrossCheckDetail>('insights/crosschecks/detail', { indicator, entity }, signal), staleTime: 10 * 60_000, enabled });
}

export function usePlaceStats() {
  return useQuery({
    queryKey: ['place-stats'],
    queryFn: ({ signal }) => api<PlaceStats>('places/stats', {}, signal),
    staleTime: FIVE_MIN,
  });
}

export function usePlaces(p: { group?: string; category?: string; governorate?: string; q?: string; page: number; per_page?: number }) {
  return useQuery({
    queryKey: ['places', p],
    queryFn: ({ signal }) => api<PlacesResponse>('places', { ...p }, signal),
    staleTime: FIVE_MIN,
    placeholderData: (prev) => prev,
  });
}

export function useCountryDirectory() {
  return useQuery({
    queryKey: ['country-directory'],
    queryFn: ({ signal }) => api<{ data: DirectoryRow[] }>('countries/directory', {}, signal),
    staleTime: 30 * 60_000,
  });
}
