import { useMemo } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useLines, type LineSpec } from '@/core/hooks';
import { COUNTRY_NAMES } from '@/core/groups';
import { GCC_CODES } from '@/core/colors';
import { lastActualYear } from '@/core/lines';

/** مؤشرات البطاقة: القطبية تحدد اللون (الأعلى أفضل أم الأدنى أفضل). */
export interface ScoreRow {
  key: string;
  label: string;
  better: 'high' | 'low';
  digits?: number;
}

export const SCORE_ROWS: ScoreRow[] = [
  { key: 'imf.GGXCNL_NGDP', label: 'رصيد الميزانية (% من الناتج)', better: 'high' },
  { key: 'imf.GGXONLB_G01_GDP_PT', label: 'الرصيد الأولي (% من الناتج)', better: 'high' },
  { key: 'imf.GGR_G01_GDP_PT', label: 'الإيرادات (% من الناتج)', better: 'high' },
  { key: 'imf.G_X_G01_GDP_PT', label: 'المصروفات (% من الناتج)', better: 'low' },
  { key: 'imf.GGXWDG_NGDP', label: 'الدين الحكومي (% من الناتج)', better: 'low' },
  { key: 'imf.GGXWDN_G01_GDP_PT', label: 'صافي الدين (% من الناتج)', better: 'low' },
  { key: 'imf.ie', label: 'الفوائد المدفوعة (% من الناتج)', better: 'low' },
];


/** أخضر للأفضل وأحمر للأسوأ داخل الصف (ترتيب نسبي بين الدول الست). */
export function rankTint(v: number, row: number[], better: 'high' | 'low'): string {
  const lo = Math.min(...row);
  const hi = Math.max(...row);
  const t = hi > lo ? (v - lo) / (hi - lo) : 0.5;
  const goodness = better === 'high' ? t : 1 - t;
  return goodness >= 0.5 ? `rgba(39, 150, 121, ${0.1 + (goodness - 0.5) * 0.9})` : `rgba(196, 68, 65, ${0.1 + (0.5 - goodness) * 0.9})`;
}

/** جدول أداء خليجي عام: دول × مؤشرات في آخر سنة فعلية (أو الأحدث المتاح لكل دولة)، لون كل خانة بحسب ترتيبها بين الدول الست. */
export function ScoreTable({ rows, title, subtitle, note }: { rows: ScoreRow[]; title: string; subtitle: string; note: string }) {
  const year = lastActualYear();
  const lines = useMemo<LineSpec[]>(() => rows.flatMap((r) => GCC_CODES.map((entity) => ({ indicator: r.key, entity }))), [rows]);
  const { isLoading, error, series, datasets } = useLines(lines, { from: String(Number(year) - 6) });
  const grid = useMemo(() => rows.map((r) => ({ ...r, vals: GCC_CODES.map((c) => {
    const pts = (series.find((s) => s.id === `${r.key}|${c}`)?.points ?? []).filter((p) => p[0].slice(0, 4) <= year);
    return pts.length ? pts[pts.length - 1][1] : undefined;
  }) })), [series, year, rows]);
  return (
    <ChartCard wide title={title} subtitle={subtitle} datasets={datasets} note={note}>
      {() => isLoading ? <Loading height={280} /> : error ? <ErrorState error={error} /> : (
        <div className="heat-scroll">
          <table className="heat-table" aria-label={title}>
            <thead><tr><th />{GCC_CODES.map((c) => <th key={c} scope="col">{COUNTRY_NAMES[c]}</th>)}</tr></thead>
            <tbody>
              {grid.map((r) => {
                const present = r.vals.filter((v): v is number => v !== undefined);
                return (
                  <tr key={r.key}>
                    <th scope="row">{r.label}</th>
                    {r.vals.map((v, i) => <td key={GCC_CODES[i]} className="plain num" style={v === undefined ? undefined : { background: rankTint(v, present, r.better) }}>{v === undefined ? '—' : formatNumber(v, r.digits ?? 1)}</td>)}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </ChartCard>
  );
}

/** بطاقة الأداء المالي الخليجية. */
export function GulfScorecard() {
  return <ScoreTable rows={SCORE_ROWS} title={`بطاقة الأداء المالي الخليجية ${lastActualYear()}`} subtitle="آخر سنة فعلية — صندوق النقد (مرصد المالية العامة)" note="الأخضر الأفضل بين الدول الست في كل صف والأحمر الأسوأ (الأعلى أفضل في الرصيد والإيراد، والأدنى أفضل في الدين والمصروف والفوائد). الترتيب نسبي بين الدول لا حكم مطلق." />;
}
