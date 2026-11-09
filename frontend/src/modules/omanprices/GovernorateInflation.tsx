import { useMemo, useState } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { LineChartView } from '@/core/components/LineChartView';
import { Segmented } from '@/core/components/Segmented';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber, formatPeriod, shortPeriod } from '@/core/format';
import { useLines, type LineSpec, type SeriesItem } from '@/core/hooks';
import { MEASURES, lastPoints, measureValue, monthGrid, rankGovernorates, shiftMonths, summarize, type GovSeries, type Measure } from './governorates';

export const REGIONS: [string, string][] = [
  ['MUSCAT', 'مسقط'], ['DHOFAR', 'ظفار'], ['N_BATINAH', 'شمال الباطنة'], ['S_BATINAH', 'جنوب الباطنة'], ['DAKHILIYAH', 'الداخلية'],
  ['N_SHARQIYAH', 'شمال الشرقية'], ['S_SHARQIYAH', 'جنوب الشرقية'], ['DHAHIRAH', 'الظاهرة'], ['BURAIMI', 'البريمي'], ['MUSANDAM', 'مسندم'], ['WUSTA', 'الوسطى'],
];

const NATIONAL = { infl: 'ncsi.CPI_INFLATION', index: 'ncsi.CPI_INDEX' };
const LINES: LineSpec[] = [
  ...REGIONS.flatMap(([c, label]) => [
    { indicator: `ncsi.CPIR_${c}_INFL`, entity: 'OMN', label },
    { indicator: `ncsi.CPIR_${c}_INDEX`, entity: 'OMN', label },
  ]),
  { indicator: NATIONAL.infl, entity: 'OMN', label: 'السلطنة' },
  { indicator: NATIONAL.index, entity: 'OMN', label: 'السلطنة' },
];
const PICK_COLORS = ['var(--series-1)', 'var(--series-2)', 'var(--series-3)'];
const sign = (v: number, d = 1) => `${v > 0 ? '+' : ''}${formatNumber(v, d)}`;

/** لون خانة الجدول: أحمر كلما ارتفع التضخم فوق المتوسط الوطني، وأخضر كلما انخفض عنه. */
function heat(v: number, ref: number, span: number): string {
  const t = Math.min(1, Math.abs(v - ref) / (span || 1));
  return v >= ref ? `rgba(196, 68, 65, ${0.1 + t * 0.5})` : `rgba(39, 150, 121, ${0.1 + t * 0.5})`;
}

/**
 * أي المحافظات تشهد تضخمًا أكبر؟ ترتيب المحافظات بثلاثة مقاييس مقابل المتوسط الوطني، وجدول حراري لآخر 12 شهرًا،
 * ورسم خطي يختار القارئ محافظاته (حتى ثلاث) أو يضغط «الأعلى/الأدنى».
 */
export function GovernorateInflation() {
  const { isLoading, error, series, datasets } = useLines(LINES, { from: '2018' });
  const [measure, setMeasure] = useState<Measure>('latest');
  const [picked, setPicked] = useState<string[] | null>(null);

  const { rows, national } = useMemo(() => {
    const get = (key: string) => series.find((s) => s.id.startsWith(`${key}|`))?.points ?? [];
    const rows: GovSeries[] = REGIONS.map(([code, label]) => ({ code, label, infl: get(`ncsi.CPIR_${code}_INFL`), index: get(`ncsi.CPIR_${code}_INDEX`) }));
    return { rows, national: { infl: get(NATIONAL.infl), index: get(NATIONAL.index) } };
  }, [series]);

  const ranked = useMemo(() => rankGovernorates(rows, measure), [rows, measure]);
  const nat = measureValue(national, measure);
  const sum = summarize(ranked, nat?.value ?? null);
  const max = Math.max(...ranked.map((r) => Math.abs(r.value)), 1e-9);
  const hint = MEASURES.find((m) => m.value === measure)!.hint;

  const grid = useMemo(() => monthGrid(rows, 12), [rows]);
  const gridSpan = useMemo(() => {
    const vals = [...grid.cells.values()].flatMap((m) => grid.periods.map((p) => m.get(p))).filter((v): v is number => v !== undefined);
    const ref = lastPoints(national.infl, 1)[0]?.[1] ?? 0;
    return Math.max(...vals.map((v) => Math.abs(v - ref)), 0.5);
  }, [grid, national.infl]);

  const top3 = rankGovernorates(rows, 'latest').slice(0, 3).map((r) => r.code);
  const bottom3 = rankGovernorates(rows, 'latest').slice(-3).map((r) => r.code);
  const chosen = picked ?? top3;
  const toggle = (code: string) => setPicked((cur) => {
    const c = cur ?? top3;
    return c.includes(code) ? c.filter((x) => x !== code) : [...c, code].slice(-3);
  });
  const lineSeries: SeriesItem[] = useMemo(() => {
    const latest = lastPoints(national.infl, 1)[0]?.[0];
    if (!latest) return [];
    const from = shiftMonths(latest, -36);
    const cut = (pts: [string, number][]) => pts.filter((p) => p[0] >= from);
    return [
      ...chosen.map((code, i) => { const r = rows.find((x) => x.code === code)!; return { id: `gov|${code}`, label: r.label, color: PICK_COLORS[i % 3], points: cut(r.infl) }; }),
    ].filter((s) => s.points.length > 0);
  }, [chosen, rows, national.infl]);

  const table = ranked.length ? {
    columns: ['المحافظة', MEASURES.find((m) => m.value === measure)!.label, 'الفرق عن السلطنة'],
    rows: ranked.map((r) => [r.label, `${formatNumber(r.value, 2)}%`, nat ? `${sign(r.value - nat.value, 2)} نقطة` : '—']),
  } : undefined;

  return (
    <ChartCard
      wide
      title="أي المحافظات تشهد تضخمًا أكبر؟"
      subtitle={`${hint} — المركز الوطني للإحصاء والمعلومات`}
      datasets={datasets}
      table={table}
      actions={<Segmented label="مقياس المقارنة" value={measure} onChange={setMeasure} options={MEASURES.map((m) => ({ value: m.value, label: m.label }))} />}
      note="الأرقام القياسية الإقليمية لأسعار المستهلك (سنة الأساس 2018). «منذ 2018» هو الارتفاع التراكمي للرقم القياسي. اللون الأحمر فوق المتوسط الوطني والأخضر دونه."
    >
      {() => isLoading ? <Loading height={260} /> : error ? <ErrorState error={error} /> : !sum ? <Empty>لا بيانات كافية للمحافظات.</Empty> : (
        <div className="ginf">
          <p className="ginf-summary" role="status">
            الأعلى تضخمًا <b>{sum.top.label}</b> (<span className="num">{sign(sum.top.value)}%</span>)، والأدنى <b>{sum.bottom.label}</b> (<span className="num">{sign(sum.bottom.value)}%</span>)
            {nat && <>، مقابل <span className="num">{sign(nat.value)}%</span> للسلطنة؛ و<b className="num">{sum.aboveNational}</b> من {ranked.length} محافظات فوق المتوسط الوطني</>}.
            {' '}الفارق بين أعلاها وأدناها <span className="num">{formatNumber(sum.spread, 1)}</span> نقطة مئوية ({formatPeriod(sum.top.period)}).
          </p>

          <div className="ranked" role="list" aria-label="ترتيب المحافظات بالتضخم">
            {ranked.map((r, i) => (
              <div key={r.code} role="listitem" className="ranked-row">
                <span className="rk">{i + 1}</span>
                <span>{r.label}</span>
                <span className="bar-track"><span className="bar" style={{ display: 'block', width: `${(Math.abs(r.value) / max) * 100}%`, background: nat && r.value > nat.value ? 'var(--critical)' : 'var(--good)' }} /></span>
                <span className="val num">{sign(r.value)}%</span>
              </div>
            ))}
          </div>

          <h4 className="ga-h">آخر 12 شهرًا (التضخم السنوي %)</h4>
          <div className="heat-scroll">
            <table className="heat-table" aria-label="التضخم الشهري حسب المحافظة">
              <thead><tr><th scope="col">المحافظة</th>{grid.periods.map((p) => <th key={p} scope="col">{shortPeriod(p)}</th>)}</tr></thead>
              <tbody>
                {ranked.map((r) => (
                  <tr key={r.code}>
                    <th scope="row">{r.label}</th>
                    {grid.periods.map((p) => {
                      const v = grid.cells.get(r.code)?.get(p);
                      const ref = new Map(national.infl).get(p);
                      return <td key={p} className="plain num" style={v !== undefined && ref !== undefined ? { background: heat(v, ref, gridSpan) } : undefined}>{v === undefined ? '—' : formatNumber(v, 1)}</td>;
                    })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          <h4 className="ga-h">قارن بين المحافظات خلال 3 سنوات</h4>
          <div className="intel-chips" role="group" aria-label="اختيار المحافظات">
            <button type="button" className="btn" onClick={() => setPicked(top3)}>الأعلى تضخمًا</button>
            <button type="button" className="btn" onClick={() => setPicked(bottom3)}>الأدنى تضخمًا</button>
            {REGIONS.map(([code, label]) => (
              <button key={code} type="button" className={`chip ${chosen.includes(code) ? 'good' : 'neutral'}`} aria-pressed={chosen.includes(code)} onClick={() => toggle(code)}>{label}</button>
            ))}
          </div>
          <p className="muted">يمكن اختيار ثلاث محافظات كحد أقصى.</p>
          <LineChartView series={lineSeries} unit="%" height={300} />
        </div>
      )}
    </ChartCard>
  );
}
