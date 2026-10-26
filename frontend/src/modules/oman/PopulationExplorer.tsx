import { useMemo, useState } from 'react';
import { useLines, type LineSpec } from '@/core/hooks';
import { formatNumber } from '@/core/format';
import { SeriesChart } from '@/core/components/SeriesChart';
import { CHART_ICON, Segmented, TABLE_ICON } from '@/core/components/Segmented';

type Nat = 'all' | 'omani' | 'expat';
type Sex = 'all' | 'male' | 'female';
type View = 'chart' | 'table';
type Topic = 'pop' | 'births' | 'deaths' | 'gcc';

const TOPICS: { value: Topic; label: string }[] = [
  { value: 'pop', label: 'السكان' },
  { value: 'births', label: 'المواليد' },
  { value: 'deaths', label: 'الوفيات' },
  { value: 'gcc', label: 'السكان (المركز الخليجي)' },
];
const onOman = (indicator: string, label: string, color?: string): LineSpec => ({ indicator, entity: 'OMN', label, color });
const TOPIC_CHARTS: Record<Exclude<Topic, 'pop'>, { title: string; subtitle: string; from?: string; lines: LineSpec[] }> = {
  births: { title: 'المواليد', subtitle: 'مولود، شهري — المركز الوطني للإحصاء', from: '2018', lines: [onOman('ncsi.BIRTHS', 'المواليد')] },
  deaths: { title: 'الوفيات', subtitle: 'حالة، شهري — المركز الوطني للإحصاء', from: '2018', lines: [onOman('ncsi.DEATHS', 'الوفيات')] },
  gcc: { title: 'عدد السكان', subtitle: 'نسمة، سنوي — المركز الإحصائي الخليجي', lines: [onOman('gccstat.POP', 'إجمالي السكان', 'var(--series-1)'), onOman('gccstat.POP_CITIZEN', 'المواطنون', 'var(--series-2)'), onOman('gccstat.POP_NON_CITIZEN', 'غير المواطنين', 'var(--series-3)')] },
};
type Measure = 'pct' | 'abs';

const NAT: Record<Nat, { suffix: string; label: string }> = {
  all: { suffix: '', label: 'الإجمالي' },
  omani: { suffix: '_OMANI', label: 'العُمانيون' },
  expat: { suffix: '_EXPAT', label: 'الوافدون' },
};
const SEX: Record<Sex, { key: string; label: string }> = {
  all: { key: '', label: 'الجنسان' },
  male: { key: '_MALE', label: 'ذكور' },
  female: { key: '_FEMALE', label: 'إناث' },
};
const TOTAL_KEY: Record<Nat, string> = { all: 'POP_TOTAL', omani: 'POP_OMANI', expat: 'POP_EXPAT' };

const indicatorOf = (nat: Nat, sex: Sex) => (sex === 'all' ? `ncsi.${TOTAL_KEY[nat]}` : `ncsi.POP${NAT[nat].suffix}${SEX[sex].key}`);
const labelOf = (nat: Nat, sex: Sex) => [nat === 'all' ? null : NAT[nat].label, sex === 'all' ? null : SEX[sex].label].filter(Boolean).join(' · ') || 'إجمالي السكان';
const spec = (nat: Nat, sex: Sex, color?: string): LineSpec => ({ indicator: indicatorOf(nat, sex), entity: 'OMN', label: labelOf(nat, sex), color });

const NATS: Nat[] = ['all', 'omani', 'expat'];
const SEXES: Sex[] = ['all', 'male', 'female'];
const ALL_LINES: LineSpec[] = NATS.flatMap((n) => SEXES.map((s) => spec(n, s)));

function linesFor(nat: Nat, sex: Sex): LineSpec[] {
  if (nat !== 'all' && sex !== 'all') return [spec(nat, sex, 'var(--series-1)')];
  if (sex === 'all') {
    if (nat === 'all') return [spec('all', 'all', 'var(--series-1)'), spec('omani', 'all', 'var(--series-3)'), spec('expat', 'all', 'var(--series-2)')];
    return [spec(nat, 'all', 'var(--series-1)'), spec(nat, 'male', 'var(--series-3)'), spec(nat, 'female', 'var(--series-2)')];
  }
  return [spec('all', sex, 'var(--series-1)'), spec('omani', sex, 'var(--series-3)'), spec('expat', sex, 'var(--series-2)')];
}

/** لون الخلية: أخضر مائل للتركوازي للنمو، أحمر للانكماش، وشدّته بقدر حجم التغير. */
function changeColor(pct: number | null): string {
  if (pct == null) return 'var(--surface-2)';
  const t = Math.min(Math.abs(pct) / 8, 1);
  const [r, g, b] = pct >= 0 ? [39, 150, 121] : [196, 68, 65];
  return `rgba(${r}, ${g}, ${b}, ${0.35 + t * 0.6})`;
}

interface YearCell { value: number; month: string }

/** السكان حسب الجنسية والجنس: فلاتر ورسم، وجدول حراري للتغير السنوي بنمط جدول التصنيف الائتماني. */
export function PopulationExplorer() {
  const [nat, setNat] = useState<Nat>('all');
  const [sex, setSex] = useState<Sex>('all');
  const [topic, setTopic] = useState<Topic>('pop');
  const [view, setView] = useState<View>('chart');
  const [measure, setMeasure] = useState<Measure>('pct');
  const all = useLines(ALL_LINES, { from: '2015' });

  const byYear = useMemo(() => {
    const out = new Map<string, Map<number, YearCell>>();
    for (const s of all.series) {
      const years = new Map<number, YearCell>();
      for (const [p, v] of s.points) {
        const y = Number(p.slice(0, 4));
        const month = p.slice(5, 7) || '12';
        const cur = years.get(y);
        if (!cur || month >= cur.month) years.set(y, { value: v, month });
      }
      out.set(s.id.split('|')[0]!, years);
    }
    return out;
  }, [all.series]);

  const years = useMemo(() => {
    const set = new Set<number>();
    for (const m of byYear.values()) for (const y of m.keys()) set.add(y);
    return [...set].sort((a, b) => b - a).slice(0, 8);
  }, [byYear]);

  const lines = useMemo(() => linesFor(nat, sex), [nat, sex]);
  const rows = NATS.flatMap((n) => SEXES.map((s) => ({ nat: n, sex: s, key: indicatorOf(n, s) })));

  return (
    <>
      <div className="controls">
        <Segmented label="المؤشر" value={topic} onChange={setTopic} options={TOPICS} />
        {topic === 'pop' && (
          <>
            <Segmented label="الجنسية" value={nat} onChange={setNat} options={NATS.map((n) => ({ value: n, label: NAT[n].label }))} />
            <Segmented label="الجنس" value={sex} onChange={setSex} options={SEXES.map((s) => ({ value: s, label: SEX[s].label }))} />
            <Segmented label="العرض" value={view} onChange={setView} options={[{ value: 'chart', label: 'رسم', icon: CHART_ICON }, { value: 'table', label: 'جدول التغير السنوي', icon: TABLE_ICON }]} />
          </>
        )}
      </div>
      {topic !== 'pop' && <SeriesChart key={topic} wide height={340} {...TOPIC_CHARTS[topic]} />}
      {topic === 'pop' && view === 'chart' && (
        <SeriesChart wide height={340} title="السكان حسب الجنسية والجنس" subtitle={`${labelOf(nat, sex)} — نسمة، شهري، عدّاد المركز الوطني للإحصاء`} lines={lines} from="2018" />
      )}
      {topic === 'pop' && view === 'table' && (
        <div className="card heat-wrap">
          <div className="controls" style={{ justifyContent: 'space-between' }}>
            <strong>التغير في السكان حسب الجنسية والجنس</strong>
            <Segmented label="القياس" value={measure} onChange={setMeasure} options={[{ value: 'pct', label: 'نسبة التغير %' }, { value: 'abs', label: 'التغير بالعدد' }]} />
          </div>
          <div className="heat-scroll">
            <table className="heat-table">
              <thead>
                <tr>
                  <th />
                  {years.map((y) => <th key={y} className="num">{y}</th>)}
                </tr>
              </thead>
              <tbody>
                {rows.map((r) => {
                  const m = byYear.get(r.key);
                  return (
                    <tr key={r.key}>
                      <th scope="row">{labelOf(r.nat, r.sex)}</th>
                      {years.map((y) => {
                        const cur = m?.get(y);
                        const prev = m?.get(y - 1);
                        const diff = cur && prev ? cur.value - prev.value : null;
                        const pct = cur && prev && prev.value ? (diff! / prev.value) * 100 : null;
                        if (!cur) return <td key={y} className="plain">—</td>;
                        return (
                          <td key={y} style={{ background: changeColor(pct) }} title={`${labelOf(r.nat, r.sex)} ${y}: ${formatNumber(cur.value, 0)} نسمة (حتى الشهر ${Number(cur.month)})`}>
                            <strong dir="ltr">{measure === 'pct' ? (pct == null ? '—' : `${pct > 0 ? '+' : ''}${formatNumber(pct, 1)}%`) : (diff == null ? '—' : `${diff > 0 ? '+' : ''}${formatNumber(diff, 0)}`)}</strong>
                            <small>{measure === 'pct' ? formatNumber(cur.value, 0) : pct == null ? '' : `${pct > 0 ? '+' : ''}${formatNumber(pct, 1)}%`}</small>
                          </td>
                        );
                      })}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p className="source-note">الأخضر نمو والأحمر انكماش (تتدرج الشدة حتى ±8%). القيمة المعروضة آخر شهر متاح من كل سنة، والتغير مقارنة بنهاية السنة السابقة (فالسنة الجارية جزئية حتى آخر شهر متاح). المصدر: المركز الوطني للإحصاء والمعلومات (عدّاد السكان).</p>
        </div>
      )}
    </>
  );
}
