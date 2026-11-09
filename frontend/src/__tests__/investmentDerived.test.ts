import { describe, expect, it } from 'vitest';
import { savingGap } from '@/modules/investment/InvestmentDerived';

describe('فجوة الادخار والاستثمار', () => {
  it('الادخار ناقص الاستثمار', () => {
    expect(savingGap(new Map([['2024', 30]]), new Map([['2024', 24]])).get('2024')).toBe(6);
    expect(savingGap(new Map([['2024', 30]]), new Map()).size).toBe(0);
  });
});
