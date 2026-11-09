import { describe, expect, it } from 'vitest';
import { perUnit } from '@/modules/human/HumanDerived';

describe('نسب التعليم', () => {
  it('الطلبة لكل معلم', () => {
    expect(perUnit(new Map([['2024', 600]]), new Map([['2024', 30]])).get('2024')).toBe(20);
    expect(perUnit(new Map([['2024', 600]]), new Map([['2024', 0]])).size).toBe(0);
  });
});
