import { formatNumber, formatPeriod } from '../format';
import { NODES, relatedTo, transformUnit, type Transform } from '../relations';
import { useRelationStats, type EdgeStats } from '../useRelations';
import { Loading } from './States';

const signed = (v: number, d = 2) => `${v > 0 ? '+' : ''}${formatNumber(v, d)}`;
const unitOf = (t: Transform) => (t === 'pct' ? '%' : t === 'level' ? '%' : ' نقطة');

/**
 * ثلاث جمل لكل علاقة كما يحتاجها صانع القرار:
 * ١) السبب ومقداره (من التاريخ الفعلي)، ٢) لماذا تهم، ٣) ماذا يرجّح آخر تغيّر في المحرّك الآن.
 * لا يُقدَّم «أثرًا» إلا ما كان دالًا إحصائيًا وفي الاتجاه المتوقع اقتصاديًا.
 */
export function RelationRow({ s, focus }: { s: EdgeStats; focus?: 'driver' | 'effect' }) {
  const from = NODES[s.edge.from];
  const to = NODES[s.edge.to];
  const step = transformUnit(from.transform);
  const lagText = s.edge.lag ? ' في السنة التالية' : '';
  const e = s.effect;
  const tone = s.usable ? 'good' : s.surprising ? 'warning' : 'neutral';
  const status = s.usable ? 'أثر مثبت إحصائيًا' : s.surprising ? 'عكس المتوقع' : 'غير مؤكد إحصائيًا';
  const changeWord = to.transform === 'level' ? '' : 'تغيّر ';

  return (
    <li className={`rel-row ${tone}`}>
      <div className="rel-head">
        <strong>
          {focus === 'effect' ? to.label : focus === 'driver' ? from.label : <>{from.label} ← {to.label}</>}
        </strong>
        <span className={`chip ${tone}`}>{status}</span>
      </div>
      <p className="rel-cause">
        {!e ? (
          'لا تكفي السنوات المشتركة لقياس العلاقة.'
        ) : s.usable ? (
          <>
            كل <b>{step.stepText}</b> في {from.label} يرتبط بـ<b className="num"><bdi>{signed(e.slope * step.step)}</bdi>{unitOf(to.transform)}</b> في {changeWord}{to.label}{lagText}، ويفسّر <b className="num">{formatNumber(e.r2 * 100, 0)}%</b> من تقلباته ({e.n} سنة).
          </>
        ) : s.surprising ? (
          <>البيانات تُظهر علاقة عكس المتوقع اقتصاديًا (معامل الارتباط <bdi className="num">{formatNumber(e.r, 2)}</bdi> على {e.n} سنة)؛ غالبًا بسبب عوامل أخرى أقوى في تلك السنوات، فتُقرأ بحذر.</>
        ) : (
          <>لم تثبت البيانات أثرًا واضحًا (معامل الارتباط <bdi className="num">{formatNumber(e.r, 2)}</bdi> على {e.n} سنة)؛ العلاقة متوقعة اقتصاديًا لكن حجمها غير مؤكد.</>
        )}
      </p>
      <p className="rel-why"><span className="muted">لماذا يهم: </span>{s.edge.note}</p>
      {s.usable && s.watch && (
        <p className="rel-watch">
          <span className="muted">ماذا نراقب الآن: </span>
          تغيّر {from.label} <b className="num"><bdi>{signed(s.watch.move, 1)}</bdi>{unitOf(from.transform)}</b> {s.watch.base ? `بين ${formatPeriod(s.watch.base)} و${formatPeriod(s.watch.period)}` : `في ${formatPeriod(s.watch.period)}`}؛
          {' '}تُرجّح العلاقة التاريخية {to.transform === 'level' ? 'أن يكون' : 'تغيّرًا في'} {to.label} {to.transform === 'level' ? 'نحو' : 'بنحو'} <b className="num"><bdi>{signed(s.watch.expected, 1)}</bdi>{unitOf(to.transform)}</b>{lagText}
          {' '}(المعتاد <bdi className="num">{signed(s.watch.usual, 1)}</bdi>{unitOf(to.transform)}) — تقدير تقريبي لا توقّع رسمي.
        </p>
      )}
    </li>
  );
}

/** المؤشرات المرتبطة بمؤشر: ما يحرّكه وما يتأثر به. */
export function RelatedPanel({ indicator }: { indicator: string }) {
  const { drivers, effects } = relatedTo(indicator);
  const { stats, isLoading } = useRelationStats([...drivers, ...effects]);
  if (!drivers.length && !effects.length) return <p className="muted">لا علاقات موثّقة لهذا المؤشر بعد.</p>;
  if (isLoading) return <Loading height={120} />;
  const of = (edges: typeof drivers) => stats.filter((s) => edges.includes(s.edge));
  return (
    <div className="rel-panel">
      {drivers.length > 0 && (
        <section>
          <h4>ما الذي يحرّكه؟</h4>
          <ul className="rel-list">{of(drivers).map((s) => <RelationRow key={s.edge.from} s={s} focus="driver" />)}</ul>
        </section>
      )}
      {effects.length > 0 && (
        <section>
          <h4>ماذا يتأثر به؟</h4>
          <ul className="rel-list">{of(effects).map((s) => <RelationRow key={s.edge.to} s={s} focus="effect" />)}</ul>
        </section>
      )}
      <p className="source-note">تُقاس كل علاقة على التغيرات السنوية الفعلية منذ 2000 (انحدار خطي واختبار t عند 5%). الارتباط لا يثبت السببية؛ العلاقات المدرجة موثّقة اقتصاديًا، والأرقام تقيس حجمها في التاريخ لا في المستقبل.</p>
    </div>
  );
}
