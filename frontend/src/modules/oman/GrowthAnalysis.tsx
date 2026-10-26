import { useMemo, useState } from 'react';
import { useSeries } from '@/core/hooks';
import { annualChange as change, annualMean, impact, jointImpact, pearson, rollingPearson, strength, tCritical, toAnnual, trend } from '@/core/stats';
import { formatNumber, OMR_PER_USD } from '@/core/format';
import { lastActualYear } from '@/core/lines';
import { Segmented } from '@/core/components/Segmented';
import type { Point } from '@/core/api';

type Group = 'all' | 'citizens' | 'expats';
type Lag = '0' | '1';
type Window = '6' | '8' | '10';

const GROUP: Record<Group, { label: string; ncsi: string; gcc: string }> = {
  all: { label: 'كل السكان', ncsi: 'ncsi.POP_TOTAL', gcc: 'gccstat.POP' },
  citizens: { label: 'المواطنون', ncsi: 'ncsi.POP_OMANI', gcc: 'gccstat.POP_CITIZEN' },
  expats: { label: 'الوافدون', ncsi: 'ncsi.POP_EXPAT', gcc: 'gccstat.POP_NON_CITIZEN' },
};

/** المؤشر المختار في رسم «النمو والناتج» وكيف يُحوَّل إلى قيمة سنوية وتغيّر سنوي. */
interface Focus {
  label: string;
  indicator: string;
  /** rate: المؤشر نفسه نسبة نمو فلا يُحسب تغيّره؛ level: مستوى يُحسب تغيّره السنوي */
  mode: 'rate' | 'level';
  scale?: number;
  unit: string;
  digits: number;
  source: string;
}

export const FOCUS: Record<string, Focus> = {
  growth: { label: 'نمو الناتج الحقيقي', indicator: 'imf.NGDP_RPCH', mode: 'rate', unit: '%', digits: 1, source: 'صندوق النقد' },
  gdp: { label: 'الناتج المحلي الجاري', indicator: 'imf.NGDPD', mode: 'level', scale: OMR_PER_USD, unit: 'مليار ر.ع', digits: 1, source: 'صندوق النقد' },
  pc: { label: 'نصيب الفرد من الناتج', indicator: 'imf.NGDPDPC', mode: 'level', scale: OMR_PER_USD, unit: 'ر.ع', digits: 0, source: 'صندوق النقد' },
  quarterly: { label: 'الناتج الجاري (مجموع الفصول الأربعة)', indicator: 'gccstat.GDP_CUR_Q', mode: 'level', scale: OMR_PER_USD / 1000, unit: 'مليار ر.ع', digits: 1, source: 'المركز الإحصائي الخليجي' },
  ppp: { label: 'الناتج بتعادل القوة الشرائية', indicator: 'imf.PPPGDP', mode: 'level', unit: 'مليار دولار دولي', digits: 1, source: 'صندوق النقد' },
  world: { label: 'حصة عُمان من الناتج العالمي', indicator: 'imf.PPPSH', mode: 'level', unit: '%', digits: 3, source: 'صندوق النقد' },
  ncsi: { label: 'الناتج بالأسعار الثابتة (الرسمي)', indicator: 'ncsi.GDP_CONSTANT', mode: 'level', scale: 1 / 1000, unit: 'مليار ر.ع', digits: 1, source: 'المركز الوطني للإحصاء' },
};

const DRIVER_COLOR = ['var(--series-2)', 'var(--series-4)'];

const toMap = (pts: readonly Point[] | undefined) => new Map((pts ?? []).map((p) => [p[0], p[1]]));

/** قيمة آخر شهر متاح في كل سنة (للسكان: عدد نهاية السنة أو آخر قراءة). */
function yearEnd(points: readonly Point[] | undefined): Map<string, number> {
  const m = new Map<string, number>();
  for (const [p, v] of [...(points ?? [])].sort((a, b) => a[0].localeCompare(b[0]))) m.set(p.slice(0, 4), v);
  return m;
}


const signed = (v: number, digits: number) => `${v > 0 ? '+' : ''}${formatNumber(v, digits)}`;


/** لون خلية بحسب موقع القيمة في مدى صفّها: تباعدي للنسب (أحمر سالب، أخضر موجب)، وتدرّج واحد للمستويات. */
function cellColor(v: number | null, row: (number | null)[], diverging: boolean, fixedMax?: number): string | undefined {
  if (v == null) return undefined;
  const vals = row.filter((x): x is number => x != null);
  if (diverging) {
    const max = fixedMax ?? Math.max(...vals.map(Math.abs), 1e-9);
    const t = Math.min(1, Math.abs(v) / max);
    return v >= 0 ? `rgba(39, 150, 121, ${0.12 + t * 0.55})` : `rgba(196, 68, 65, ${0.12 + t * 0.55})`;
  }
  const lo = Math.min(...vals);
  const hi = Math.max(...vals);
  const t = hi > lo ? (v - lo) / (hi - lo) : 0.5;
  return `rgba(58, 134, 255, ${0.08 + t * 0.5})`;
}

/** مقياس الارتباط من −1 إلى +1: مناطق القوة ملوّنة، وتعبئة من الصفر حتى القيمة، وعلامة عند القيمة. */
function CorrScale({ r }: { r: number }) {
  const pos = ((r + 1) / 2) * 100;
  const sign = r >= 0 ? 'pos' : 'neg';
  return (
    <div className="ga-scale" dir="ltr" role="img" aria-label={`معامل الارتباط ${formatNumber(r, 2)} على مقياس من −1 إلى +1`}>
      <div className="ga-track">
        <span className="ga-zone" style={{ left: '0%', width: '15%' }} data-z="strong-neg" />
        <span className="ga-zone" style={{ left: '15%', width: '15%' }} data-z="mid-neg" />
        <span className="ga-zone" style={{ left: '70%', width: '15%' }} data-z="mid-pos" />
        <span className="ga-zone" style={{ left: '85%', width: '15%' }} data-z="strong-pos" />
        <span className={`ga-fill ${sign}`} style={{ left: `${Math.min(50, pos)}%`, width: `${Math.abs(pos - 50)}%` }} />
        <span className="ga-zero" />
        <span className={`ga-dot ${sign}`} style={{ left: `${pos}%` }} />
      </div>
      <div className="ga-ticks">
        <span>−1</span><span>−0.5</span><span>0</span><span>+0.5</span><span>+1</span>
      </div>
      <div className="ga-ends"><span>عكسي</span><span>لا علاقة</span><span>طردي</span></div>
    </div>
  );
}

interface Row { key: string; label: string; unit: string; values: Map<string, number | null>; diverging: boolean; digits: number; source: string; target?: boolean; corr?: boolean }

/**
 * تحليل المؤشر المختار في رسم «النمو والناتج» وحده: قيمته وتغيّره السنوي بجانب سعر النفط والسكان (بنمط جدول التصنيف الائتماني)،
 * وصفّان للارتباط المتحرك عبر السنوات (هل تقوى العلاقة أم تضعف؟)، ثم ارتباط كامل الفترة في بطاقات. صفوف المؤشر ثابتة وبقية الصفوف قابلة للإخفاء.
 */
export function GrowthAnalysis({ focus = 'growth' }: { focus?: string }) {
  const [group, setGroup] = useState<Group>('all');
  const [lag, setLag] = useState<Lag>('0');
  const [win, setWin] = useState<Window>('6');
  const [hidden, setHidden] = useState<Set<string>>(new Set());
  const [method, setMethod] = useState('oil_chg');
  const f = FOCUS[focus] ?? FOCUS.growth;
  const last = lastActualYear();
  const target = useSeries(f.indicator, ['OMN'], { from: '2008' });
  const gdp = useSeries('imf.NGDPD', ['OMN'], { from: '2008' });
  const oil = useSeries('ncsi.OIL_PRICE', ['OMN'], { from: '2008' });
  const popN = useSeries(GROUP[group].ncsi, ['OMN'], { from: '2013' });
  const popG = useSeries(GROUP[group].gcc, ['OMN'], { from: '2008' });

  const years = useMemo(() => Array.from({ length: Number(last) - 2010 + 1 }, (_, i) => String(2010 + i)), [last]);
  const gl = GROUP[group].label;
  // نصيب الفرد لفئة بعينها: الناتج الكلي مقسومًا على الفئة (تقريبي للمقارنة)
  const perGroup = focus === 'pc' && group !== 'all';
  const lagN = Number(lag);
  const winN = Number(win);

  const { rows, targetKey, results, joint } = useMemo(() => {
    const pop = new Map<string, number>([...toMap(popG.data?.series[0]?.points), ...yearEnd(popN.data?.series[0]?.points)]);
    let level = new Map([...toAnnual(target.data?.series[0]?.points)].map(([y, v]) => [y, v * (f.scale ?? 1)]));
    if (perGroup) {
      const nominal = toMap(gdp.data?.series[0]?.points);
      level = new Map(years.filter((y) => nominal.get(y) != null && pop.get(y)).map((y) => [y, (nominal.get(y)! * OMR_PER_USD * 1e9) / pop.get(y)!]));
    }
    const oilA = toMap(annualMean(oil.data?.series[0]?.points ?? []));
    const pick = (m: Map<string, number>) => new Map(years.map((y) => [y, m.get(y) ?? null]));
    const chg = (m: Map<string, number>) => new Map(years.map((y) => [y, change(m, y)]));
    const label = perGroup ? `الناتج لكل فرد من ${gl} (تقريبي)` : f.label;
    const targetChange = f.mode === 'rate' ? pick(level) : chg(level);
    const oilChg = chg(oilA);
    const popChg = chg(pop);

    const drivers = [
      { key: 'oil_chg', values: oilChg, short: 'سعر النفط', xName: 'تغيّر سعر النفط %', step: 10, stepText: 'ارتفاع سعر النفط 10%', hint: 'الأسعار الجارية تتأثر بالنفط أكثر من الحقيقية.' },
      { key: 'pop_chg', values: popChg, short: `السكان (${gl})`, xName: `نمو السكان (${gl}) %`, step: 1, stepText: `زيادة نمو السكان (${gl}) نقطة مئوية واحدة`, hint: 'نمو السكان يعني عمالة واستهلاكًا أكثر، والوافدون يتبعون دورة الإنفاق.' },
    ].map((d, i) => {
      const rolling = rollingPearson(d.values, targetChange, years, winN, lagN);
      return { ...d, color: DRIVER_COLOR[i], whole: pearson(d.values, targetChange, years, lagN), effect: impact(d.values, targetChange, years, lagN), rolling, trend: trend(rolling) };
    });
    const joint = jointImpact(oilChg, popChg, targetChange, years, lagN);

    const out: Row[] = [];
    if (f.mode === 'level') out.push({ key: 'level', label, unit: f.unit, values: pick(level), diverging: false, digits: f.digits, source: perGroup ? 'محسوب' : f.source, target: true });
    out.push(
      { key: 'target', label: f.mode === 'rate' ? label : `التغيّر السنوي: ${label}`, unit: '%', values: targetChange, diverging: true, digits: 1, source: f.mode === 'rate' ? f.source : 'محسوب', target: true },
      { key: 'oil', label: 'سعر النفط العُماني (متوسط السنة)', unit: '$/برميل', values: pick(oilA), diverging: false, digits: 1, source: 'المركز الوطني' },
      { key: 'oil_chg', label: 'تغيّر سعر النفط', unit: '%', values: oilChg, diverging: true, digits: 1, source: 'محسوب' },
      { key: 'pop', label: `السكان: ${gl}`, unit: 'مليون', values: new Map(years.map((y) => [y, pop.get(y) != null ? pop.get(y)! / 1e6 : null])), diverging: false, digits: 2, source: 'المركز الخليجي + المركز الوطني' },
      { key: 'pop_chg', label: `نمو السكان: ${gl}`, unit: '%', values: popChg, diverging: true, digits: 1, source: 'محسوب' },
      ...drivers.map((d): Row => ({ key: `corr_${d.key}`, label: `ارتباط المؤشر مع ${d.short}`, unit: `معامل −1…+1 على آخر ${winN} سنوات`, values: d.rolling, diverging: true, digits: 2, source: 'محسوب', corr: true })),
    );
    return { rows: out, targetKey: label, results: drivers, joint };
  }, [target.data, gdp.data, oil.data, popN.data, popG.data, years, f, perGroup, gl, winN, lagN]);

  const yName = f.mode === 'rate' ? targetKey : `تغيّر ${targetKey}`;
  const optional = rows.filter((r) => !r.target);
  const visible = rows.filter((r) => r.target || !hidden.has(r.key));
  const toggle = (key: string) => setHidden((h) => {
    const next = new Set(h);
    if (next.has(key)) next.delete(key);
    else next.add(key);
    return next;
  });

  const loading = [target, gdp, oil, popG].some((q) => q.isLoading);
  const shown = [...years].reverse();

  return (
    <div className="card heat-wrap growth-analysis">
      <div className="controls" style={{ justifyContent: 'space-between' }}>
        <div>
          <strong>تحليل: {targetKey}</strong>
          <small className="muted ga-sub">مع النفط والسكان ({years[0]}–{last}) — غيّر المؤشر من أزرار الرسم أعلاه</small>
        </div>
        <div className="intel-chips">
          <Segmented label="فئة السكان" value={group} onChange={setGroup} options={(Object.keys(GROUP) as Group[]).map((k) => ({ value: k, label: GROUP[k].label }))} />
          <Segmented label="التأخر" value={lag} onChange={setLag} options={[{ value: '0', label: 'نفس السنة' }, { value: '1', label: 'بعد سنة' }]} />
          <Segmented label="نافذة الارتباط" value={win} onChange={setWin} options={[{ value: '6', label: '6 سنوات' }, { value: '8', label: '8' }, { value: '10', label: '10' }]} />
        </div>
      </div>

      <div className="ga-filter" role="group" aria-label="إظهار الصفوف">
        <span className="muted">الصفوف:</span>
        {optional.map((r) => (
          <button key={r.key} type="button" className={`ga-filter-chip${hidden.has(r.key) ? '' : ' on'}`} aria-pressed={!hidden.has(r.key)} onClick={() => toggle(r.key)}>
            {r.label}
          </button>
        ))}
        <button type="button" className="ga-filter-all" onClick={() => setHidden(hidden.size ? new Set() : new Set(optional.map((r) => r.key)))}>
          {hidden.size ? 'إظهار الكل' : 'المؤشر فقط'}
        </button>
      </div>

      {loading ? <p className="muted">جارٍ التحميل…</p> : (
        <>
          <div className="heat-scroll">
            <table className="heat-table" aria-label={`جدول تحليل ${targetKey}`}>
              <thead><tr><th />{shown.map((y) => <th key={y} className="num">{y}</th>)}</tr></thead>
              <tbody>
                {visible.map((r, idx) => {
                  const vals = shown.map((y) => r.values.get(y) ?? null);
                  const cls = [r.target && 'ga-target', r.corr && 'ga-corr-row', r.corr && !visible[idx - 1]?.corr && 'ga-corr-first'].filter(Boolean).join(' ');
                  return (
                    <tr key={r.key} className={cls || undefined}>
                      <th scope="row">{r.target && <span className="ga-target-tag">المؤشر</span>}{r.label}<small>{r.unit} · {r.source}</small></th>
                      {shown.map((y, i) => {
                        const v = vals[i];
                        return <td key={y} className="plain num" style={{ background: cellColor(v, vals, r.diverging, r.corr ? 1 : undefined) }} title={r.corr ? `${r.label}: السنوات ${Number(y) - winN + 1}–${y}` : `${r.label} ${y}`}>{v == null ? '—' : `${r.diverging && v > 0 ? '+' : ''}${formatNumber(v, r.digits)}`}</td>;
                      })}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <p className="source-note">صفّا الارتباط: كل خانة = ارتباط تغيّر المؤشر بتغيّر سعر النفط أو بنمو السكان على آخر {win} سنوات حتى تلك السنة؛ اقتراب القيم من +1 أو −1 مع الزمن يعني أن العلاقة تقوى، واقترابها من الصفر يعني أنها تضعف.</p>

          <h4 className="ga-h">النتيجة: الارتباط ومدى التأثير على كل السنوات المتاحة {lagN ? '(المحرّك يسبق المؤشر بسنة)' : '(في السنة نفسها)'}</h4>
          <div className="ga-corr">
            {results.map((p) => {
              const { r, n, from, to } = p.whole;
              return (
                <div key={p.key} className="card ga-corr-card" style={{ borderTopColor: p.color }}>
                  <small className="ga-pair"><i className="ga-swatch" style={{ background: p.color }} />{p.short} ↔ {f.mode === 'rate' ? f.label : `تغيّر ${targetKey}`}</small>
                  <div className="ga-value">
                    <b className="num" dir="ltr">{r == null ? '—' : `${r > 0 ? '+' : ''}${formatNumber(r, 2)}`}</b>
                    {r != null && <span className={`chip ${Math.abs(r) >= 0.4 ? (r > 0 ? 'good' : 'critical') : 'neutral'}`}>{strength(r)}</span>}
                  </div>
                  {r != null ? <CorrScale r={r} /> : <small className="muted">سنوات غير كافية (أقل من 5).</small>}
                  {p.effect && (
                    <div className="ga-effect">
                      <div className="ga-effect-main">
                        <span className="muted">الأثر:</span> كل {p.stepText} يرتبط بتغيّر{' '}
                        <b className={`num ${p.effect.slope >= 0 ? 'pos' : 'neg'}`}><bdi>{signed(p.effect.slope * p.step, 2)}</bdi> نقطة مئوية</b>{' '}
                        في {yName}{lagN ? ' في السنة التالية' : ''}.
                      </div>
                      <div className="ga-effect-meta">
                        <span>يفسّر <b className="num">{formatNumber(p.effect.r2 * 100, 0)}%</b> من تقلبات المؤشر (R²)</span>
                        <span className={`chip ${p.effect.significant ? 'good' : 'neutral'}`} title={`t = ${formatNumber(p.effect.t, 2)} مقابل حدّ ${formatNumber(tCritical(p.effect.n - 2), 2)} عند 5%`}>
                          {p.effect.significant ? 'دال إحصائيًا' : 'غير دال — قد يكون صدفة'}
                        </span>
                      </div>
                    </div>
                  )}
                  {p.trend && (
                    <small className={`ga-trend ${p.trend.tone}`}>
                      {p.trend.tone === 'up' ? '▲' : p.trend.tone === 'down' ? '▼' : p.trend.tone === 'flip' ? '⇄' : '●'} {p.trend.label}
                      <span className="ga-trend-detail"> — من <bdi className="num">{formatNumber(p.trend.first, 2)}</bdi> في أول نافذة إلى <bdi className="num">{formatNumber(p.trend.last, 2)}</bdi> في آخرها</span>
                    </small>
                  )}
                  <small className="muted">{n} سنة{from ? ` (${from}–${to})` : ''} · {p.hint}</small>
                </div>
              );
            })}
          </div>

          {joint && (
            <div className="card ga-joint">
              <h4 className="ga-h" style={{ marginTop: 0 }}>الأثر معًا: سعر النفط والسكان في نموذج واحد (انحدار متعدد)</h4>
              <p className="muted" style={{ margin: '0 0 8px' }}>النفط والسكان يتحركان معًا (طفرة النفط تجلب الوافدين)، فيتداخل أثراهما في الحساب المنفرد أعلاه. هذا النموذج يقيس أثر كل منهما مع تثبيت الآخر:</p>
              <ul className="ga-joint-list">
                <li>كل ارتفاع 10% في سعر النفط ← <b className={`num ${joint.b1 >= 0 ? 'pos' : 'neg'}`}><bdi>{signed(joint.b1 * 10, 2)}</bdi> نقطة مئوية</b> في {yName} <span className={`chip ${joint.sig1 ? 'good' : 'neutral'}`}>{joint.sig1 ? 'دال' : 'غير دال'}</span></li>
                <li>كل نقطة مئوية إضافية في نمو السكان ({gl}) ← <b className={`num ${joint.b2 >= 0 ? 'pos' : 'neg'}`}><bdi>{signed(joint.b2, 2)}</bdi> نقطة مئوية</b> <span className={`chip ${joint.sig2 ? 'good' : 'neutral'}`}>{joint.sig2 ? 'دال' : 'غير دال'}</span></li>
                <li>يفسّر المحرّكان معًا <b className="num">{formatNumber(joint.r2 * 100, 0)}%</b> من تقلبات المؤشر على {joint.n} سنة.</li>
              </ul>
            </div>
          )}

          <details className="ga-method">
            <summary>طريقة الحساب خطوة بخطوة</summary>
            <ol className="ga-method-steps">
              <li><b>التغيّر السنوي:</b> <code dir="ltr">Δ% = (Vₜ / Vₜ₋₁ − 1) × 100</code> حيث V قيمة السنة t. النمو الحقيقي نسبة نمو أصلًا فيُستخدم كما هو.</li>
              <li><b>المتوسطات:</b> x̄ متوسط تغيّر المحرّك، ȳ متوسط تغيّر المؤشر على السنوات التي يتوفر فيها الاثنان{lagN ? ' (المؤشر مأخوذ في السنة التالية لسنة المحرّك)' : ''}.</li>
              <li><b>المجاميع:</b> <code dir="ltr">Sxy = Σ(x−x̄)(y−ȳ)</code>، <code dir="ltr">Sxx = Σ(x−x̄)²</code>، <code dir="ltr">Syy = Σ(y−ȳ)²</code>.</li>
              <li><b>معامل الارتباط (بيرسون):</b> <code dir="ltr">r = Sxy / √(Sxx × Syy)</code> — اتجاه العلاقة وقوتها بين −1 و+1.</li>
              <li><b>مدى التأثير (ميل الانحدار):</b> <code dir="ltr">β = Sxy / Sxx</code> — كم نقطة مئوية يتغيّر المؤشر لكل 1% تغيّر في المحرّك؛ ويُعرض لكل 10% في النفط ولكل نقطة في نمو السكان.</li>
              <li><b>نسبة التفسير:</b> <code dir="ltr">R² = r²</code> — الجزء من تقلبات المؤشر الذي يفسّره المحرّك.</li>
              <li><b>الدلالة الإحصائية:</b> <code dir="ltr">t = r × √(n−2) / √(1−r²)</code>؛ إن تجاوزت |t| القيمة الحرجة عند 5% (مثل 2.16 لـ 13 درجة حرية) فالأثر غالبًا ليس صدفة.</li>
              <li><b>الانحدار المتعدد:</b> <code dir="ltr">y = a + b₁·x₁ + b₂·x₂</code> حيث x₁ تغيّر سعر النفط و x₂ نمو السكان، بالمربعات الصغرى، فيُقاس أثر كل محرّك مع تثبيت الآخر.</li>
            </ol>
            {(() => {
              const d = results.find((x) => x.key === method) ?? results[0];
              const e = d.effect;
              if (!e) return <p className="muted">سنوات غير كافية لعرض مثال.</p>;
              return (
                <>
                  <div className="controls" style={{ justifyContent: 'space-between' }}>
                    <strong>مثال بالأرقام الفعلية</strong>
                    <Segmented label="المحرّك" value={d.key} onChange={setMethod} options={results.map((x) => ({ value: x.key, label: x.short }))} />
                  </div>
                  <div className="heat-scroll">
                    <table className="heat-table ga-steps" aria-label="خطوات حساب الارتباط">
                      <thead><tr><th>السنة</th><th className="num">x: {d.xName}</th><th className="num">y: تغيّر المؤشر %</th><th className="num">x−x̄</th><th className="num">y−ȳ</th><th className="num">(x−x̄)(y−ȳ)</th><th className="num">(x−x̄)²</th><th className="num">(y−ȳ)²</th></tr></thead>
                      <tbody>
                        {e.pairs.map((q) => (
                          <tr key={q.year}>
                            <th scope="row" className="num">{q.year}{lagN ? ` → ${Number(q.year) + 1}` : ''}</th>
                            {[q.x, q.y, q.x - e.mx, q.y - e.my, (q.x - e.mx) * (q.y - e.my), (q.x - e.mx) ** 2, (q.y - e.my) ** 2].map((v, i) => <td key={i} className="plain num"><bdi>{formatNumber(v, 2)}</bdi></td>)}
                          </tr>
                        ))}
                        <tr className="ga-steps-total">
                          <th scope="row">المتوسط / المجموع</th>
                          <td className="plain num">x̄ = <bdi>{formatNumber(e.mx, 2)}</bdi></td>
                          <td className="plain num">ȳ = <bdi>{formatNumber(e.my, 2)}</bdi></td>
                          <td className="plain" /><td className="plain" />
                          <td className="plain num">Sxy = <bdi>{formatNumber(e.sxy, 1)}</bdi></td>
                          <td className="plain num">Sxx = <bdi>{formatNumber(e.sxx, 1)}</bdi></td>
                          <td className="plain num">Syy = <bdi>{formatNumber(e.syy, 1)}</bdi></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                  <div className="ga-calc" dir="ltr">
                    <div>r = {formatNumber(e.sxy, 1)} / √({formatNumber(e.sxx, 1)} × {formatNumber(e.syy, 1)}) = <b>{formatNumber(e.r, 3)}</b></div>
                    <div>β = {formatNumber(e.sxy, 1)} / {formatNumber(e.sxx, 1)} = <b>{formatNumber(e.slope, 4)}</b> → × {d.step} = <b>{signed(e.slope * d.step, 2)}</b> pp</div>
                    <div>R² = {formatNumber(e.r, 3)}² = <b>{formatNumber(e.r2, 3)}</b> · t = {formatNumber(e.t, 2)} (n = {e.n}, critical = {formatNumber(tCritical(e.n - 2), 2)})</div>
                  </div>
                </>
              );
            })()}
          </details>

          <p className="source-note">
            كيف تُقرأ: معامل الارتباط (بيرسون) بين −1 و+1؛ قرب +1 يعني أن المؤشرين يرتفعان وينخفضان معًا، وقرب −1 يعني أنهما يتحركان عكسيًا، وقرب الصفر لا علاقة خطية. يُحسب على التغيرات السنوية لا المستويات (المستويات المتصاعدة معًا تعطي ارتباطًا مضللًا)، وهو ارتباط لا يثبت السببية، وقليل السنوات يجعله حساسًا لسنة استثنائية (مثل 2020). السكان: المركز الإحصائي الخليجي حتى 2012 ثم عدّاد المركز الوطني (آخر قراءة في السنة). الناتج الفصلي يُجمع في السنوات المكتملة الفصول فقط. السنوات بعد {last} توقعات فلا تدخل الحساب.
          </p>
        </>
      )}
    </div>
  );
}
