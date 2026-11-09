import { describe, expect, it } from 'vitest';
import { hydrocarbonExportShare, importCoverMonths, netPosition } from '@/modules/external/derived';

const m = (o: Record<string, number>) => new Map(Object.entries(o));
describe('مقاييس القطاع الخارجي المشتقة', () => {
  it('تغطية الاحتياطيات بالأشهر', () => {
    expect(importCoverMonths(m({ 2024: 6000 }), m({ 2024: -12000 })).get('2024')).toBeCloseTo(6);
  });
  it('حصة الهيدروكربون من الصادرات', () => {
    expect(hydrocarbonExportShare(m({ 2024: 50 }), m({ 2024: 20 }), m({ 2024: 10 }), m({ 2024: 100 })).get('2024')).toBe(80);
  });
  it('صافي المركز', () => {
    expect(netPosition(m({ 2025: 125 }), m({ 2025: 151 })).get('2025')).toBe(-26);
  });
});
