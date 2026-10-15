import { GCC_CODES, PEER_CODES } from './colors';
import type { LineSpec } from './hooks';

export const gccLines = (indicator: string): LineSpec[] => GCC_CODES.map((entity) => ({ indicator, entity }));
export const peerLines = (indicator: string): LineSpec[] => PEER_CODES.map((entity) => ({ indicator, entity }));

export const COUNTRY_NAMES: Record<string, string> = { OMN: 'عُمان', SAU: 'السعودية', ARE: 'الإمارات', QAT: 'قطر', KWT: 'الكويت', BHR: 'البحرين' };
