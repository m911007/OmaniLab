interface Props {
  values: readonly number[];
  /** لون الخط؛ الافتراضي لون التمييز */
  color?: string;
  width?: number;
  height?: number;
}

/** خط مصغّر لاتجاه المؤشر: بلا محاور (المعنى في الرقم المجاور)، ونقطة أخيرة تُبرز آخر قيمة. */
export function Sparkline({ values, color = 'var(--accent)', width = 96, height = 30 }: Props) {
  if (values.length < 2) return null;
  const min = Math.min(...values);
  const max = Math.max(...values);
  const span = max - min || 1;
  const pad = 3;
  const x = (i: number) => pad + (i / (values.length - 1)) * (width - pad * 2);
  const y = (v: number) => height - pad - ((v - min) / span) * (height - pad * 2);
  const d = values.map((v, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)} ${y(v).toFixed(1)}`).join(' ');
  const last = values.length - 1;
  return (
    <svg width={width} height={height} viewBox={`0 0 ${width} ${height}`} role="img" aria-label={`اتجاه آخر ${values.length} قراءة`} style={{ direction: 'ltr', flexShrink: 0 }}>
      <path d={d} fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
      <circle cx={x(last)} cy={y(values[last])} r={3} fill={color} stroke="var(--surface-1)" strokeWidth={1.5} />
    </svg>
  );
}
