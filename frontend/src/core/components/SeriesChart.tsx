import { useMemo } from 'react';
import { useLines, type LineSpec } from '../hooks';
import { entityName, formatPeriod, formatValue, yearOf } from '../format';
import { ChartCard } from './ChartCard';
import { ColumnChartView } from './ColumnChartView';
import { LineChartView } from './LineChartView';
import { Empty, ErrorState, Loading } from './States';

interface Props {
  title: string;
  subtitle?: string;
  lines: readonly LineSpec[];
  kind?: 'line' | 'columns' | 'stacked';
  from?: string;
  to?: string;
  /** توقعات IMF: الخط المتقطع يبدأ من سنة إصدار WEO */
  imfForecast?: boolean;
  note?: string;
  callout?: string;
  height?: number;
  /** آخر N فترة للأعمدة */
  last?: number;
}

/** مخطط معرَّف بالتكوين: يجلب خطوطه ويرسمها مع مصدرها وحداثتها وجدولها البديل. هكذا تُضاف الرسوم دون كود رسم جديد. */
export function SeriesChart({ title, subtitle, lines, kind = 'line', from, to, imfForecast, note, callout, height, last }: Props) {
  const range = useMemo(() => ({ from, to }), [from, to]);
  const { isLoading, error, series, datasets, indicators } = useLines(lines, range);

  const unit = indicators[0]?.unit ?? null;
  const forecastFrom = useMemo(() => {
    if (!imfForecast) return undefined;
    const imf = datasets.find((d) => d.key === 'imf.weo');
    const m = imf?.freshness.source_version?.match(/(\d{4})\)?\s*$/);
    return m ? Number(m[1]) : undefined;
  }, [imfForecast, datasets]);

  const table = useMemo(() => {
    if (series.length === 0) return undefined;
    const periods = [...new Set(series.flatMap((s) => s.points.map((p) => p[0])))].sort().reverse();
    return {
      columns: ['الفترة', ...series.map((s) => s.label)],
      rows: periods.map((p) => [formatPeriod(p), ...series.map((s) => { const v = s.points.find((x) => x[0] === p)?.[1]; return v === undefined ? '—' : formatValue(v, unit); })]),
    };
  }, [series, unit]);

  const fullSubtitle = [subtitle, unit].filter(Boolean).join(' — ');

  return (
    <ChartCard title={title} subtitle={fullSubtitle} datasets={datasets} table={table} note={note} callout={callout}>
      {isLoading ? (
        <Loading height={height ?? 280} />
      ) : error ? (
        <ErrorState error={error} />
      ) : series.length === 0 ? (
        <Empty />
      ) : kind === 'line' ? (
        <LineChartView series={series} unit={unit} forecastFrom={forecastFrom} height={height} />
      ) : (
        <ColumnChartView series={series} unit={unit} stacked={kind === 'stacked'} height={height} last={last} />
      )}
    </ChartCard>
  );
}

export { entityName, yearOf };
