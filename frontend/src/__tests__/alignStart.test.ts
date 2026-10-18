import { alignStart } from '@/core/components/LineChartView';
import type { SeriesItem } from '@/core/hooks';

const s = (id: string, years: number[]): SeriesItem => ({ id, label: id, color: '#000', points: years.map((y) => [String(y), y]) });

describe('alignStart', () => {
  it('يقص الخطوط عند أحدث بداية مشتركة', () => {
    const { aligned, startedAt } = alignStart([s('a', [2000, 2001, 2002, 2003, 2004, 2005, 2006, 2007, 2008, 2009]), s('b', [2003, 2004, 2005, 2006, 2007, 2008, 2009])]);
    expect(startedAt).toBe('2003');
    expect(aligned[0].points[0][0]).toBe('2003');
  });

  it('لا يقص إن بقيت أقل من 5 نقاط أو كانت البدايات متطابقة', () => {
    const late = alignStart([s('a', [2000, 2001, 2002, 2003, 2004]), s('b', [2003, 2004])]);
    expect(late.startedAt).toBeNull();
    const same = alignStart([s('a', [2000, 2001]), s('b', [2000, 2002])]);
    expect(same.startedAt).toBeNull();
  });
});
