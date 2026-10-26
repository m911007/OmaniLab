import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { api, apiSend } from '@/core/api';

export type PriceType = 'reference' | 'index' | 'customs_unit_value' | 'futures' | 'supplier' | 'asking' | 'derived';
export type Confidence = 'high' | 'medium' | 'low';

export interface ChainSummary {
  id: number;
  slug: string;
  name_ar: string;
  name_en: string;
  status: 'draft' | 'review' | 'published';
  version: number;
  is_demo: boolean;
  nodes: number;
  edges: number;
  missing_weights: number;
  flagged_nodes: number;
  updated_at: string;
}

export interface SeriesLink {
  id: number;
  role: 'primary' | 'alternative' | 'proxy';
  indicator_key: string;
  entity_code: string;
  label_ar: string;
  price_type: PriceType;
  confidence: Confidence;
  market: string | null;
  incoterm: string | null;
  unit: string | null;
  currency: string | null;
  frequency: string;
  license_note: string | null;
  is_demo: boolean;
  latest: { period: string; value: number; updated_at: string; dataset: string; dataset_status: string; last_success_at: string | null; points: number } | null;
}

export interface CheckInput {
  from: string;
  name_ar: string;
  series: string;
  lag_months: number;
  corr: number;
  beta: number;
  se: number;
  t: number | null;
  weight: number;
  weight_min: number;
  weight_max: number;
  conflict: boolean;
}

export interface ChainNode {
  id: number;
  slug: string;
  name_ar: string;
  name_en: string;
  kind: 'raw' | 'energy' | 'intermediate' | 'final' | 'consumable' | 'conversion';
  stage: number;
  unit: string | null;
  hs_codes: string[];
  notes: string | null;
  series: SeriesLink[];
  check: { computed_at: string; n_obs: number; r2: number; window_from: string; window_to: string; flagged: boolean; results: { target_series: string; inputs: CheckInput[] } } | null;
}

export interface EdgeSource {
  id: number;
  applies_to: 'weight' | 'conversion' | 'both';
  title: string;
  url: string | null;
  publisher: string | null;
  year: number | null;
  kind: string;
  reliability: Confidence;
  excerpt: string | null;
}

export interface ChainEdge {
  id: number;
  from_node_id: number;
  to_node_id: number;
  weight: string | null;
  weight_min: string | null;
  weight_max: string | null;
  weight_basis: string;
  conversion_factor: string | null;
  conversion_min: string | null;
  conversion_max: string | null;
  conversion_unit: string | null;
  notes: string | null;
  logistics_note: string | null;
  updated_at: string;
  sources: EdgeSource[];
}

export interface ChainFull {
  chain: { id: number; slug: string; name_ar: string; name_en: string; description: string | null; route_note: string | null; status: string; version: number; is_demo: boolean; updated_at: string };
  nodes: ChainNode[];
  edges: ChainEdge[];
}

export function useChains() {
  return useQuery({ queryKey: ['chains'], queryFn: ({ signal }) => api<{ data: ChainSummary[] }>('intel/chains', {}, signal) });
}

export function useChain(slug: string | undefined) {
  return useQuery({ queryKey: ['chain', slug], queryFn: ({ signal }) => api<ChainFull>(`intel/chains/${slug}`, {}, signal), enabled: !!slug });
}

export function useChainRevisions(slug: string) {
  return useQuery({ queryKey: ['chain-revisions', slug], queryFn: ({ signal }) => api<{ data: { id: number; version: number; note: string; created_at: string; editor: string | null }[] }>(`intel/chains/${slug}/revisions`, {}, signal) });
}

export function useChainMutation(slug: string) {
  const qc = useQueryClient();
  return useMutation({
    mutationFn: ({ method, path, body }: { method: 'POST' | 'PATCH' | 'DELETE'; path: string; body?: unknown }) => apiSend<ChainFull>(method, `intel/chains/${slug}${path}`, body),
    onSuccess: (data) => {
      qc.setQueryData(['chain', slug], data);
      void qc.invalidateQueries({ queryKey: ['chains'] });
      void qc.invalidateQueries({ queryKey: ['chain-revisions', slug] });
    },
  });
}

export const PRICE_TYPE: Record<PriceType, string> = {
  reference: 'سعر مرجعي',
  index: 'مؤشر (ليس سعرًا)',
  customs_unit_value: 'قيمة وحدة جمركية',
  futures: 'عقود آجلة',
  supplier: 'سعر مورد',
  asking: 'سعر معروض',
  derived: 'مشتق',
};
export const CONFIDENCE: Record<Confidence, string> = { high: 'موثوقية عالية', medium: 'موثوقية متوسطة', low: 'موثوقية منخفضة' };
export const KIND: Record<ChainNode['kind'], string> = { raw: 'مادة خام', energy: 'طاقة', intermediate: 'منتج وسيط', final: 'منتج نهائي', consumable: 'مستهلكات', conversion: 'تكاليف تحويل' };
export const BASIS: Record<string, string> = { documented_estimate: 'تقدير موثّق', derived: 'مشتق من مصادر', io_table: 'جداول مدخلات ومخرجات', annual_report: 'تقرير شركة', regression: 'انحدار إحصائي', analyst: 'تقدير محلل', missing: 'ينقصه مصدر' };
export const FREQ: Record<string, string> = { D: 'يومي', W: 'أسبوعي', M: 'شهري', Q: 'فصلي', A: 'سنوي' };

export interface NodeAnalytics {
  node_id: number;
  series: { key: string; label_ar: string; months: number } | null;
  volatility: number | null;
  trade: { hs4: string; year: number; total_usd: number; hhi: number; exporters: { iso: string; name_ar: string; value_usd: number; share: number }[]; computed_at: string } | null;
  risk: { score: number; components: Record<string, { label: string; value: number; score: number }>; note: string } | null;
  lag: { best_lag: number; best_corr: number; n: number; by_lag: { lag: number; corr: number; n: number }[] } | null;
}

export interface ChainAnalyticsResult {
  final_node_id: number | null;
  final_series: string | null;
  nodes: NodeAnalytics[];
}

export function useChainAnalytics(slug: string | undefined) {
  return useQuery({ queryKey: ['chain-analytics', slug], queryFn: ({ signal }) => api<ChainAnalyticsResult>(`intel/chains/${slug}/analytics`, {}, signal), enabled: !!slug, staleTime: 10 * 60_000 });
}

export function useSeriesHistory(slug: string, seriesId: number | null, from: string | undefined) {
  return useQuery({
    queryKey: ['chain-history', slug, seriesId, from],
    queryFn: ({ signal }) => api<{ series: { label_ar: string; unit: string | null; frequency: string; price_type: PriceType; confidence: Confidence; market: string | null; incoterm: string | null; currency: string | null }; points: [string, number][]; latest: SeriesLink['latest'] }>(`intel/chains/${slug}/history`, { series_id: seriesId, from }, signal),
    enabled: seriesId != null,
    staleTime: 10 * 60_000,
  });
}
