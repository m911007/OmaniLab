import type { Scorecard, ScorecardItem } from '@/core/api';

type Col = 'weak' | 'mid' | 'strong';
type Row = 'improving' | 'stable' | 'worsening';

const COLS: { id: Col; label: string }[] = [
  { id: 'strong', label: 'في أفضل الدول' },
  { id: 'mid', label: 'وسط الترتيب' },
  { id: 'weak', label: 'في أضعف الدول' },
];
const ROWS: { id: Row; label: string; icon: string }[] = [
  { id: 'improving', label: 'يتحسن', icon: '↗' },
  { id: 'stable', label: 'مستقر', icon: '→' },
  { id: 'worsening', label: 'يتراجع', icon: '↘' },
];

/** ما يعنيه كل مربع بلغة القرار. */
const MEANING: Record<string, { text: string; tone: 'good' | 'info' | 'warn' | 'bad' | 'flat' }> = {
  'weak-improving': { text: 'تحدٍّ يتحسن — ادعم المسار', tone: 'info' },
  'weak-stable': { text: 'تحدٍّ راسخ — يحتاج تدخلًا', tone: 'bad' },
  'weak-worsening': { text: 'الأخطر — تدهور من قاعدة ضعيفة', tone: 'bad' },
  'mid-improving': { text: 'فرصة — المجال مفتوح للتقدم', tone: 'info' },
  'mid-stable': { text: 'محايد', tone: 'flat' },
  'mid-worsening': { text: 'يستوجب المراقبة', tone: 'warn' },
  'strong-improving': { text: 'ميزة تتعزز', tone: 'good' },
  'strong-stable': { text: 'ميزة قائمة — حافظ عليها', tone: 'good' },
  'strong-worsening': { text: 'قوة تتآكل — تحرّك قبل فوات الأوان', tone: 'warn' },
};

export function PositionMatrix({ card, onPick }: { card: Scorecard; onPick: (cell: { col: Col; row: Row } | null) => void; picked?: string | null }) {
  const colOf = (i: ScorecardItem): Col => (i.world.percentile < card.thresholds.weak_until ? 'weak' : i.world.percentile >= card.thresholds.strong_from ? 'strong' : 'mid');
  const rated = card.items.filter((i) => i.trend);
  const cell = (c: Col, r: Row) => rated.filter((i) => colOf(i) === c && i.trend === r).sort((a, b) => b.world.percentile - a.world.percentile);

  return (
    <div className="matrix-wrap">
      <div className="matrix" role="table" aria-label="مصفوفة الموقع والاتجاه">
        <div className="matrix-corner" aria-hidden="true">الاتجاه ↓ · الموقع العالمي →</div>
        {COLS.map((c) => (
          <div key={c.id} className="matrix-colhead" role="columnheader">
            {c.label}
          </div>
        ))}
        {ROWS.map((r) => (
          <div key={r.id} className="matrix-row" role="row">
            <div className="matrix-rowhead" role="rowheader">
              <span aria-hidden="true">{r.icon}</span> {r.label}
            </div>
            {COLS.map((c) => {
              const items = cell(c.id, r.id);
              const m = MEANING[`${c.id}-${r.id}`];
              return (
                <button key={c.id} className={`matrix-cell tone-${m.tone}`} role="cell" onClick={() => onPick(items.length ? { col: c.id, row: r.id } : null)} disabled={items.length === 0}>
                  <span className="matrix-count num">{items.length}</span>
                  <span className="matrix-meaning">{m.text}</span>
                  <span className="matrix-names">
                    {items.slice(0, 3).map((i) => i.name_ar).join(' · ')}
                    {items.length > 3 ? ' …' : ''}
                  </span>
                </button>
              );
            })}
          </div>
        ))}
      </div>
    </div>
  );
}

export type MatrixCell = { col: Col; row: Row };

export function inCell(card: Scorecard, i: ScorecardItem, cell: MatrixCell): boolean {
  const col: Col = i.world.percentile < card.thresholds.weak_until ? 'weak' : i.world.percentile >= card.thresholds.strong_from ? 'strong' : 'mid';
  return col === cell.col && i.trend === cell.row;
}
