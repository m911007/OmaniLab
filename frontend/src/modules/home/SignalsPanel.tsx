import { useState } from 'react';
import { Link } from 'react-router-dom';
import type { Signal } from '@/core/api';
import { ReadingGrid } from '@/core/components/ReadingGrid';
import { formatNumber, formatPeriod } from '@/core/format';
import { useSignals } from '@/core/hooks';

const TONE: Record<Signal['tone'], { chip: string; icon: string; label: string; verdict: string }> = {
  critical: { chip: 'critical', icon: '▼', label: 'خطر', verdict: 'critical' },
  warning: { chip: 'warning', icon: '!', label: 'انتباه', verdict: 'attention' },
  good: { chip: 'good', icon: '▲', label: 'فرصة', verdict: 'good' },
};

/** بطاقة إشارة بتصميم بطاقة القراءة: وجه صغير بالعنوان والقيمة والنوع، و«؟» يقلبها لما حدث ولماذا يهم وماذا نراقب. */
function SignalCard({ s }: { s: Signal }) {
  const [flipped, setFlipped] = useState(false);
  const tone = TONE[s.tone];
  return (
    <article className={`reading-flip signal-card${flipped ? ' flipped' : ''}`} aria-label={s.title}>
      <div className="reading-inner">
        <div className={`card reading-card reading-face front verdict-${tone.verdict}`} aria-hidden={flipped}>
          <header className="reading-head">
            <h3>{s.title}</h3>
            <button type="button" className="reading-flip-btn" onClick={() => setFlipped(true)} aria-label={`تفاصيل إشارة ${s.title}`} title="ماذا حدث ولماذا يهم؟" tabIndex={flipped ? -1 : 0}>؟</button>
          </header>
          <div className="reading-value">
            <div><b className="num">{formatNumber(s.value, 2)}</b></div>
          </div>
          <div className="reading-meta">
            <span>{formatPeriod(s.period)}</span>
            {s.previous != null && <span>السابقة <bdi className="num">{formatNumber(s.previous, 2)}</bdi></span>}
          </div>
          <div className="reading-front-foot">
            <span className={`chip ${tone.chip}`}>
              <span aria-hidden="true">{tone.icon}</span>
              {tone.label}
            </span>
          </div>
        </div>
        <div className={`card reading-card reading-face back verdict-${tone.verdict}`} aria-hidden={!flipped}>
          <header className="reading-head">
            <h3>{s.title}</h3>
            <button type="button" className="reading-flip-btn" onClick={() => setFlipped(false)} aria-label="رجوع" title="رجوع" tabIndex={flipped ? 0 : -1}>↩</button>
          </header>
          <p className="reading-sentence">{s.message}</p>
          {s.why && <p className="signal-why"><b>لماذا يهم:</b> {s.why}</p>}
          {s.watch && <p className="signal-why"><b>ماذا نراقب:</b> {s.watch}</p>}
          {s.link && <Link to={s.link} className="reading-more" tabIndex={flipped ? 0 : -1}>التفاصيل ←</Link>}
        </div>
      </div>
    </article>
  );
}

/** إشارات السوق النشطة: قواعد معلنة (config/signals.php) على أحدث البيانات، في بطاقات بالتصميم الموحّد. */
export function SignalsPanel({ showEmpty = false }: { showEmpty?: boolean }) {
  const { data } = useSignals();
  if (!data || data.length === 0) {
    return showEmpty ? <p className="card muted">لا إشارات استثنائية في أحدث البيانات: لم تتجاوز أسعار النفط والفائدة والتضخم والاحتياطيات عتباتها المعلنة.</p> : null;
  }
  return (
    <ReadingGrid className="signals">
      {data.map((s) => <SignalCard key={s.id} s={s} />)}
    </ReadingGrid>
  );
}
