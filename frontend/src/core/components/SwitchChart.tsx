import { useState } from 'react';
import type { LineSpec } from '../hooks';
import { Segmented } from './Segmented';
import { SeriesChart } from './SeriesChart';

export interface ChartVariant {
  value: string;
  label: string;
  /** عنوان فرعي خاص بهذا المؤشر (يحلّ محل subtitle العام) */
  subtitle?: string;
  lines: readonly LineSpec[];
  /** نوع الرسم لهذا المؤشر (الافتراضي line) */
  kind?: 'line' | 'columns' | 'stacked';
  /** خيارات خاصة بهذا المؤشر (تحلّ محل خيارات الرسم العامة) */
  from?: string;
  last?: number;
  oil?: boolean;
  imfForecast?: boolean;
  note?: string;
}

type Props = Omit<React.ComponentProps<typeof SeriesChart>, 'lines' | 'subtitle'> & { variants: readonly ChartVariant[]; subtitle?: string; defaultVariant?: string; onVariantChange?: (value: string) => void };

/** رسم واحد بمبدّل: عدة مؤشرات متقاربة المعنى في بطاقة واحدة يختار القارئ بينها، فيوفّر المساحة ويتيح المقارنة السريعة. */
export function SwitchChart({ variants, defaultVariant, subtitle, onVariantChange, ...rest }: Props) {
  const [value, setValue] = useState(defaultVariant ?? variants[0].value);
  const choose = (v: string) => {
    setValue(v);
    onVariantChange?.(v);
  };
  const current = variants.find((v) => v.value === value) ?? variants[0];
  return (
    <SeriesChart
      // الرسم متعدد الكيانات له وضع «مقارنة» مستقل، فيُعاد بناؤه عند الانتقال إليه أو منه
      key={current.lines.some((l) => l.entity !== (rest.primary ?? 'OMN')) ? 'multi' : 'single'}
      {...rest}
      subtitle={current.subtitle ?? subtitle}
      lines={current.lines}
      kind={current.kind ?? 'line'}
      from={current.from ?? rest.from}
      last={current.last ?? rest.last}
      oil={current.oil ?? rest.oil}
      imfForecast={current.imfForecast ?? rest.imfForecast}
      note={current.note ?? rest.note}
      extraActions={<>{rest.extraActions}<Segmented label="المؤشر" value={current.value} onChange={choose} options={variants.map((v) => ({ value: v.value, label: v.label }))} /></>}
    />
  );
}
