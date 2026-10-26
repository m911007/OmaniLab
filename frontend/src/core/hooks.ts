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
  type Reading,
  type CatalogResponse,
  type IndicatorDetail,
  type SearchResults,
  type Signal,
  type Scorecard,
  type SeriesResponse,
  type PlaceStats,
  type PlacesResponse,
  type DirectoryRow,
  type EntityIndicator,
  type FirmsResponse,
  type FirmStats,
  type FirmRow,
} from './api';
import { assignLineColors } from './colors';
import { OMR_PER_USD, entityName, toOmrUnit } from './format';

const FIVE_MIN = 5 * 60_000;

export function useSeries(indicator: string, entities: readonly string[], range: { from?: string; to?: string } = {}) {
  return useQuery({
    queryKey: ['series', indicator, [...entities].sort().join(','), range.from, range.to],
    queryFn: ({ signal }) => api<SeriesResponse>('observations', { indicator, entities: entities.join(','), from: range.from, to: range.to }, signal),
    staleTime: FIVE_MIN,
    enabled: entities.length > 0,
  });
}

/** مجموعة بيانات واحدة بمؤشراتها (GET /datasets/{key}). */
export function useDataset(key: string) {
  return useQuery({ queryKey: ['dataset', key], queryFn: ({ signal }) => api<{ data: Dataset }>(`datasets/${key}`, {}, signal).then((r) => r.data), staleTime: FIVE_MIN });
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
  /** يحوّل قيم الدولار إلى ريال عُماني بالربط الرسمي (0.3845) ويستبدل الوحدة */
  omr?: boolean;
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
    const lineColors = assignLineColors(lines);
    const omrKeys = new Set(lines.filter((l) => l.omr).map((l) => l.indicator));
    for (const r of results) {
      if (!r.data) continue;
      datasets.set(r.data.dataset.key, r.data.dataset);
      const meta = r.data.indicator;
      indicators.set(meta.key, omrKeys.has(meta.key) ? { ...meta, unit: toOmrUnit(meta.unit) } : meta);
    }
    for (const [i, l] of lines.entries()) {
      const res = results.find((r) => r.data?.indicator.key === l.indicator)?.data;
      const s = res?.series.find((x) => x.entity.code === l.entity);
      if (!res || !s) continue;
      series.push({
        id: `${l.indicator}|${l.entity}`,
        label: l.label ?? entityName(s.entity),
        color: lineColors[i],
        points: l.omr ? s.points.map(([p, v]) => [p, v * OMR_PER_USD] as typeof s.points[number]) : s.points,
      });
    }
    return { series, datasets: [...datasets.values()], indicators: [...indicators.values()] };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [lines, results.map((r) => r.dataUpdatedAt).join('|')]);

  return { isLoading, error, series, datasets, indicators };
}

export function usePageTitle(title: string, enabled = true): void {
  useEffect(() => {
    if (enabled) document.title = `${title} — المخبر العماني`;
  }, [title, enabled]);
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

/** قراءات المؤشرات: قائمة مفاتيح أو مجموعة مسمّاة (pulse). */
export function useReadings(query: { indicators?: readonly string[]; set?: 'pulse'; entity?: string; maxPeriod?: string; polarity?: string; omr?: boolean }) {
  const indicators = query.indicators?.join(',');
  return useQuery({
    queryKey: ['readings', query.set ?? indicators, query.entity, query.maxPeriod, query.polarity, query.omr],
    queryFn: ({ signal }) => api<{ data: Reading[] }>('readings', { set: query.set, indicators, entity: query.entity, max_period: query.maxPeriod, polarity: query.polarity, omr: query.omr ? 1 : undefined }, signal).then((r) => r.data),
    staleTime: 10 * 60_000,
    enabled: Boolean(query.set || indicators),
  });
}

export function useIndicator(key: string) {
  return useQuery({ queryKey: ['indicator', key], queryFn: ({ signal }) => api<{ data: IndicatorDetail }>(`indicators/${encodeURIComponent(key)}`, {}, signal).then((r) => r.data), staleTime: 10 * 60_000 });
}

export function useIndicatorCatalog(params: { q?: string; section?: string; omn?: boolean; page?: number }) {
  return useQuery({
    queryKey: ['catalog', params],
    queryFn: ({ signal }) => api<CatalogResponse>('indicators', { q: params.q || undefined, section: params.section || undefined, omn: params.omn ? 1 : undefined, page: params.page }, signal),
    staleTime: 10 * 60_000,
    placeholderData: (prev) => prev,
  });
}

/** البحث الشامل (يبدأ من حرفين) */
export function useSearch(q: string) {
  const term = q.trim();
  return useQuery({
    queryKey: ['search', term],
    queryFn: ({ signal }) => api<{ data: SearchResults }>('search', { q: term }, signal).then((r) => r.data),
    enabled: term.length >= 2,
    staleTime: 5 * 60_000,
    placeholderData: (prev) => prev,
  });
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

export function useEntityIndicators(code: string) {
  return useQuery({
    queryKey: ['entity-indicators', code],
    queryFn: ({ signal }) => api<{ data: EntityIndicator[] }>(`entities/${code}/indicators`, {}, signal),
    staleTime: 30 * 60_000,
    enabled: code !== '',
  });
}

export function useFirms(p: { q?: string; country?: string; industry?: string; listed?: string; sort?: string; page: number; per_page?: number }) {
  return useQuery({
    queryKey: ['firms', p],
    queryFn: ({ signal }) => api<FirmsResponse>('firms', { ...p }, signal),
    staleTime: FIVE_MIN,
    placeholderData: (prev) => prev,
  });
}

export function useFirmStats() {
  return useQuery({ queryKey: ['firm-stats'], queryFn: ({ signal }) => api<FirmStats>('firms/stats', {}, signal), staleTime: FIVE_MIN });
}

export function useFirm(code: string) {
  return useQuery({ queryKey: ['firm', code], queryFn: ({ signal }) => api<{ data: FirmRow; dataset: Dataset | null }>(`firms/${code}`, {}, signal), staleTime: FIVE_MIN });
}

export interface StartupRow {
  name: string;
  code: string;
  blurb?: string;
  description?: string;
  stage?: string;
  employees?: string;
  legal_type?: string;
  activity_status?: string;
  sector?: string[];
  subsector?: string[];
  seeking?: string[];
  founded?: string;
  founded_year?: number;
  funding_sought?: string;
  url?: string;
}

export interface StartupsResponse {
  total: number;
  page: number;
  per_page: number;
  data: StartupRow[];
  dataset: Dataset | null;
}

export interface CountItem {
  label: string;
  count: number;
}

export interface StartupStats {
  total: number;
  platform_count: number | null;
  platform_links: number | null;
  by_stage: CountItem[];
  by_sector: CountItem[];
  by_subsector: CountItem[];
  by_legal_type: CountItem[];
  by_status: CountItem[];
  by_employees: CountItem[];
  by_founded_year: CountItem[];
  seeking: CountItem[];
  by_funding: CountItem[];
  dataset: Dataset | null;
}

export function useStartups(p: { q?: string; sector?: string; stage?: string; page: number; per_page?: number }) {
  return useQuery({
    queryKey: ['startups', p],
    queryFn: ({ signal }) => api<StartupsResponse>('startups', { ...p }, signal),
    staleTime: FIVE_MIN,
    placeholderData: (prev) => prev,
  });
}

export function useStartupStats() {
  return useQuery({ queryKey: ['startup-stats'], queryFn: ({ signal }) => api<StartupStats>('startups/stats', {}, signal), staleTime: FIVE_MIN });
}
