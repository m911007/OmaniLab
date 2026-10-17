import type { Explanation } from '../explain';

interface Props {
  explanation: Explanation;
  onBack: () => void;
}

/** لوحة الشرح التي تحلّ محل الرسم: معنى القياس، ما يوضحه الرسم، كيف يُقرأ، لماذا يهمّ، والمصطلحات. */
export function ExplainPanel({ explanation: e, onBack }: Props) {
  return (
    <div className="explain" role="region" aria-label="شرح الرسم">
      <button className="btn explain-back" onClick={onBack} aria-label="العودة إلى الرسم" title="العودة إلى الرسم">
        ↩
      </button>

      <section>
        <h4>ماذا يعني هذا القياس؟</h4>
        <p>{e.meaning}</p>
      </section>
      <section>
        <h4>ماذا يوضح الرسم؟</h4>
        <p>{e.shows}</p>
      </section>
      <section>
        <h4>كيف أقرأه؟</h4>
        <ul>
          {e.read.map((r) => (
            <li key={r}>{r}</li>
          ))}
        </ul>
      </section>
      <section>
        <h4>لماذا يهمّ؟</h4>
        <p>{e.why}</p>
      </section>
      {e.terms && e.terms.length > 0 && (
        <section>
          <h4>مصطلحات</h4>
          <dl className="explain-terms">
            {e.terms.map((t) => (
              <div key={t.term}>
                <dt>{t.term}</dt>
                <dd>{t.definition}</dd>
              </div>
            ))}
          </dl>
        </section>
      )}
      {e.caution && (
        <div className="callout" role="note">
          {e.caution}
        </div>
      )}
    </div>
  );
}
