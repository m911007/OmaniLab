import type { StandingClass } from '@/core/api';

export type InsightGroup = 'strength' | 'opportunity' | 'challenge' | 'watch' | 'neutral';

export interface ClassMeta {
  label: string;
  group: InsightGroup;
  chip: 'good' | 'warning' | 'critical' | 'neutral';
  icon: string;
  hint: string;
}

/** تصنيفات البطاقة: النص والأيقونة مع اللون دائمًا (لا اعتماد على اللون وحده). */
export const CLASS_META: Record<StandingClass, ClassMeta> = {
  strength: { label: 'نقطة قوة', group: 'strength', chip: 'good', icon: '▲', hint: 'ضمن أفضل 30% عالميًا، ولا تتراجع.' },
  eroding: { label: 'قوة تتآكل', group: 'watch', chip: 'warning', icon: '↘', hint: 'ضمن أفضل 30% عالميًا لكنها تتراجع عن مستواها قبل خمس سنوات.' },
  challenge: { label: 'تحدٍّ', group: 'challenge', chip: 'critical', icon: '▼', hint: 'ضمن أضعف 30% عالميًا ولا تتحسن.' },
  recovering: { label: 'تحدٍّ يتحسن', group: 'opportunity', chip: 'warning', icon: '↗', hint: 'ضمن أضعف 30% عالميًا لكنها تتحسن؛ مسار يستحق الدعم.' },
  opportunity: { label: 'فرصة', group: 'opportunity', chip: 'good', icon: '◆', hint: 'في منتصف الترتيب وتتحسن؛ المجال مفتوح للتقدم.' },
  watch: { label: 'تحت المراقبة', group: 'watch', chip: 'warning', icon: '!', hint: 'في منتصف الترتيب وتتراجع.' },
  neutral: { label: 'محايد', group: 'neutral', chip: 'neutral', icon: '■', hint: 'في منتصف الترتيب ومستقرة.' },
};

export const GROUP_LABEL: Record<InsightGroup, string> = {
  strength: 'نقاط القوة',
  opportunity: 'الفرص',
  challenge: 'التحديات',
  watch: 'تحت المراقبة',
  neutral: 'محايدة',
};
