import { describe, expect, it } from 'vitest';
import { shareOf } from '@/modules/population/PopulationDerived';

describe('حصص السكان', () => {
  it('حصة الجزء من المجموع', () => {
    expect(shareOf(new Map([['2024', 3]]), new Map([['2024', 7]])).get('2024')).toBe(30);
    expect(shareOf(new Map([['2024', 3]]), new Map()).size).toBe(0);
  });
});
