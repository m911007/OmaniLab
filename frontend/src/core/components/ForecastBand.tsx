interface Axis {
  scale: ((v: string) => number) & { bandwidth?: () => number };
}

interface Props {
  /** آخر فترة فعلية وأول فترة توقع: تبدأ المنطقة عند الحدّ بينهما تمامًا فتتصل بالفعلي بلا فراغ */
  before: string;
  after: string;
  [key: string]: unknown;
}

const centre = (axis: Axis, period: string) => axis.scale(period) + (axis.scale.bandwidth?.() ?? 0) / 2;

/** خلفية خافتة لمنطقة التوقعات، من منتصف المسافة بين آخر قيمة فعلية وأول توقع حتى حافة الرسم. */
export function ForecastBand(props: Props) {
  const xMap = props.xAxisMap as Record<string, Axis> | undefined;
  const offset = props.offset as { top: number; left: number; width: number; height: number } | undefined;
  const axis = xMap && Object.values(xMap)[0];
  if (!axis || !offset) return null;
  const a = centre(axis, props.before);
  const b = centre(axis, props.after);
  if (!Number.isFinite(a) || !Number.isFinite(b)) return null;
  const x = (a + b) / 2;
  const right = offset.left + offset.width;
  if (right <= x) return null;
  return <rect x={x} y={offset.top} width={right - x} height={offset.height} fill="var(--forecast-bg)" pointerEvents="none" />;
}
