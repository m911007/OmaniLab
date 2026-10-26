import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { Empty, Loading } from '@/core/components/States';
import { formatNumber, formatPeriod } from '@/core/format';
import { useDataset, useLatest } from '@/core/hooks';

export interface FoodRow {
  slug: string;
  name: string;
  packs: { kg: number; price: number }[];
  perKg: number | null;
}

/** يجمع مؤشرات الأسعار (mafwr.food.<صنف>.p<عبوة> و.perkg) في صف لكل صنف، مرتّبة من الأغلى كيلوً إلى الأرخص. */
export function foodRows(indicators: { key: string; name_ar: string }[], values: Map<string, number>): FoodRow[] {
  const items = new Map<string, FoodRow>();
  for (const ind of indicators) {
    const m = ind.key.match(/^mafwr\.food\.(.+)\.(p(\d+)|perkg)$/);
    const value = values.get(ind.key);
    if (!m || value === undefined) continue;
    const row = items.get(m[1]) ?? { slug: m[1], name: ind.name_ar.split(':')[0], packs: [], perKg: null };
    if (m[2] === 'perkg') row.perKg = value;
    else row.packs.push({ kg: Number(m[3]), price: value });
    items.set(m[1], row);
  }
  return [...items.values()].map((r) => ({ ...r, packs: r.packs.sort((a, b) => a.kg - b.kg) })).sort((a, b) => (b.perKg ?? 0) - (a.perKg ?? 0));
}

/** أسعار السلع الغذائية الأساسية (الأرز والسكر والعدس) كما تنشرها الوزارة: سعر كل عبوة وكلفة الكيلو. */
export function FoodPricesTable() {
  const dataset = useDataset('mafwr.food_prices');
  const keys = useMemo(() => dataset.data?.indicators?.map((i) => i.key) ?? [], [dataset.data]);
  const latest = useLatest('OMN', keys);
  const values = useMemo(() => new Map((latest.data?.items ?? []).map((i) => [i.indicator.key, i.value])), [latest.data]);
  const period = latest.data?.items[0]?.period;
  const rows = useMemo(() => foodRows(dataset.data?.indicators ?? [], values), [dataset.data, values]);
  const maxPerKg = Math.max(...rows.map((r) => r.perKg ?? 0), 0.0001);

  if (dataset.isLoading || latest.isLoading) return <Loading height={240} />;
  if (rows.length === 0 || !dataset.data) return <Empty>لا أسعار منشورة بعد.</Empty>;

  return (
    <ChartCard
      wide
      title="أسعار الأرز والسكر والعدس"
      subtitle={`ريال عُماني — سعر البيع للمستهلك المنشور من الوزارة لشهر ${period ? formatPeriod(period) : ''}`}
      datasets={[dataset.data]}
      note="الجدول الشهري الذي تنشره الوزارة في البيانات المفتوحة. كلفة الكيلو هي وسيط كلفة الكيلو بين العبوات المنشورة للصنف، فتصلح للمقارنة بين الأصناف والأحجام؛ العبوة الأكبر أرخص للكيلو عادةً."
    >
      <div className="table-scroll">
        <table className="wide food-prices">
          <thead><tr><th>الصنف</th><th className="num">العبوات (كغم ← ريال)</th><th className="num">ريال للكيلو</th></tr></thead>
          <tbody>
            {rows.map((r) => (
              <tr key={r.slug}>
                <th scope="row">{r.name}</th>
                <td className="num">{r.packs.map((p) => <span key={p.kg} className="pack-chip"><bdi>{p.kg}</bdi> كغم ← <b><bdi>{formatNumber(p.price, 2)}</bdi></b></span>)}</td>
                <td className="num">
                  <div className="perkg-bar" style={{ ['--w' as string]: `${((r.perKg ?? 0) / maxPerKg) * 100}%` }}><b><bdi>{r.perKg == null ? '—' : formatNumber(r.perKg, 3)}</bdi></b></div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </ChartCard>
  );
}
