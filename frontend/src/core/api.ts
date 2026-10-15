/** عميل الـ API: طبقة رقيقة بأنواع صريحة. كل رد يحمل مصدره وحداثته. */

export type DatasetStatus = 'pending' | 'fresh' | 'stale' | 'quarantined' | 'failing' | 'manual' | 'disabled';

export interface Freshness {
  source_version: string | null;
  source_published_at: string | null;
  last_checked_at: string | null;
  last_changed_at: string | null;
  last_success_at: string | null;
  next_check_at: string | null;
  interval_minutes: number;
}

export interface Dataset {
  key: string;
  name_ar: string;
  name_en: string;
  section: string;
  status: DatasetStatus;
  status_label: string;
  reliability: string;
  source: { key: string; name_ar: string; name_en: string; url: string | null; license: string | null };
  freshness: Freshness;
  indicators?: IndicatorMeta[];
}

export interface IndicatorMeta {
  key: string;
  name_ar: string;
  name_en: string;
  unit: string | null;
  frequency: 'A' | 'Q' | 'M' | 'D';
}

export interface EntityLite {
  code: string;
  name_ar: string | null;
  name_en: string;
  type: 'country' | 'region' | 'group' | 'other';
}

export type Point = [period: string, value: number];

export interface SeriesResponse {
  indicator: IndicatorMeta;
  dataset: Dataset;
  series: { entity: EntityLite; points: Point[] }[];
}

export interface LatestItem {
  indicator: IndicatorMeta;
  period: string;
  value: number;
  previous: { period: string; value: number } | null;
  rank: { position: number; total: number } | null;
  dataset: Dataset;
}

export interface LatestResponse {
  entity: EntityLite;
  items: LatestItem[];
}

export interface RankingResponse {
  indicator: IndicatorMeta;
  dataset: Dataset;
  period: string;
  count: number;
  sum: number;
  items: { rank: number; entity: EntityLite; value: number; share: number | null }[];
  highlight: { code: string; value: number; rank: number; of: number } | null;
}

export interface AuditFinding {
  severity: 'error' | 'warning' | 'info';
  check: string;
  message: string;
  count: number;
  examples: string[];
}

export interface RunSummary {
  id: number;
  dataset: string;
  dataset_name_ar: string;
  status: 'running' | 'unchanged' | 'staged' | 'quarantined' | 'promoted' | 'failed';
  trigger: string;
  started_at: string;
  records_count: number;
  promoted_count: number;
  error: string | null;
  rejected_points: number;
  removed_points: number;
}

export interface Overview {
  counts: { datasets: number; indicators: number; observations: number; entities: number };
  by_status: Partial<Record<DatasetStatus, number>>;
  last_checked_at: string | null;
  last_changed_at: string | null;
  audit: { at: string; findings: AuditFinding[] } | null;
  recent_runs: RunSummary[];
}

export class ApiError extends Error {
  constructor(
    message: string,
    public status: number,
  ) {
    super(message);
  }
}

type Params = Record<string, string | number | undefined | null>;

export async function api<T>(path: string, params: Params = {}, signal?: AbortSignal): Promise<T> {
  const url = new URL(`/api/v1/${path}`, window.location.origin);
  for (const [k, v] of Object.entries(params)) {
    if (v !== undefined && v !== null && v !== '') url.searchParams.set(k, String(v));
  }
  const res = await fetch(url, { headers: { Accept: 'application/json' }, signal });
  if (!res.ok) {
    let message = `HTTP ${res.status}`;
    try {
      message = ((await res.json()) as { message?: string }).message ?? message;
    } catch {
      /* الرد ليس JSON */
    }
    throw new ApiError(message, res.status);
  }
  return (await res.json()) as T;
}

export interface CompanyRow {
  code: string;
  name_en: string;
  name_ar: string | null;
  status?: string;
  category?: string;
  city?: string;
  governorate?: string;
  legal_form?: string;
  creation_date?: string;
  registered_as?: string;
}

export interface CompaniesResponse {
  total: number;
  page: number;
  per_page: number;
  data: CompanyRow[];
  dataset: Dataset | null;
}

export interface CompanyStats {
  total: number;
  by_status: { label: string; count: number }[];
  by_governorate: { label: string; count: number }[];
  by_category: { label: string; count: number }[];
  by_legal_form: { label: string; count: number }[];
  by_year: { year: string; count: number }[];
  dataset: Dataset | null;
}

export interface CompanyDetail {
  code: string;
  name_en: string;
  name_ar: string | null;
  attributes: Record<string, string | string[] | number | null>;
  dataset: Dataset | null;
}

export interface RelationsResponse {
  indicator: IndicatorMeta;
  dataset: Dataset;
  entity: { code: string; name_ar: string | null; name_en: string };
  direction: 'out' | 'in';
  period: string;
  count: number;
  sum: number;
  items: { rank: number; entity: EntityLite; value: number; share: number | null }[];
}

export type StandingClass = 'strength' | 'eroding' | 'challenge' | 'recovering' | 'opportunity' | 'watch' | 'neutral';

export interface ScorecardItem {
  theme: string;
  key: string;
  name_ar: string;
  unit: string | null;
  polarity: string;
  period: string;
  value: number;
  baseline: { period: string; value: number } | null;
  trend: 'improving' | 'worsening' | 'stable' | null;
  class: StandingClass;
  world: { rank: number; of: number; percentile: number; median: number };
  gcc: { rank: number; of: number; best: { code: string; value: number; percentile: number | null } | null; items: { code: string; value: number; percentile: number | null }[] } | null;
  dataset: Dataset;
}

export interface Scorecard {
  entity: string;
  peers: string[];
  thresholds: { strong_from: number; weak_until: number; baseline_years: number };
  counts: Partial<Record<StandingClass, number>>;
  themes: { key: string; label: string; count: number; percentile: number | null }[];
  items: ScorecardItem[];
}

export interface Signal {
  id: string;
  title: string;
  message: string;
  tone: 'critical' | 'warning' | 'good';
  indicator: string;
  entity: string;
  period: string;
  value: number;
  previous: number | null;
  change: number | null;
}

export interface CrossCheckGroup {
  key: string;
  label: string;
  verdict: 'agree' | 'differ' | 'single';
  indicators: string[];
  members: { indicator: string; has_data: boolean; source: { key: string; name_ar: string; url: string | null } | null }[];
}

export interface CrossCheckDetail {
  group: { key: string; label: string; mode: 'relative' | 'absolute'; tolerance: number };
  verdict: 'agree' | 'differ' | 'single';
  periods: { period: string; values: Record<string, number>; spread: number; agree: boolean }[];
  sources: { indicator: string; name_ar: string; unit: string | null; is_base: boolean; dataset: Dataset; points: Point[] }[];
}
