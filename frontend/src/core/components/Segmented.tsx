import type { ReactNode } from 'react';

export interface SegmentedOption<T extends string> {
  value: T;
  label: string;
  /** أيقونة تحلّ محل النص لتوفير المساحة؛ يبقى النص اسمًا وصفيًا للزر (aria-label وتلميح) */
  icon?: ReactNode;
}

export function Segmented<T extends string>({ value, options, onChange, label }: { value: T; options: SegmentedOption<T>[]; onChange: (v: T) => void; label: string }) {
  return (
    <div className="toggle" role="group" aria-label={label}>
      {options.map((o) => (
        <button key={o.value} aria-pressed={o.value === value} aria-label={o.icon ? o.label : undefined} title={o.icon ? o.label : undefined} onClick={() => onChange(o.value)}>
          {o.icon ?? o.label}
        </button>
      ))}
    </div>
  );
}

const svg = (d: string) => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d={d} />
  </svg>
);

/** أيقونتا المخطط والجدول (بدل كلمتين) */
export const CHART_ICON = svg('M3 17l5-6 4 3 8-9M3 21h18');
export const TABLE_ICON = svg('M3 5h18v14H3zM3 10h18M3 15h18M9 5v14');
export const LINES_ICON = svg('M3 8l5 4 4-6 5 8 4-3M3 20h18');
export const STACK_ICON = svg('M5 20V11M12 20V5M19 20V13M3 20h18');
export const PIE_ICON = svg('M12 3v9h9a9 9 0 1 1-9-9zM15 3.5A9 9 0 0 1 20.5 9H15z');
export const BARS_ICON = svg('M4 20v-8h3v8M10 20V6h3v14M16 20v-11h3v11M3 20h18');
export const BALANCE_ICON = svg('M3 12h18M7 12V5M12 12v7M17 12V8');
export const RANK_ICON = svg('M4 6h14M4 12h9M4 18h5');
export const SHARE_ICON = svg('M4 4h16v16H4zM4 10h16M4 15h16');
