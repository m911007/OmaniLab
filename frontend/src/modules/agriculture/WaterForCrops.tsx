import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';

const OMN = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });
const LINES: LineSpec[] = [
  OMN('wb.ER.H2O.FWTL.K3'), OMN('wb.ER.H2O.FWAG.ZS'),
  OMN('mafwr.crop.total.prod'), OMN('mafwr.crop.total.area'), OMN('mafwr.crop.fodder.area'), OMN('mafwr.crop.fodder.prod'),
];

export interface WaterRow {
  year: string;
  agriWater: number;
  perTonne: number;
  perFeddan: number;
  fodderAreaShare: number | null;
}

/**
 * المياه التي تستهلكها الزراعة لإنتاج المحاصيل: سحب المياه للزراعة (إجمالي السحب × حصة الزراعة) ÷ الإنتاج المحصولي وعلى المساحة المحصولية.
 * متوسطات لكل الإنتاج لا لمحصول بعينه، لسنوات يتوفر فيها المصدران معًا.
 */
export function waterForCrops(withdrawalBn: Map<string, number>, agriShare: Map<string, number>, prod: Map<string, number>, area: Map<string, number>, fodderArea: Map<string, number>): WaterRow[] {
  const out: WaterRow[] = [];
  for (const [year, bn] of [...withdrawalBn].sort((a, b) => a[0].localeCompare(b[0]))) {
    const share = agriShare.get(year);
    const p = prod.get(year);
    const a = area.get(year);
    if (share === undefined || !p || !a) continue;
    const agriWater = bn * 1e9 * (share / 100);
    const fa = fodderArea.get(year);
    out.push({ year, agriWater, perTonne: agriWater / p, perFeddan: agriWater / a, fodderAreaShare: fa !== undefined ? (fa / a) * 100 : null });
  }
  return out;
}

export function WaterForCrops() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2010' });
  const rows = useMemo(() => {
    const get = (key: string) => new Map(series.find((s) => s.id.startsWith(key))?.points ?? []);
    return waterForCrops(get('wb.ER.H2O.FWTL.K3'), get('wb.ER.H2O.FWAG.ZS'), get('mafwr.crop.total.prod'), get('mafwr.crop.total.area'), get('mafwr.crop.fodder.area'));
  }, [series]);
  const last = rows[rows.length - 1];

  const table = rows.length ? {
    columns: ['السنة', 'مياه الزراعة (مليون م³)', 'م³ لكل طن', 'م³ لكل فدان', 'حصة الأعلاف من المساحة'],
    rows: [...rows].reverse().map((r) => [r.year, formatNumber(r.agriWater / 1e6, 0), formatNumber(r.perTonne, 0), formatNumber(r.perFeddan, 0), r.fodderAreaShare == null ? '—' : `${formatNumber(r.fodderAreaShare, 0)}%`]),
  } : undefined;

  return (
    <ChartCard
      wide
      title="كم ماءً تستهلك الزراعة لإنتاج المحاصيل؟"
      subtitle="متوسط وطني: سحب المياه للزراعة ÷ الإنتاج المحصولي والمساحة المحصولية"
      datasets={datasets}
      table={table}
      note="تقدير محسوب من مصدرين: سحب المياه للزراعة من قاعدة AQUASTAT (عبر البنك الدولي)، والإنتاج والمساحة من البيانات المفتوحة لوزارة الثروة الزراعية. هو متوسط لكل المحاصيل لا لمحصول بعينه؛ الأعلاف (حشيشة الرودس والبرسيم) تشغل أكبر مساحة وتحتاج ريًا على مدار السنة، فترفع المتوسط. لا تنشر الوزارة احتياجات الري لكل محصول بعد."
    >
      {isLoading ? <Loading height={200} /> : error ? <ErrorState error={error} /> : !last ? <Empty>لا تتداخل سنوات المصدرين بعد.</Empty> : (
        <div className="water-crops">
          <div className="water-crops-stats">
            <div><span className="muted">مياه الزراعة ({last.year})</span><b className="num">{formatNumber(last.agriWater / 1e6, 0)}</b><small>مليون م³ سنويًا</small></div>
            <div><span className="muted">لكل طن من المحاصيل</span><b className="num">{formatNumber(last.perTonne, 0)}</b><small>م³ للطن</small></div>
            <div><span className="muted">لكل فدان مزروع</span><b className="num">{formatNumber(last.perFeddan, 0)}</b><small>م³ للفدان في السنة</small></div>
            {last.fodderAreaShare != null && <div><span className="muted">حصة الأعلاف من المساحة</span><b className="num">{formatNumber(last.fodderAreaShare, 0)}%</b><small>أكبر مستهلك للمياه</small></div>}
          </div>
        </div>
      )}
    </ChartCard>
  );
}
