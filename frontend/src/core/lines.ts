import { GCC_CODES, PEER_CODES } from './colors';
import type { LineSpec } from './hooks';

/** سنة آخر قيمة فعلية لمؤشرات IMF (السنة الماضية؛ ما بعدها توقعات). */
export const lastActualYear = (): string => String(new Date().getFullYear() - 1);

export const oman = (indicator: string, label?: string): LineSpec[] => [{ indicator, entity: 'OMN', label }];

export const gcc = (indicator: string): LineSpec[] => GCC_CODES.map((entity) => ({ indicator, entity }));

export const peers = (indicator: string): LineSpec[] => PEER_CODES.map((entity) => ({ indicator, entity }));
