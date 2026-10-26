import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

describe('سلامة ملف الأنماط', () => {
  it('الأقواس متوازنة (قوس مفتوح يحبس كل القواعد بعده داخل استعلام وسائط)', () => {
    const css = readFileSync(resolve(__dirname, '../design/base.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '');
    let depth = 0;
    for (const ch of css) {
      if (ch === '{') depth++;
      if (ch === '}') depth--;
      expect(depth).toBeGreaterThanOrEqual(0);
    }
    expect(depth).toBe(0);
  });
});
