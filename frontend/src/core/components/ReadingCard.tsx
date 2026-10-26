import { useEffect, useRef, useState } from 'react';
import { Link } from 'react-router-dom';
import type { Reading, VerdictLevel } from '../api';
import { relatedTo } from '../relations';
import { RelatedPanel } from './RelatedPanel';
import { formatNumber } from '../format';
import { FreshnessBadge } from './FreshnessBadge';
import { Sparkline } from './Sparkline';

const VERDICT: Record<VerdictLevel, { cls: string; icon: string }> = {
  good: { cls: 'good', icon: '✓' },
  normal: { cls: 'neutral', icon: '●' },
  attention: { cls: 'warning', icon: '!' },
  critical: { cls: 'critical', icon: '✕' },
};

/** هل التغيّر تحسّن أم تراجع بحسب القطبية؟ بلا قطبية يبقى محايدًا. */
export function changeTone(previous: number, current: number, polarity: string | null): 'good' | 'bad' | 'flat' {
  if (polarity === null || current === previous) return 'flat';
  if (polarity === 'lower') return current < previous ? 'good' : 'bad';
  if (polarity.startsWith('target:')) {
    const target = Number(polarity.slice(7));
    return Math.abs(current - target) < Math.abs(previous - target) ? 'good' : 'bad';
  }
  return current > previous ? 'good' : 'bad';
}

const withUnit = (v: number, r: Reading) => `${formatNumber(v, r.digits)}${r.unit.startsWith('%') ? r.unit : ` ${r.unit}`}`;

/** يمنع التركيز والقراءة الآلية في الوجه المخفي من البطاقة المقلوبة. */
function useInert(hidden: boolean) {
  const ref = useRef<HTMLDivElement>(null);
  useEffect(() => {
    if (ref.current) (ref.current as HTMLElement & { inert: boolean }).inert = hidden;
  }, [hidden]);
  return ref;
}

/**
 * بطاقة القراءة (الطبقة الأولى).
 * flip (الافتراضي): وجه صغير بالرقم والحكم والتغيّر والاتجاه، وزر «؟» يقلبها لتظهر التفاصيل: الجملة المولَّدة،
 * ومراجع الحكم (تاريخ المؤشر، الخليج، مرجع موثّق)، والمصدر وحداثته، ورابط صفحة المؤشر بعلاقاته.
 * full: كل ذلك ظاهرًا معًا (صفحة المؤشر)، مع لوحة «ما الذي يحرّكه». الحكم لا يظهر إلا بقطبية ومرجع.
 */
export function ReadingCard({ reading: r, variant = 'flip' }: { reading: Reading; variant?: 'flip' | 'full' }) {
  const [flipped, setFlipped] = useState(false);
  const [open, setOpen] = useState(false);
  const frontRef = useInert(variant === 'flip' && flipped);
  const backRef = useInert(variant === 'flip' && !flipped);
  const rel = relatedTo(r.key);
  const relCount = rel.drivers.length + rel.effects.length;
  const verdict = r.verdict ? VERDICT[r.verdict.level] : null;
  const rawDiff = r.previous ? r.value - r.previous.value : null;
  // تغيّر يُقرّب إلى صفر بخانات العرض يُعامل كثبات (لا سهم أحمر على «0»)
  const diff = rawDiff !== null && Math.abs(rawDiff) < 0.5 * 10 ** -r.digits ? 0 : rawDiff;
  const tone = r.previous && diff !== 0 ? changeTone(r.previous.value, r.value, r.polarity) : 'flat';
  const rate = r.unit.startsWith('%');
  const verdictTitle = r.verdict ? `الحكم مبني على: ${r.verdict.basis.map((b) => (b === 'history' ? 'تاريخ المؤشر' : 'دول الخليج')).join(' و')}` : 'لا يُحكم على هذا المؤشر بجيد أو سيئ؛ يُعرض للمعرفة';

  const chip = verdict && r.verdict ? (
    <span className={`chip ${verdict.cls}`} title={verdictTitle}>
      <span aria-hidden="true">{verdict.icon}</span>
      {r.verdict.label}
    </span>
  ) : (
    <span className="chip neutral" title={verdictTitle}>للمعرفة</span>
  );

  const summary = (
    <>
      <div className="reading-value">
        <div>
          <b className="num">{formatNumber(r.value, r.digits)}</b>
          <span className="reading-unit">{r.unit}</span>
        </div>
        {r.spark.length > 2 && <Sparkline values={r.spark.map((p) => p[1])} width={variant === 'flip' ? 72 : 96} height={variant === 'flip' ? 26 : 30} color={verdict ? `var(--${verdict.cls === 'neutral' ? 'accent' : verdict.cls})` : 'var(--accent)'} />}
      </div>
      <div className="reading-meta">
        <span>{r.period_label}</span>
        {r.previous && diff !== null && (
          <span className={`reading-delta ${tone}`}>
            <span aria-hidden="true">{diff > 0 ? '▲' : diff < 0 ? '▼' : '■'}</span>{' '}
            {diff === 0 ? 'دون تغيّر' : <><bdi className="num">{formatNumber(Math.abs(diff), r.digits)}</bdi>{rate ? ' نقطة' : ''}</>} عن {r.previous.period_label}
          </span>
        )}
      </div>
    </>
  );

  const details = (
    <>
      <p className="reading-sentence">{r.sentence}</p>
      <dl className="reading-refs">
        <div>
          <dt>تاريخيًا</dt>
          <dd>
            {r.history ? (r.history.percentile != null ? (r.history.percentile >= 50 ? `أفضل من ${formatNumber(r.history.percentile, 0)}%` : `أسوأ من ${formatNumber(100 - r.history.percentile, 0)}%`) : `المتوسط ${withUnit(r.history.mean, r)}`) : '—'}
            {r.history && <small>من قراءات {r.history.years} سنوات</small>}
          </dd>
        </div>
        <div>
          <dt>خليجيًا</dt>
          <dd>
            {r.gcc ? <>المرتبة <bdi className="num">{r.gcc.rank}</bdi> من {r.gcc.of}</> : '—'}
            {r.gcc && <small>المتوسط {withUnit(r.gcc.mean, r)}</small>}
          </dd>
        </div>
        <div>
          <dt>مرجع</dt>
          <dd title={r.reference?.source}>
            {r.reference ? r.reference.label : '—'}
            {r.reference && <small>{r.value > r.reference.value ? 'أعلى من المرجع' : r.value < r.reference.value ? 'أدنى من المرجع' : 'عند المرجع'}</small>}
          </dd>
        </div>
      </dl>
    </>
  );

  const foot = (
    <footer className="reading-foot">
      <span className="muted">{r.dataset.source.name_ar}</span>
      <FreshnessBadge dataset={r.dataset} />
    </footer>
  );

  if (variant === 'full') {
    return (
      <article className={`card reading-card${r.verdict ? ` verdict-${r.verdict.level}` : ''}`} aria-label={r.label}>
        <header className="reading-head">
          <h3>{r.label}</h3>
          {chip}
        </header>
        {summary}
        {details}
        {relCount > 0 && (
          <button type="button" className="reading-related-btn" aria-expanded={open} onClick={() => setOpen((v) => !v)}>
            {open ? 'إخفاء' : 'ما الذي يحرّكه وماذا يتأثر به؟'} <span className="muted">({relCount})</span>
          </button>
        )}
        {open && <RelatedPanel indicator={r.key} />}
        {foot}
      </article>
    );
  }

  return (
    <article className={`reading-flip${flipped ? ' flipped' : ''}`} aria-label={r.label}>
      <div className="reading-inner">
        <div ref={frontRef} className={`card reading-card reading-face front${r.verdict ? ` verdict-${r.verdict.level}` : ''}`} aria-hidden={flipped}>
          <header className="reading-head">
            <h3><Link to={`/indicator/${r.key}`} className="reading-title" title="صفحة المؤشر: الرسم الكامل والترتيب والعلاقات والمصدر">{r.label}</Link></h3>
            <button type="button" className="reading-flip-btn" onClick={() => setFlipped(true)} aria-label={`تفاصيل قراءة ${r.label}`} title="التفاصيل: لماذا هذا الحكم؟">؟</button>
          </header>
          {summary}
          <div className="reading-front-foot">{chip}</div>
        </div>
        <div ref={backRef} className={`card reading-card reading-face back${r.verdict ? ` verdict-${r.verdict.level}` : ''}`} aria-hidden={!flipped}>
          <header className="reading-head">
            <h3>{r.label}</h3>
            <button type="button" className="reading-flip-btn" onClick={() => setFlipped(false)} aria-label="رجوع إلى الرقم" title="رجوع">↩</button>
          </header>
          {details}
          <Link to={`/indicator/${r.key}`} className="reading-more">
            الصفحة الكاملة{relCount > 0 ? ' وما يحرّكه' : ''} ←
          </Link>
          {foot}
        </div>
      </div>
    </article>
  );
}
