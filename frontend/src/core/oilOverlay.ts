import { useMemo } from 'react';
import { useSeries } from './hooks';
import { annualMean } from './stats';
import type { Point } from './api';

export interface OilOverlay {
  label: string;
  monthly: Point[];
  annual: Point[];
}

/** سعر خام دبي الشهري من صندوق النقد (مقياس قريب من الخام العُماني، ومصدر مستقل عن المركز الوطني للإحصاء). */
export const OIL_INDICATOR = 'imf.pcps.poildub';

export function useOilOverlay(enabled: boolean): OilOverlay | null {
  const q = useSeries(OIL_INDICATOR, enabled ? ['WLD'] : [], { from: '1990' });
  const monthly = q.data?.series[0]?.points;
  return useMemo(() => (enabled && monthly && monthly.length > 0 ? { label: 'سعر النفط: خام دبي ($/برميل، المحور الأيمن)', monthly, annual: annualMean(monthly) } : null), [enabled, monthly]);
}

/** يطابق سعر النفط بفترة المخطط: سنوي (متوسط السنة)، فصلي (متوسط الفصل)، أو شهري. */
export function oilAt(overlay: OilOverlay, period: string): number | null {
  if (/^\d{4}$/.test(period)) {
    return overlay.annual.find((p) => p[0] === period)?.[1] ?? null;
  }
  const q = period.match(/^(\d{4})-Q([1-4])$/);
  if (q) {
    const start = (Number(q[2]) - 1) * 3;
    const months = overlay.monthly.filter((p) => p[0].slice(0, 4) === q[1] && Number(p[0].slice(5, 7)) > start && Number(p[0].slice(5, 7)) <= start + 3);
    return months.length >= 2 ? months.reduce((a, p) => a + p[1], 0) / months.length : null;
  }
  const month = period.slice(0, 7);
  return overlay.monthly.find((p) => p[0] === month)?.[1] ?? null;
}
