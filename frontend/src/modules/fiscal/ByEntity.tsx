import { useMemo, useState } from 'react';
import { ChartCard, type ViewOption } from '@/core/components/ChartCard';
import { CompositionPie } from '@/core/components/CompositionPie';
import { LineChartView } from '@/core/components/LineChartView';
import { PeriodSlider } from '@/core/components/PeriodSlider';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { entityName, formatNumber } from '@/core/format';
import { useEntities, useLines, type LineSpec, type SeriesItem } from '@/core/hooks';

const UNIT = 'مليون ريال عُماني';
const TOP = 10;
const PALETTE = ['var(--series-1)', 'var(--series-2)', 'var(--series-3)', 'var(--series-4)', 'var(--series-5)', 'var(--series-6)', 'var(--series-7)', 'var(--series-8)', 'var(--series-other)', 'var(--text-muted)'];
const VIEWS: ViewOption[] = [
  { value: 'rank', label: 'ترتيب الجهات' },
  { value: 'pie', label: 'دائري حسب السنة' },
  { value: 'lines', label: 'مسار أكبر الجهات' },
  { value: 'table', label: 'جدول' },
];

export interface Ranked {
  code: string;
  label: string;
  value: number;
}

/** الجهات مرتبطة بقيمها في سنة، من الأكبر إلى الأصغر؛ القيم غير الموجبة تُهمل. */
export function rankByYear(series: { id: string; label: string; points: [string, number][] }[], year: string): Ranked[] {
  return series
    .map((s) => ({ code: s.id, label: s.label, value: s.points.find((p) => p[0] === year)?.[1] ?? 0 }))
    .filter((r) => r.value > 0)
    .sort((a, b) => b.value - a.value);
}

/** أكبر الجهات في سنة والباقي مجمَّعًا «جهات أخرى»، بألوان ثابتة بحسب الترتيب. */
export function topWithOthers(ranked: Ranked[], n = TOP): { code: string; label: string; value: number; color: string }[] {
  const head = ranked.slice(0, n).map((r, i) => ({ ...r, color: PALETTE[i % PALETTE.length] }));
  const rest = ranked.slice(n).reduce((a, r) => a + r.value, 0);
  return rest > 0 ? [...head, { code: 'others', label: 'جهات أخرى', value: rest, color: 'var(--text-muted)' }] : head;
}

/** الإيرادات أو المصروفات حسب الجهة الحكومية (الحساب الختامي): ترتيب بسنة تختارها، أو حصص دائرية، أو مسار أكبر الجهات. */
export function ByEntity({ indicator, title, subtitle, note }: { indicator: string; title: string; subtitle: string; note: string }) {
  const ents = useEntities('other', indicator);
  const specs = useMemo<LineSpec[]>(() => (ents.data ?? []).map((e) => ({ indicator, entity: e.code, label: entityName(e) })), [ents.data, indicator]);
  const { isLoading, error, series, datasets } = useLines(specs);
  const years = useMemo(() => [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort(), [series]);
  const [picked, setPicked] = useState<string | null>(null);
  const year = picked && years.includes(picked) ? picked : years[years.length - 1];

  const ranked = useMemo(() => (year ? rankByYear(series, year) : []), [series, year]);
  const shown = useMemo(() => topWithOthers(ranked), [ranked]);
  const total = ranked.reduce((a, r) => a + r.value, 0);
  const pieSeries = useMemo<SeriesItem[]>(() => {
    const out: SeriesItem[] = [];
    for (const y of years) {
      for (const [i, r] of topWithOthers(rankByYear(series, y)).entries()) {
        const id = `${r.code}|x`;
        let s = out.find((x) => x.id === id);
        if (!s) {
          s = { id, label: r.label, color: PALETTE[i % PALETTE.length], points: [] };
          out.push(s);
        }
        s.points.push([y, r.value]);
      }
    }
    return out;
  }, [series, years]);
  const lineSeries = useMemo(() => {
    const top = rankByYear(series, years[years.length - 1] ?? '').slice(0, 6).map((r) => r.code);
    return series.filter((s) => top.includes(s.id)).map((s, i) => ({ ...s, color: PALETTE[i % PALETTE.length] }));
  }, [series, years]);
  const table = ranked.length ? { columns: ['الجهة', `${UNIT} (${year})`, 'الحصة %'], rows: ranked.map((r) => [r.label, formatNumber(r.value, 1), formatNumber((r.value / total) * 100, 1)]) } : undefined;
  const max = Math.max(...shown.map((r) => r.value), 1e-9);

  return (
    <ChartCard wide title={title} subtitle={`${subtitle} — ${UNIT}`} datasets={datasets} table={table} views={VIEWS} defaultView="rank" note={note}>
      {(view) => isLoading || ents.isLoading ? <Loading height={320} /> : error ? <ErrorState error={error} /> : !year ? <Empty /> : view === 'pie' ? (
        <CompositionPie series={pieSeries} categories={pieSeries.map((s) => ({ key: s.id.split('|')[0], label: s.label, color: s.color }))} unit={UNIT} period={picked} onPeriod={setPicked} />
      ) : view === 'lines' ? (
        <LineChartView series={lineSeries} unit={UNIT} height={320} />
      ) : (
        <>
          <PeriodSlider periods={years} current={year} onChange={setPicked} />
          <div className="ranked" role="list" aria-label={title}>
            {shown.map((r, i) => (
              <div key={r.code} role="listitem" className="ranked-row">
                <span className="rk">{i + 1}</span>
                <span>{r.label}</span>
                <span className="bar-track"><span className="bar" style={{ display: 'block', width: `${(r.value / max) * 100}%`, background: r.color }} /></span>
                <span className="val num">{formatNumber(r.value, 1)} <small className="muted">({formatNumber((r.value / total) * 100, 1)}%)</small></span>
              </div>
            ))}
          </div>
        </>
      )}
    </ChartCard>
  );
}
