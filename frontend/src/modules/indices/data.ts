/// <reference types="vite/client" />
import raw from './data/indices.json';
import type { IndexGroup, IndexInfo, RankingRow } from './types';

export const INDEX_GROUPS = raw.groups as IndexGroup[];
export const INDICES = raw.indices as unknown as IndexInfo[];

const loaders = import.meta.glob('./data/rankings/*.json', { import: 'default' }) as Record<string, () => Promise<RankingRow[]>>;

export async function loadRanking(slug: string): Promise<RankingRow[]> {
  const load = loaders[`./data/rankings/${slug}.json`];
  return load ? load() : [];
}
