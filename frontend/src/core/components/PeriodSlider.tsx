import { formatPeriod } from '../format';

/** شريط تمرير للفترات (سنوات) مع زرّي السابق والتالي وعرض الفترة الحالية. */
export function PeriodSlider({ periods, current, onChange }: { periods: string[]; current: string; onChange: (p: string) => void }) {
  const idx = periods.indexOf(current);
  const long = periods.length > 30;
  return (
    <div className="gov-pie-slider">
      <button type="button" className="btn" onClick={() => onChange(periods[Math.max(0, idx - 1)])} disabled={idx <= 0} aria-label="الفترة السابقة">›</button>
      <input type="range" min={0} max={periods.length - 1} value={Math.max(0, idx)} onChange={(e) => onChange(periods[Number(e.target.value)])} aria-label="الفترة" aria-valuetext={formatPeriod(current)} />
      <button type="button" className="btn" onClick={() => onChange(periods[Math.min(periods.length - 1, idx + 1)])} disabled={idx >= periods.length - 1} aria-label="الفترة التالية">‹</button>
      <b className="num gov-pie-year" style={long ? { fontSize: 15 } : undefined}>{formatPeriod(current)}</b>
    </div>
  );
}
