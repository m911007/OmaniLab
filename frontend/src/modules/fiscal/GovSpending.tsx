import { useMemo, useState } from 'react';
import { ChartCard, type ViewOption } from '@/core/components/ChartCard';
import { CompositionPie, type Category } from '@/core/components/CompositionPie';
import { OilToggle } from '@/core/components/OilToggle';
import { ColumnChartView } from '@/core/components/ColumnChartView';
import { LineChartView } from '@/core/components/LineChartView';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';
import { useOilOverlay } from '@/core/oilOverlay';

/** وجوه الصرف بألوان متدرجة التناغم: من الأزرق البارد (الأمن والإدارة) إلى الدافئ (الدعم). */
export const CATEGORIES: Category[] = [
  { key: 'mof.exp_defense', label: 'الدفاع والأمن', color: 'var(--series-1)' },
  { key: 'mof.exp_civil', label: 'الوزارات المدنية', color: 'var(--series-5)' },
  { key: 'mof.exp_other_current', label: 'خدمة الدين وأخرى', color: 'var(--series-7)' },
  { key: 'mof.exp_investment', label: 'الاستثمار والتنمية', color: 'var(--series-3)' },
  { key: 'mof.exp_contributions', label: 'المساهمات والدعم', color: 'var(--series-2)' },
];
export const REVENUE_CATEGORIES: Category[] = [
  { key: 'mof.rev_oil', label: 'النفط', color: 'var(--series-2)' },
  { key: 'mof.rev_gas', label: 'الغاز', color: 'var(--series-4)' },
  { key: 'mof.rev_other', label: 'إيرادات أخرى', color: 'var(--series-3)' },
];
const viewsFor = (shares: boolean, pie: boolean): ViewOption[] => [
  { value: 'stack', label: 'مكدّس مع الإجمالي' },
  ...(shares ? [{ value: 'share', label: 'حصص % من الإجمالي' }] : []),
  { value: 'lines', label: 'خطوط' },
  ...(pie ? [{ value: 'pie', label: 'دائري حسب السنة' }] : []),
  { value: 'table', label: 'جدول' },
];

/** سلاسل حصص % من مجموع المكوّنات في كل سنة (للمكدّس 100%). */
export function toShares<T extends { points: [string, number][] }>(series: T[]): T[] {
  const totals = new Map<string, number>();
  for (const s of series) for (const [p, v] of s.points) totals.set(p, (totals.get(p) ?? 0) + v);
  return series.map((s) => ({ ...s, points: s.points.filter(([p]) => (totals.get(p) ?? 0) > 0).map(([p, v]) => [p, (v / totals.get(p)!) * 100] as [string, number]) }));
}
const UNIT = 'مليار ريال عُماني';

interface BreakdownProps {
  title: string;
  subtitle: string;
  note: string;
  categories: Category[];
  /** يُحذف الإجمالي حين لا يكون مجموع المكوّنات معنًى (حصص لا تجمع 100) */
  totalLabel?: string;
  /** وحدة العرض (الافتراضي مليار ر.ع) */
  unit?: string;
  defaultView?: 'stack' | 'share' | 'lines' | 'pie';
  /** مؤشرات المصدر الأخرى بدل mof. للتجميع؛ نص إضافي يُلحق بالعنوان الفرعي */
  entity?: string;
  /** زر إخفاء/إظهار سعر النفط (سياق للقراءة) */
  oil?: boolean;
  /** وضع «حصص %» (مكدّس 100%) */
  shares?: boolean;
  /** الدائري لا يصلح لمكوّنات سالبة (مثل التمويل) */
  pie?: boolean;
  /** تنبيه يظهر تحت الدائري */
  pieNote?: string;
  /** مؤشرات المنشأ (ncsi.* مثلًا) بدل mof.*، ومن أي سنة */
  from?: string;
}

/** تفصيل سنوي لمجموع (الإنفاق أو الإيراد): مكدّس مع الإجمالي، أو خطوط، أو دائري بشريط تمرير للسنوات، أو جدول. */
export function Breakdown({ title, subtitle, note, categories, totalLabel, oil: oilAvailable = false, shares = false, pie = true, pieNote, from = '2012', unit = UNIT, defaultView = 'stack', entity = 'OMN' }: BreakdownProps) {
  const lines = useMemo<LineSpec[]>(() => categories.map((c) => ({ indicator: c.key, entity, label: c.label, color: c.color })), [categories]);
  const { isLoading, error, series, datasets } = useLines(lines, { from });
  const [showOil, setShowOil] = useState(true);
  const oil = useOilOverlay(oilAvailable && showOil);
  const years = useMemo(() => [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort(), [series]);
  const [picked, setPicked] = useState<string | null>(null);

  const valueOf = (key: string, y: string) => series.find((x) => x.id.startsWith(`${key}|`))?.points.find((p) => p[0] === y)?.[1];
  const table = years.length ? {
    columns: ['السنة', ...categories.map((c) => c.label), ...(totalLabel ? [totalLabel] : [])],
    rows: [...years].reverse().map((y) => {
      const vals = categories.map((c) => valueOf(c.key, y));
      const present = vals.filter((v): v is number => v !== undefined);
      return [y, ...vals.map((v) => (v === undefined ? '—' : formatNumber(v, 2))), ...(totalLabel ? [present.length ? formatNumber(present.reduce((a, c) => a + c, 0), 2) : '—'] : [])];
    }),
  } : undefined;

  return (
    <ChartCard
      wide
      title={title}
      subtitle={`${subtitle} — ${unit}`}
      datasets={datasets}
      table={table}
      views={viewsFor(shares, pie)}
      defaultView={defaultView}
      actions={oilAvailable ? <OilToggle on={showOil} onChange={setShowOil} /> : undefined}
      note={note}
    >
      {(view) => isLoading ? <Loading height={300} /> : error ? <ErrorState error={error} /> : series.length === 0 ? <Empty /> : view === 'pie' ? (
        <><CompositionPie series={series} categories={categories} unit={unit} period={picked} onPeriod={setPicked} />{pieNote && <p className="muted" style={{ fontSize: 12 }}>{pieNote}</p>}</>
      ) : view === 'share' ? (
        <ColumnChartView series={toShares(series)} unit="%" height={340} stacked last={14} />
      ) : view === 'lines' ? (
        <LineChartView series={series} unit={unit} height={340} oil={oil} />
      ) : (
        <ColumnChartView series={series} unit={unit} height={340} stacked last={14} totalLabel={totalLabel} oil={oil} />
      )}
    </ChartCard>
  );
}

export function GovSpending() {
  return <Breakdown title="أوجه صرف الحكومة (الحساب الختامي)" subtitle="وزارة المالية" shares categories={CATEGORIES} totalLabel="إجمالي الإنفاق العام" note="الحساب الختامي السنوي لوزارة المالية (opendata.gov.om)؛ 2021 غير منشور. «خدمة الدين وأخرى» مشتقة (جملة الجارية − الدفاع − المدنية) وتشمل خدمة الدين وبنودًا أخرى. الإجمالي هو مجموع الوجوه الخمسة." />;
}

export function RevenueSources() {
  return <Breakdown oil shares title="مصادر الدخل" subtitle="وزارة المالية" categories={REVENUE_CATEGORIES} totalLabel="إجمالي الإيرادات" note="الحساب الختامي السنوي لوزارة المالية (opendata.gov.om)؛ 2021 غير منشور. «إيرادات أخرى» = إجمالي الإيرادات − النفط − الغاز، وتشمل الإيرادات الجارية (الضرائب والرسوم وغيرها) والرأسمالية." />;
}

export const FINANCING_CATEGORIES: Category[] = [
  { key: 'mof.fin_external_net', label: 'اقتراض خارجي (صافي)', color: 'var(--series-1)' },
  { key: 'mof.fin_domestic_net', label: 'اقتراض محلي (صافي)', color: 'var(--series-5)' },
  { key: 'mof.fin_reserves', label: 'من الاحتياطيات', color: 'var(--series-3)' },
  { key: 'mof.fin_other', label: 'أخرى (معونات وحسابات حكومية وفائض سابق)', color: 'var(--series-7)' },
];

/** كيف يُموَّل العجز؟ مكوّنات التمويل (الموجب يغطي عجزًا، والسالب سداد أو ادخار) ومجموعها يساوي العجز بإشارة معكوسة. */
export function FinancingMix() {
  return <Breakdown pieNote="الدائري يعرض مصادر التمويل الموجبة فقط (الاقتراض والسحب من الاحتياطيات)؛ السداد والادخار لا يظهران فيه." title="تمويل العجز" subtitle="وزارة المالية" categories={FINANCING_CATEGORIES} totalLabel="إجمالي التمويل (= العجز)" note="وسائل التمويل من الجدول 1 في الحساب الختامي: الموجب يغطي عجزًا والسالب سداد قروض أو ادخار فائض. «أخرى» = إجمالي التمويل − الاقتراض والاحتياطيات (معونات، حركة الحسابات الحكومية، استخدام فائض سابق). 2021 غير منشور." />;
}
