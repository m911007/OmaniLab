import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber, shortPeriod } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';

export const ITEMS: [string, string][] = [
  ['FOOD_BEV', 'غذاء ومشروبات'], ['MEAT', 'لحوم'], ['FISH', 'أسماك'], ['DAIRY', 'ألبان وبيض'], ['VEG', 'خضروات'], ['FRUIT', 'فواكه'], ['BREAD', 'خبز وحبوب'], ['OILS', 'زيوت ودهون'], ['SUGAR', 'سكر وحلويات'],
  ['HOUSING', 'سكن ومرافق'], ['TRANSPORT', 'نقل'], ['EDU', 'تعليم'], ['HEALTH', 'صحة'], ['COMM', 'اتصالات'], ['RESTAURANT', 'مطاعم وفنادق'], ['CLOTHING', 'ملابس وأحذية'], ['FURNISH', 'أثاث وتجهيزات'], ['RECREATION', 'ثقافة وترفيه'],
];
const LINES: LineSpec[] = [...ITEMS.map(([c, label]) => ({ indicator: `ncsi.CPIG_${c}_INFL`, entity: 'OMN', label })), { indicator: 'ncsi.CPI_INFLATION', entity: 'OMN', label: 'التضخم العام' }];

/** لون خانة التضخم: أخضر للانخفاض، محايد حتى 3%، كهرماني حتى 6%، أحمر فوقها (قراءة سريعة لا هدف رسمي). */
export function inflationTint(v: number): string {
  if (v <= 0) return `rgba(39, 150, 121, ${0.15 + Math.min(1, Math.abs(v) / 6) * 0.45})`;
  if (v < 3) return 'rgba(58, 134, 255, 0.12)';
  if (v < 6) return `rgba(214, 158, 63, ${0.25 + ((v - 3) / 3) * 0.2})`;
  return `rgba(196, 68, 65, ${Math.min(0.75, 0.45 + ((v - 6) / 10) * 0.3)})`;
}

/** أي البنود ترتفع أسعارها أسرع؟ جدول حراري لآخر 12 شهرًا مرتبًا بآخر شهر، مع التضخم العام صفًّا مرجعيًا. */
export function PriceItemsHeat() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: String(new Date().getFullYear() - 2) });
  const { periods, rows } = useMemo(() => {
    const all = series.find((s) => s.id.startsWith('ncsi.CPI_INFLATION|'));
    const periods = (all?.points ?? []).map((p) => p[0]).sort().slice(-12);
    const rows = series.filter((s) => !s.id.startsWith('ncsi.CPI_INFLATION|')).map((s) => ({ label: s.label, m: new Map(s.points) }))
      .sort((a, b) => (b.m.get(periods[periods.length - 1]) ?? -99) - (a.m.get(periods[periods.length - 1]) ?? -99));
    return { periods, rows: [{ label: 'التضخم العام', m: new Map(all?.points ?? []), general: true }, ...rows.map((r) => ({ ...r, general: false }))] };
  }, [series]);
  return (
    <ChartCard wide title="أسرع البنود ارتفاعًا" subtitle="التضخم السنوي % لآخر 12 شهرًا — المركز الوطني للإحصاء (مرتبة بآخر شهر)" datasets={datasets}
      note="الأخضر انخفاض في الأسعار، والأزرق تضخم هادئ دون 3%، والكهرماني بين 3 و6%، والأحمر فوق 6%. حدود الألوان للقراءة السريعة لا أهداف رسمية.">
      {() => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : (
        <div className="heat-scroll">
          <table className="heat-table growth-heat" aria-label="التضخم حسب البند">
            <thead><tr><th />{[...periods].reverse().map((p) => <th key={p} className="num">{shortPeriod(p)}</th>)}</tr></thead>
            <tbody>
              {rows.map((r) => (
                <tr key={r.label} className={r.general ? 'ga-target' : undefined}>
                  <th scope="row">{r.label}</th>
                  {[...periods].reverse().map((p) => { const v = r.m.get(p); return <td key={p} className="plain num" style={v === undefined ? undefined : { background: inflationTint(v) }}>{v === undefined ? '—' : formatNumber(v, 1)}</td>; })}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </ChartCard>
  );
}
