import { useQueries } from '@tanstack/react-query';
import { useMemo } from 'react';
import { api, type Point, type SeriesResponse } from './api';
import { lastActualYear } from './lines';
import { NODES, transformAnnual, type RelEdge } from './relations';
import { annualMean, impact, type Impact } from './stats';

const FROM = '2000';

/** يحوّل أي سلسلة إلى سنوية: الشهرية واليومية متوسط السنة (سنوات مكتملة تقريبًا)، والسنوية كما هي. */
export function annualOf(points: readonly Point[]): Map<string, number> {
  if (points.some((p) => p[0].length > 4)) return new Map(annualMean(points));
  return new Map(points);
}

/** آخر تغيّر سنوي متاح للمحرّك بحسب تحويله، من أحدث نقطة مقارنةً بما قبلها بسنة (يلتقط الشهور الأخيرة قبل اكتمال السنة). */
export function recentMove(points: readonly Point[], transform: 'pct' | 'diff' | 'level'): { value: number; period: string; base?: string } | null {
  if (points.length < 2) return null;
  const sorted = [...points].sort((a, b) => a[0].localeCompare(b[0]));
  const [period, v] = sorted[sorted.length - 1];
  if (transform === 'level') return { value: v, period };
  let base: Point | undefined;
  if (period.length === 4) base = sorted[sorted.length - 2];
  else {
    const target = `${Number(period.slice(0, 4)) - 1}${period.slice(4)}`;
    for (let i = sorted.length - 1; i >= 0; i--) {
      if (sorted[i][0] <= target) {
        base = sorted[i];
        break;
      }
    }
  }
  if (!base) return null;
  const value = transform === 'diff' ? v - base[1] : base[1] !== 0 ? (v / base[1] - 1) * 100 : null;
  return value == null ? null : { value, period, base: base[0] };
}

export interface EdgeStats {
  edge: RelEdge;
  effect: Impact | null;
  /** التغيّر الأخير في المحرّك وما تعنيه العلاقة التاريخية للأثر */
  watch: { move: number; period: string; base?: string; expected: number; usual: number } | null;
  /** العلاقة دالة إحصائيًا وفي الاتجاه المتوقع اقتصاديًا */
  usable: boolean;
  /** دالة لكن عكس الاتجاه المتوقع: تُعرض مع تنبيه */
  surprising: boolean;
}

/** يجلب سلاسل العقد في العلاقات المطلوبة ويحسب لكل علاقة: الارتباط ومدى التأثير والدلالة، وما يرجّحه آخر تغيّر في المحرّك. */
export function useRelationStats(edges: readonly RelEdge[]): { stats: EdgeStats[]; isLoading: boolean } {
  const keys = useMemo(() => [...new Set(edges.flatMap((e) => [e.from, e.to]))], [edges]);
  const results = useQueries({
    queries: keys.map((k) => {
      const entity = NODES[k]?.entity ?? 'OMN';
      return {
        queryKey: ['series', k, entity, FROM, undefined],
        queryFn: ({ signal }: { signal: AbortSignal }) => api<SeriesResponse>('observations', { indicator: k, entities: entity, from: FROM }, signal),
        staleTime: 5 * 60_000,
      };
    }),
  });
  const isLoading = results.some((r) => r.isLoading);
  const dataStamp = results.map((r) => r.dataUpdatedAt).join(',');

  const stats = useMemo(() => {
    const last = Number(lastActualYear());
    const years = Array.from({ length: last - Number(FROM) + 1 }, (_, i) => String(Number(FROM) + i));
    // التوقعات (بعد آخر سنة فعلية) لا تدخل الحساب ولا «آخر تغيّر»
    const points = new Map(keys.map((k, i) => [k, (results[i].data?.series[0]?.points ?? []).filter((p) => Number(p[0].slice(0, 4)) <= last)]));
    const series = new Map(keys.map((k) => [k, transformAnnual(annualOf(points.get(k)!), NODES[k].transform)]));
    return edges.map((edge): EdgeStats => {
      const effect = impact(series.get(edge.from)!, series.get(edge.to)!, years, edge.lag ?? 0);
      const sameSign = effect ? Math.sign(effect.slope) === edge.expect : false;
      const usable = Boolean(effect?.significant && sameSign);
      const move = recentMove(points.get(edge.from)!, NODES[edge.from].transform);
      const watch = effect && move ? { move: move.value, period: move.period, base: move.base, expected: effect.intercept + effect.slope * move.value, usual: effect.my } : null;
      return { edge, effect, watch, usable, surprising: Boolean(effect?.significant && !sameSign) };
    });
    // dataStamp يكفي لإعادة الحساب عند وصول البيانات
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [edges, keys, dataStamp]);

  return { stats, isLoading };
}
