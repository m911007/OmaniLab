import { useMemo, useState } from 'react';
import { useCrosscheckDetail, useCrosschecks, useEntities, useLines, type LineSpec, type SeriesItem } from '../hooks';
import { entityName, formatPeriod, formatValue, yearOf } from '../format';
import { ChartCard, type ViewOption } from './ChartCard';
import { ColumnChartView } from './ColumnChartView';
import { LineChartView } from './LineChartView';
import { Empty, ErrorState, Loading } from './States';
import { SourcesPanel } from './SourcesPanel';

interface Props {
  title: string;
  subtitle?: string;
  lines: readonly LineSpec[];
  kind?: 'line' | 'columns' | 'stacked';
  from?: string;
  to?: string;
  /** توقعات IMF: الخط المتقطع يبدأ من سنة إصدار WEO */
  imfForecast?: boolean;
  /** أول سنة تُعدّ توقعًا لمصدر غير IMF (مثل التوقعات السكانية) */
  forecastFromYear?: number;
  note?: string;
  callout?: string;
  height?: number;
  /** آخر N فترة للأعمدة */
  last?: number;
}

/** مخطط معرَّف بالتكوين: يجلب خطوطه ويرسمها مع مصدرها وحداثتها وجدولها البديل. هكذا تُضاف الرسوم دون كود رسم جديد. */
export function SeriesChart({ title, subtitle, lines, kind = 'line', from, to, imfForecast, forecastFromYear, note, callout, height, last }: Props) {
  const range = useMemo(() => ({ from, to }), [from, to]);
  const omanLines = useMemo(() => lines.filter((l) => l.entity === 'OMN'), [lines]);
  // الرسم الذي فيه عُمان ودول أخرى يبدأ بعُمان وحدها، ويتيح «مقارنة» بالدول (الجاهزة وما يضيفه القارئ) و«جدول».
  const comparable = omanLines.length > 0 && lines.some((l) => l.entity !== 'OMN');
  const [view, setView] = useState<string>(comparable ? 'oman' : 'chart');
  const [extra, setExtra] = useState<string[]>([]);
  // مصادر بديلة للمؤشر نفسه (إن نشره أكثر من مصدر): تُقارَن وتُعرض، ويبدّل القارئ بينها.
  const baseIndicator = lines[0].indicator;
  const singleIndicator = lines.every((l) => l.indicator === baseIndicator);
  const xcheck = useCrosschecks();
  const group = singleIndicator && omanLines.length > 0 ? xcheck.data?.find((g) => g.indicators.includes(baseIndicator)) : undefined;
  const [panelOpen, setPanelOpen] = useState(false);
  const [alt, setAlt] = useState<string | null>(null);
  const detailQuery = useCrosscheckDetail(baseIndicator, 'OMN', !!group && (panelOpen || alt !== null));

  const compareLines = useMemo<LineSpec[]>(() => [...lines, ...extra.filter((c) => !lines.some((l) => l.entity === c)).map((entity) => ({ indicator: lines[0].indicator, entity }))], [lines, extra]);
  const activeLines = !comparable ? lines : view === 'oman' ? omanLines : compareLines;
  const primary = useLines(activeLines, range);
  const altSource = alt && alt !== baseIndicator && view !== 'compare' && view !== 'table' ? detailQuery.data?.sources.find((x) => x.indicator === alt) : undefined;
  const { indicators } = primary;
  const series = useMemo<SeriesItem[]>(() => {
    if (!altSource) return primary.series;
    const from0 = from ?? '0000';
    return [{ id: altSource.indicator, label: primary.series[0]?.label ?? 'عُمان', color: primary.series[0]?.color ?? 'var(--series-1)', points: altSource.points.filter((p) => p[0] >= from0 && (!to || p[0] <= to)) }];
  }, [altSource, primary.series, from, to]);
  const datasets = altSource ? [altSource.dataset] : primary.datasets;
  // الجدول يحمل كل الخطوط، لذا يُجلب ما يلزمه عند فتحه.
  const tableSource = useLines(comparable && view === 'table' ? compareLines : [], range);
  const tableSeries = comparable ? (view === 'table' ? tableSource.series : series) : series;

  const unit = indicators[0]?.unit ?? null;
  const forecastFrom = useMemo(() => {
    if (forecastFromYear) return forecastFromYear;
    if (!imfForecast) return undefined;
    const imf = datasets.find((d) => d.key === 'imf.weo');
    const m = imf?.freshness.source_version?.match(/(\d{4})\)?\s*$/);
    return m ? Number(m[1]) : undefined;
  }, [imfForecast, forecastFromYear, datasets]);

  const table = useMemo(() => {
    if (tableSeries.length === 0) return undefined;
    const periods = [...new Set(tableSeries.flatMap((s) => s.points.map((p) => p[0])))].sort().reverse();
    return {
      columns: ['الفترة', ...tableSeries.map((s) => s.label)],
      rows: periods.map((p) => [formatPeriod(p), ...tableSeries.map((s) => { const v = s.points.find((x) => x[0] === p)?.[1]; return v === undefined ? '—' : formatValue(v, unit); })]),
    };
  }, [tableSeries, unit]);

  const fullSubtitle = [subtitle, unit].filter(Boolean).join(' — ');
  const views: ViewOption[] | undefined = comparable
    ? [
        { value: 'oman', label: 'عُمان' },
        { value: 'compare', label: 'مقارنة مع دول أخرى' },
        { value: 'table', label: 'جدول' },
      ]
    : undefined;

  return (
    <ChartCard
      title={title}
      subtitle={fullSubtitle}
      datasets={datasets}
      table={table}
      note={note}
      callout={callout}
      views={views}
      defaultView={comparable ? 'oman' : 'chart'}
      onViewChange={setView}
      extra={(v) => (comparable && v === 'compare' ? <AddCountries lines={lines} extra={extra} onChange={setExtra} /> : null)}
    >
      {() => (
        <>
          {primary.isLoading ? (
            <Loading height={height ?? 280} />
          ) : primary.error ? (
            <ErrorState error={primary.error} />
          ) : series.length === 0 ? (
            <Empty />
          ) : kind === 'line' ? (
            <LineChartView series={series} unit={unit} forecastFrom={forecastFrom} height={height} />
          ) : (
            <ColumnChartView series={series} unit={unit} stacked={kind === 'stacked'} height={height} last={last} />
          )}
          {group && (
            <SourcesPanel
              group={group}
              detail={detailQuery.data}
              loading={detailQuery.isLoading}
              open={panelOpen}
              onToggle={() => setPanelOpen((o) => !o)}
              shown={alt ?? baseIndicator}
              onPick={(i) => setAlt(i === baseIndicator ? null : i)}
              canSwitch={view !== 'compare'}
            />
          )}
        </>
      )}
    </ChartCard>
  );
}

/** إضافة دول إلى المقارنة (فوق الدول الجاهزة): قائمة بحث واحدة وشرائح للإزالة. */
function AddCountries({ lines, extra, onChange }: { lines: readonly LineSpec[]; extra: string[]; onChange: (codes: string[]) => void }) {
  const { data: countries } = useEntities('country', lines[0].indicator);
  const taken = new Set([...lines.map((l) => l.entity), ...extra]);
  const name = (code: string) => countries?.find((c) => c.code === code);
  return (
    <div className="add-countries">
      <label className="field">
        أضف دولة
        <select value="" aria-label="إضافة دولة إلى المقارنة" onChange={(e) => e.target.value && onChange([...extra, e.target.value].slice(0, 6))} disabled={extra.length >= 6}>
          <option value="">اختر…</option>
          {(countries ?? [])
            .filter((c) => !taken.has(c.code))
            .sort((a, b) => entityName(a).localeCompare(entityName(b), 'ar'))
            .map((c) => (
              <option key={c.code} value={c.code}>
                {entityName(c)}
              </option>
            ))}
        </select>
      </label>
      {extra.map((code) => (
        <button key={code} className="chip neutral" onClick={() => onChange(extra.filter((c) => c !== code))} aria-label={`إزالة ${code}`}>
          {name(code) ? entityName(name(code)!) : code} ✕
        </button>
      ))}
    </div>
  );
}

export { entityName, yearOf };
