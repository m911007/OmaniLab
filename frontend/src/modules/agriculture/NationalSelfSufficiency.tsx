import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';

const OMN = (indicator: string): LineSpec => ({ indicator, entity: 'OMN' });

const LINES: LineSpec[] = [
  OMN('ncsi.AGR_POULTRY_MEAT'), OMN('ncsi.AGR_RED_MEAT'),
  OMN('fao.fbs.poultry.imp'), OMN('fao.fbs.poultry.exp'),
  OMN('fao.fbs.beef.imp'), OMN('fao.fbs.beef.exp'), OMN('fao.fbs.mutton.imp'), OMN('fao.fbs.mutton.exp'),
];

type Pts = Map<string, number>;

/**
 * الاكتفاء الذاتي = الإنتاج ÷ (الإنتاج + الواردات − الصادرات) لكل سنة تتوفر فيها الأرقام الثلاثة.
 * الإنتاج بالطن من المركز الوطني يُحوَّل إلى ألف طن ليوافق تجارة FAO.
 */
export function selfSufficiency(prodTonnes: Pts, imports: Pts[], exports: Pts[]): [string, number][] {
  const out: [string, number][] = [];
  for (const [year, p] of [...prodTonnes].sort((a, b) => a[0].localeCompare(b[0]))) {
    const imp = imports.map((m) => m.get(year));
    const exp = exports.map((m) => m.get(year));
    if (imp.some((v) => v === undefined) || exp.some((v) => v === undefined)) continue;
    const sum = (vals: (number | undefined)[]) => vals.reduce<number>((a, b) => a + (b ?? 0), 0);
    const prod = p / 1000;
    const supply = prod + sum(imp) - sum(exp);
    if (supply > 0) out.push([year, (prod / supply) * 100]);
  }
  return out;
}

/**
 * الاكتفاء الذاتي من الدواجن واللحوم الحمراء وفق الإنتاج الرسمي: منظمة الأغذية لا تتلقى أرقام إنتاج اللحوم من عُمان فتُسندها
 * (الدواجن 7 آلاف طن ثابتة)، فنقيس بالإنتاج المعلن من المركز الوطني للإحصاء مع واردات وصادرات المنظمة.
 */
export function NationalSelfSufficiency() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2010' });

  const result = useMemo(() => {
    const get = (key: string): Pts => new Map(series.find((s) => s.id.startsWith(key))?.points ?? []);
    const poultry = selfSufficiency(get('ncsi.AGR_POULTRY_MEAT'), [get('fao.fbs.poultry.imp')], [get('fao.fbs.poultry.exp')]);
    const red = selfSufficiency(get('ncsi.AGR_RED_MEAT'), [get('fao.fbs.beef.imp'), get('fao.fbs.mutton.imp')], [get('fao.fbs.beef.exp'), get('fao.fbs.mutton.exp')]);
    const items: SeriesItem[] = [
      { id: 'poultry', label: 'لحوم الدواجن', color: 'var(--series-1)', points: poultry },
      { id: 'red', label: 'اللحوم الحمراء (أبقار وضأن وماعز)', color: 'var(--series-2)', points: red },
    ];
    return items.filter((s) => s.points.length > 0);
  }, [series]);

  const table = result.length > 0 ? {
    columns: ['السنة', ...result.map((s) => s.label)],
    rows: [...new Set(result.flatMap((s) => s.points.map((p) => p[0])))].sort().reverse().map((y) => [y, ...result.map((s) => { const v = s.points.find((p) => p[0] === y)?.[1]; return v === undefined ? '—' : `${formatNumber(v, 1)}%`; })]),
  } : undefined;

  return (
    <ChartCard
      wide
      title="الاكتفاء الذاتي من اللحوم وفق الإنتاج الرسمي"
      subtitle="% — إنتاج المركز الوطني للإحصاء ÷ (الإنتاج + الواردات − الصادرات من FAO)"
      datasets={datasets}
      table={table}
      note="تقدير يجمع مصدرين: الإنتاج الرسمي لعُمان، وتجارة اللحوم من الموازين الغذائية لمنظمة الأغذية والزراعة (دون تغيّر المخزون). نسبة FAO نفسها للحوم لا تُعرض لأنها مبنية على إنتاج أسندته المنظمة لا على أرقام عُمان."
    >
      {isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : result.length === 0 ? <Empty /> : <LineChartView series={result} unit="%" height={300} zeroLine={false} />}
    </ChartCard>
  );
}
