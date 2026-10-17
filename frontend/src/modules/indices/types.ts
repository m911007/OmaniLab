export interface GccRow {
  code: string;
  rank: number;
  score: number | null;
}

export interface Pillar {
  name: string;
  score: number | string | null;
  note?: string;
}

export interface IndexInfo {
  group: string;
  slug: string;
  ar: string;
  en: string;
  org: string;
  year: number;
  rank: number | null;
  total: number | null;
  score: string | null;
  scaleMax: string | null;
  trend: string | null;
  trendDir: 'up' | 'down' | null;
  note: string;
  badge?: string;
  metric?: string;
  url: string;
  methodology: string;
  pillars: Pillar[] | null;
  rankingYear: number | null;
  rankingNote: string | null;
  rankingCount: number;
  gcc: GccRow[];
  omanRank: number | null;
  omanScore: number | null;
}

export interface IndexGroup {
  id: string;
  title: string;
  sub: string;
}

export interface RankingRow {
  rank: number;
  country: string;
  countryAr: string;
  score: number | null;
}

export type Tier = 'top' | 'mid' | 'low' | 'none';

/** ترتيب عُمان المعتمد: المُعلَن رسميًا، وإلا المستخرج من جدول الترتيب. */
export function omanRankOf(i: IndexInfo): number | null {
  return i.rank ?? i.omanRank;
}

export function totalOf(i: IndexInfo): number | null {
  return i.total ?? (i.rankingCount >= 50 ? i.rankingCount : null);
}

/** موقع عُمان كنسبة من الدول التي تتفوق عليها (100 = الأول عالميًا). */
export function standingPct(i: IndexInfo): number | null {
  const r = omanRankOf(i);
  const t = totalOf(i);
  if (r == null || t == null || t < 2) {
    return null;
  }
  return Math.round(((t - r) / (t - 1)) * 100);
}

export function tierOf(i: IndexInfo): Tier {
  const p = standingPct(i);
  if (p == null) {
    return 'none';
  }
  return p >= 70 ? 'top' : p >= 35 ? 'mid' : 'low';
}

export const TIER_META: Record<Tier, { label: string; chip: 'good' | 'warning' | 'critical' | 'neutral'; icon: string }> = {
  top: { label: 'ضمن الأفضل', chip: 'good', icon: '▲' },
  mid: { label: 'وسط الترتيب', chip: 'warning', icon: '■' },
  low: { label: 'متأخرة', chip: 'critical', icon: '▼' },
  none: { label: 'بلا ترتيب', chip: 'neutral', icon: '–' },
};

export const GCC_NAMES: Record<string, string> = { OMN: 'عُمان', SAU: 'السعودية', ARE: 'الإمارات', QAT: 'قطر', KWT: 'الكويت', BHR: 'البحرين' };

/** خليجيًا: ترتيب عُمان بين الدول الست (1 = الأولى) إن توفرت أرقام الجميع. */
export function gccPosition(i: IndexInfo): { rank: number; of: number } | null {
  const me = i.gcc.find((g) => g.code === 'OMN');
  if (!me || i.gcc.length < 4) {
    return null;
  }
  const rank = i.gcc.filter((g) => g.rank < me.rank).length + 1;
  return { rank, of: i.gcc.length };
}
