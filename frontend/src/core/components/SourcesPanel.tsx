import type { CrossCheckDetail, CrossCheckGroup } from '../api';
import { formatNumber, formatPeriod, formatValue } from '../format';
import { SourceBadge } from './SourceBadge';
import { Loading } from './States';

interface Props {
  group: CrossCheckGroup;
  /** تفاصيل المقارنة (تُجلب عند فتح اللوحة) */
  detail?: CrossCheckDetail;
  loading: boolean;
  open: boolean;
  onToggle: () => void;
  /** المؤشر المعروض الآن في الرسم */
  shown: string;
  onPick: (indicator: string) => void;
  /** التبديل بين المصادر متاح فقط حين يعرض الرسم عُمان وحدها */
  canSwitch: boolean;
}

const VERDICT = {
  agree: { chip: 'good', icon: '✓', text: 'متطابقة' },
  differ: { chip: 'warning', icon: '!', text: 'تختلف' },
  single: { chip: 'neutral', icon: '•', text: 'مصدر واحد' },
} as const;

/**
 * مصادر القياس الواحد في شريط مطوي موحّد: العنوان وعدد المصادر وشاراتها وحكم المطابقة، وبنقرة واحدة تُفتح قائمة المصادر
 * بجدول القيم المشتركة ومبدّل العرض. لا نختار عن القارئ؛ نعرض الاختلاف ونترك له القرار.
 */
export function SourcesPanel({ group, detail, loading, open, onToggle, shown, onPick, canSwitch }: Props) {
  const verdict = VERDICT[group.verdict];
  const members = group.members.filter((m) => m.has_data && m.source);
  if (members.length < 2) return null;

  return (
    <section className={`xsrc ${group.verdict}`} aria-label="مصادر القياس">
      <button className="xsrc-bar" aria-expanded={open} onClick={onToggle}>
        <span className="xsrc-title">
          <span className={`chip ${verdict.chip}`}>
            <span aria-hidden="true">{verdict.icon}</span>
            {members.length} مصادر {verdict.text}
          </span>
          <span className="xsrc-badges">
            {members.map((m) => (
              <SourceBadge key={m.indicator} sourceKey={m.source!.key} name={m.source!.name_ar} />
            ))}
          </span>
        </span>
        <span className="xsrc-action">{open ? 'إخفاء' : group.verdict === 'differ' ? 'اعرض المصادر وقارن' : 'التفاصيل'}</span>
      </button>

      {open &&
        (loading || !detail ? (
          <Loading height={90} />
        ) : (
          <div className="xsrc-body">
            <p className="muted">
              {detail.verdict === 'agree' ? 'القيم متقاربة ضمن حد التسامح' : 'القيم تتباعد عن حد التسامح'} (
              <span className="num">{detail.group.mode === 'relative' ? `${formatNumber(detail.group.tolerance * 100, 0)}%` : formatNumber(detail.group.tolerance, 1)}</span>) في آخر الفترات المشتركة.
            </p>
            <div className="table-scroll">
              <table className="xsrc-table">
                <thead>
                  <tr>
                    <th>الفترة</th>
                    {detail.sources.map((s) => (
                      <th key={s.indicator}>
                        <SourceBadge sourceKey={s.dataset.source.key} name={s.dataset.source.name_ar} url={s.dataset.source.url} />
                      </th>
                    ))}
                    <th>الفرق</th>
                  </tr>
                </thead>
                <tbody>
                  {detail.periods.map((p) => (
                    <tr key={p.period}>
                      <td>{formatPeriod(p.period)}</td>
                      {detail.sources.map((s) => {
                        const v = s.points.find((x) => x[0] === p.period)?.[1];
                        return (
                          <td className="num" key={s.indicator}>
                            {v === undefined ? '—' : formatValue(v, detail.sources[0].unit)}
                          </td>
                        );
                      })}
                      <td className="num" style={{ color: p.agree ? 'var(--good)' : 'var(--warning)' }}>
                        {p.agree ? '✓ ' : '! '}
                        {detail.group.mode === 'relative' ? `${formatNumber(p.spread * 100, 1)}%` : formatNumber(p.spread, 2)}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <div className="xsrc-pick" role="radiogroup" aria-label="المصدر المعروض في الرسم">
              <span className="muted">اعرض في الرسم:</span>
              {detail.sources.map((s) => (
                <label key={s.indicator} className={`pick${shown === s.indicator ? ' on' : ''}`}>
                  <input type="radio" name={`src-${group.key}`} checked={shown === s.indicator} disabled={!canSwitch} onChange={() => onPick(s.indicator)} />
                  <SourceBadge sourceKey={s.dataset.source.key} name={s.dataset.source.name_ar} />
                  <span>{s.dataset.name_ar}</span>
                </label>
              ))}
            </div>
            {!canSwitch && <p className="muted">للتبديل بين المصادر اعرض «عُمان» وحدها.</p>}
          </div>
        ))}
    </section>
  );
}
