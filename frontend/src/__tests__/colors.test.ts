import { describe, expect, it } from 'vitest';
import { assignColors } from '@/core/colors';

describe('ألوان الكيانات', () => {
  it('لون الكيان ثابت ولا يتبع ترتيبه ولا وجود غيره', () => {
    const all = assignColors(['OMN', 'SAU', 'ARE', 'QAT']);
    const fewer = assignColors(['QAT', 'OMN']);
    expect(fewer.OMN).toBe(all.OMN);
    expect(fewer.QAT).toBe(all.QAT);
  });

  it('كيان غير معروف يأخذ أول خانة حرة لا تتعارض مع المعروفين', () => {
    const c = assignColors(['OMN', 'CHN']);
    expect(c.OMN).toBe('var(--series-1)');
    expect(c.CHN).not.toBe(c.OMN);
    expect(c.CHN).not.toBe('var(--series-other)');
  });

  it('كيان وحيد غير معروف يأخذ الخانة الأولى لا الرمادي', () => {
    expect(assignColors(['CHN']).CHN).toBe('var(--series-1)');
  });

  it('لا ألوان واحدة لكيانين مختلفين', () => {
    const c = assignColors(['AAA', 'BBB', 'CCC']);
    expect(new Set(Object.values(c)).size).toBe(3);
  });
});

import { assignLineColors } from '@/core/colors';

describe('assignLineColors', () => {
  it('يعطي خطوط الكيان الواحد ألوانًا مختلفة', () => {
    const c = assignLineColors([{ entity: 'OMN' }, { entity: 'OMN' }, { entity: 'OMN' }, { entity: 'OMN' }]);
    expect(new Set(c).size).toBe(4);
  });
  it('يتخطى الألوان الصريحة', () => {
    const c = assignLineColors([{ entity: 'OMN', color: 'var(--series-1)' }, { entity: 'OMN' }, { entity: 'OMN' }]);
    expect(c).toEqual(['var(--series-1)', 'var(--series-2)', 'var(--series-3)']);
  });
  it('يبقي لون الكيان عند اختلاف الكيانات', () => {
    expect(assignLineColors([{ entity: 'SAU' }, { entity: 'OMN' }])).toEqual(['var(--series-3)', 'var(--series-1)']);
  });
});
