import { useMutation, useQuery } from '@tanstack/react-query';
import { api, apiSend } from '@/core/api';

export type Mode = 'import' | 'export';
export type Component = 'scale' | 'growth' | 'price' | 'logistics' | 'risk';

export interface Product {
  id: number;
  hs4: string;
  name_en: string;
  name_ar: string;
  section?: number;
  hs2?: string;
  commodity?: boolean;
  section_ar?: string | null;
}

export interface Catalog {
  sections: { id: number; name: string; count: number }[];
  chapters: { hs2: string; section: number; name: string; count: number }[];
  products: Required<Product>[];
}

export function useCatalog() {
  return useQuery({
    queryKey: ['mi-catalog'],
    queryFn: ({ signal }) => api<Catalog | { status: 'pending' }>('market-intel/catalog', {}, signal),
    refetchInterval: (query) => ((query.state.data as { status?: string } | undefined)?.status === 'pending' ? 2500 : false),
    staleTime: 60 * 60_000,
  });
}

export interface Candidate {
  iso: string;
  name: string;
  name_ar: string;
  value: number;
  share: number | null;
  cagr: number | null;
  unit_value: number | null;
  price_vs_median: number | null;
  uv_series: Record<string, number>;
  oman_value: number;
  oman_unit_value: number | null;
  fta: string | null;
  item_price?: number | null;
  oman_item_price?: number | null;
  tariff: { tariff: number; year: number } | null;
  components: Record<Component, number | null>;
  score: number | null;
  risk: { lpi: number | null; stability: number | null; inform: number | null };
}

export interface Summary {
  mode: Mode;
  year: number;
  market_value: number;
  hhi_market: number;
  oman_value: number;
  hhi_oman: number | null;
  oman_top_partner: string | null;
  oman_unit_value: number | null;
  median_unit_value: number | null;
  p25_unit_value: number | null;
  saving_estimate: number | null;
}

export interface Signal {
  level: 'risk' | 'opportunity' | 'info';
  title: string;
  detail: string;
  weight: number;
}

export interface CommodityPrice {
  key: string;
  name_ar: string;
  unit: string | null;
  period: string;
  value: number;
  change_12m: number | null;
  series: [string, number][];
}

export interface UnitRow {
  value: number;
  qty: number;
  unit_code: number;
  net_kg: number;
  estimated: boolean;
  unit_price: number | null;
}

export interface UnitPrices {
  year: number;
  unit_code: number;
  unit: string | null;
  oman_total: UnitRow | null;
  oman_by_partner: (UnitRow & { iso: string; name_ar: string; kg_per_unit: number | null })[];
  world_median_unit_price: number | null;
  world_p25_unit_price: number | null;
  hs6: (UnitRow & { code: string; text: string })[];
}

export interface MarketplaceOffer {
  title: string;
  price: number;
  currency: string;
  omr: number;
  platform: string;
  group: string;
  link: string | null;
  thumbnail: string | null;
  rating: number | null;
  reviews: number | null;
}

export interface MarketplaceResult {
  offers: MarketplaceOffer[];
  groups: { group: string; label: string; count: number; min: number; median: number; max: number; platforms: string[] }[];
  errors: string[];
  fetched_at: string;
}

export function useMarketplaces(q: string, enabled: boolean) {
  return useQuery({
    queryKey: ['mi-markets', q],
    queryFn: ({ signal }) => api<MarketplaceResult | Pending>('market-intel/marketplaces', { q }, signal),
    enabled: enabled && q.length >= 2,
    refetchInterval: (query) => poll(query.state.data),
    staleTime: 30 * 60_000,
    retry: false,
  });
}

export interface Analysis {
  signals: Signal[];
  oman_trend: Record<string, { value: number; qty: number | null }>;
  world_trend: Record<string, { value: number; qty: number | null; unit_value: number | null }>;
  rca: number | null;
  pci: number | null;
  tariffs_oman: { agreement: string; tariff: number; year: number }[];
  commodity: CommodityPrice[];
  unit_prices: UnitPrices | null;
  generated_at: string;
  candidates: Candidate[];
  summary: Summary;
  product: Product;
  weights: Record<Component, number>;
  source: { name: string; url: string; note: string };
}

export interface Brief {
  data: { source: 'ai' | 'rules'; summary: string; recommendations: string[]; risks: string[]; next_steps: string[]; model: string | null };
  ai_enabled: boolean;
}

type Pending = { status: 'pending' };
const isPending = (d: unknown): d is Pending => (d as Pending | undefined)?.status === 'pending';
const poll = (d: unknown) => (isPending(d) ? 2500 : false);

/** الخادم يردّ 202 «قيد الإعداد» حتى يجهز العامل (الإنترنت محصور في عامل الطابور)، فنعيد السؤال كل ثانيتين ونصف. */
export function useProductSearch(q: string) {
  return useQuery({
    queryKey: ['mi-products', q],
    queryFn: ({ signal }) => api<{ data: Product[] } | Pending>('market-intel/products', { q }, signal),
    enabled: q.trim().length >= 2,
    refetchInterval: (query) => poll(query.state.data),
    staleTime: 60_000,
  });
}

export function useAnalysis(product: number | null, mode: Mode) {
  return useQuery({
    queryKey: ['mi-analysis', product, mode],
    queryFn: ({ signal }) => api<Analysis | Pending>('market-intel/analysis', { product, mode }, signal),
    enabled: product !== null,
    refetchInterval: (query) => poll(query.state.data),
    staleTime: 10 * 60_000,
  });
}

export function useBrief(product: number | null, mode: Mode, ready: boolean) {
  return useQuery({
    queryKey: ['mi-brief', product, mode],
    queryFn: ({ signal }) => api<Brief | Pending>('market-intel/brief', { product, mode }, signal),
    enabled: product !== null && ready,
    refetchInterval: (query) => poll(query.state.data),
    staleTime: 10 * 60_000,
  });
}

export function useAsk(product: number | null, mode: Mode) {
  return useMutation({
    mutationFn: async (question: string): Promise<string> => {
      const { id } = await apiSend<{ id: string }>('POST', 'market-intel/ask', { product, mode, question });
      for (let i = 0; i < 40; i++) {
        await new Promise((r) => setTimeout(r, 2000));
        const res = await api<{ answer?: string } | Pending>('market-intel/answer', { id });
        if (!isPending(res) && res.answer) return res.answer;
      }
      throw new Error('استغرقت الإجابة وقتًا طويلًا؛ حاول مجددًا.');
    },
  });
}

export { isPending };

export interface Capabilities {
  ai: boolean;
  shopping: boolean;
  sources: { key: string; label: string; free: boolean; enabled: boolean }[];
  countries: { code: string; name_ar: string; currency: string }[];
}

export function useCapabilities() {
  return useQuery({ queryKey: ['mi-capabilities'], queryFn: ({ signal }) => api<Capabilities>('market-intel/capabilities', {}, signal), staleTime: 5 * 60_000 });
}

export interface Offer {
  title: string;
  store: string;
  price: number;
  currency: string;
  link: string | null;
  thumbnail: string | null;
  rating: number | null;
  reviews: number | null;
  delivery: string | null;
  country: string;
  country_ar: string;
  omr: number;
  source: string;
}

export interface PriceResult {
  sources: { key: string; label: string; count: number }[];
  offers: Offer[];
  by_country: { country: string; country_ar: string; count: number; min: number; median: number; max: number }[];
  summary: { count: number; median: number | null; min: number | null; max: number | null; best: Offer | null; oman_median: number | null; oman_vs_best_country_pct: number | null };
  errors: string[];
  fetched_at: string;
}

export interface SubscriptionOffer {
  country: string;
  country_ar: string;
  plan: string;
  price: number;
  currency: string;
  period: 'month' | 'year' | 'one_time';
  omr: number | null;
  omr_monthly: number | null;
  url: string;
  as_of: string | null;
  notes: string | null;
}

export interface SubscriptionResult {
  offers: SubscriptionOffer[];
  note: string | null;
  fetched_at: string;
}

export function usePrices(q: string, countries: string[], enabled: boolean) {
  return useQuery({
    queryKey: ['mi-prices', q, countries.join(',')],
    queryFn: ({ signal }) => api<PriceResult | Pending>('market-intel/prices', { q, countries: countries.join(',') }, signal),
    enabled: enabled && q.length >= 2 && countries.length > 0,
    refetchInterval: (query) => poll(query.state.data),
    staleTime: 30 * 60_000,
    retry: false,
  });
}

export function useSubscriptions(service: string, countries: string[], enabled: boolean) {
  return useQuery({
    queryKey: ['mi-subs', service, countries.join(',')],
    queryFn: ({ signal }) => api<SubscriptionResult | Pending>('market-intel/subscriptions', { service, countries: countries.join(',') }, signal),
    enabled: enabled && service.length >= 2 && countries.length > 0,
    refetchInterval: (query) => poll(query.state.data),
    staleTime: 6 * 60 * 60_000,
    retry: false,
  });
}

export function useResolve(text: string, enabled: boolean) {
  return useQuery({
    queryKey: ['mi-resolve', text],
    queryFn: ({ signal }) => api<{ data: Product[] } | Pending>('market-intel/resolve', { text }, signal),
    enabled: enabled && text.length >= 2,
    refetchInterval: (query) => poll(query.state.data),
    staleTime: 60 * 60_000,
    retry: false,
  });
}
