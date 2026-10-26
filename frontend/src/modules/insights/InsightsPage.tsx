import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { Segmented } from '@/core/components/Segmented';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useScorecard } from '@/core/hooks';
import { CLASS_META, GROUP_LABEL, type InsightGroup } from './classes';
import { groupOf, headlines } from './headlines';
import { IndicatorRow } from './IndicatorRow';
import { ThemeProfile } from './ThemeProfile';
import { GlobalIndicesSummary } from './GlobalIndicesSummary';
import { inCell, PositionMatrix, type MatrixCell } from './PositionMatrix';
import { Priorities } from './Priorities';

type Filter = InsightGroup | 'all';

const ORDER: InsightGroup[] = ['challenge', 'opportunity', 'watch', 'strength', 'neutral'];

export default function InsightsPage() {
  const { data: card, isLoading, error } = useScorecard();
  const [filter, setFilter] = useState<Filter>('all');
  const [theme, setTheme] = useState<string>('all');
  const [cell, setCell] = useState<MatrixCell | null>(null);

  const items = useMemo(() => (card?.items ?? []).filter((i) => (theme === 'all' || i.theme === theme) && (filter === 'all' || groupOf(i) === filter) && (!cell || (card && inCell(card, i, cell)))), [card, filter, theme, cell]);
  const groups = useMemo(
    () => ORDER.map((g) => ({ g, rows: items.filter((i) => groupOf(i) === g).sort((a, b) => (g === 'strength' || g === 'neutral' || g === 'opportunity' ? b.world.percentile - a.world.percentile : a.world.percentile - b.world.percentile)) })).filter((x) => x.rows.length > 0),
    [items],
  );

  const total = card?.items.length ?? 0;
  const count = (g: InsightGroup) => card?.items.filter((i) => groupOf(i) === g).length ?? 0;
  const h = card ? headlines(card) : null;

  return (
    <>
      <PageHead
        title="بطاقة الأداء: التحديات والفرص"
        lead="أين تتقدم عُمان وأين تتأخر، وماذا نفعل؟ كل مؤشر يُقارَن بدول العالم والخليج وبمستواه قبل خمس سنوات، ويُصنَّف آليًا من البيانات المعتمدة: لا تقدير شخصي ولا رقم بلا مصدر."
      />

      <div className="no-print" style={{ marginBottom: 12 }}>
        <button className="btn" onClick={() => window.print()}>
          طباعة / حفظ PDF
        </button>
      </div>

      {isLoading && <Loading height={360} />}
      {error && <ErrorState error={error} />}

      {card && h && (
        <>
          <div className="grid cols-4 insight-tiles">
            {(['strength', 'opportunity', 'challenge', 'watch'] as const).map((g) => (
              <button key={g} className={`card tile tile-${g}`} aria-pressed={filter === g} onClick={() => setFilter(filter === g ? 'all' : g)}>
                <span className="stat-label">{GROUP_LABEL[g]}</span>
                <span className="stat-value num">{formatNumber(count(g), 0)}</span>
                <span className="stat-meta">من {formatNumber(total, 0)} مؤشرًا · اضغط للتصفية</span>
              </button>
            ))}
          </div>

          <section className="card narrative" aria-label="الصورة في سطر">
            <p>
              من <strong className="num">{formatNumber(total, 0)}</strong> مؤشرًا قابلًا للمقارنة: <strong className="num">{formatNumber(count('strength'), 0)}</strong> نقطة قوة، <strong className="num">{formatNumber(count('challenge'), 0)}</strong> تحدٍّ قائم، <strong className="num">{formatNumber(count('opportunity'), 0)}</strong> فرصة أو مسار يتحسن، و<strong className="num">{formatNumber(count('watch'), 0)}</strong> تحت المراقبة. الأولوية الآن:{' '}
              <strong>{h.challenges[0]?.name_ar ?? 'لا يوجد تحدٍّ حرج'}</strong>
              {h.strengths[0] ? <>، والميزة الأبرز: <strong>{h.strengths[0].name_ar}</strong></> : null}.
            </p>
          </section>

          <section className="section" aria-labelledby="matrix-h">
            <h2 id="matrix-h">خريطة الموقع والاتجاه</h2>
            <p className="lead">كل مؤشر في مربع واحد بحسب موقعه بين دول العالم (يمينًا الأفضل) واتجاهه خلال {formatNumber(card.thresholds.baseline_years, 0)} سنوات (الأعلى يتحسن). اضغط مربعًا لعرض مؤشراته في القائمة أدناه.</p>
            <PositionMatrix card={card} onPick={(c) => { setCell(c); setFilter('all'); setTheme('all'); if (c) { document.getElementById('detail-h')?.scrollIntoView({ behavior: 'smooth' }); } }} />
          </section>

          <section className="section" aria-labelledby="prio-h">
            <h2 id="prio-h">أولويات العمل</h2>
            <p className="lead">أكثر المؤشرات إلحاحًا: الموقع العالمي الأضعف، والاتجاه إلى التراجع، والبعد عن أفضل جار خليجي ترفع الأولوية.</p>
            <Priorities card={card} />
          </section>

          <section className="section" aria-labelledby="gi-h">
            <h2 id="gi-h">ماذا تقول المؤشرات الدولية؟</h2>
            <p className="lead">من 25 مؤشرًا تصدرها منظمات عالمية. <Link to="/indices">عرض الكل ←</Link></p>
            <GlobalIndicesSummary />
          </section>

          <section className="card brief" aria-label="الخلاصة">
            <h2>الخلاصة التنفيذية</h2>
            <div className="brief-grid">
              <BriefList title="أقوى ما تتميز به عُمان" items={h.strengths} />
              <BriefList title="أشد التحديات" items={h.challenges} />
              <BriefList title="فرص صاعدة" items={h.opportunities} />
              <BriefList title="يستوجب المراقبة" items={h.watch} />
            </div>
          </section>

          <section className="section" aria-labelledby="themes-h">
            <h2 id="themes-h">الملف العام حسب المحور</h2>
            <p className="lead">متوسط موقع عُمان بين دول العالم في مؤشرات كل محور (100 = الأفضل عالميًا، 50 = الوسيط العالمي). اضغط محورًا لعرض مؤشراته فقط.</p>
            <div className="card">
              <ThemeProfile themes={card.themes} selected={theme} onSelect={(t) => setTheme(theme === t ? 'all' : t)} />
            </div>
          </section>

          <section className="section" aria-labelledby="detail-h">
            <div className="card-head">
              <h2 id="detail-h">المؤشرات بالتفصيل</h2>
              {cell && <button className="btn" onClick={() => setCell(null)}>إلغاء تصفية الخريطة ✕</button>}
              <Segmented
                label="تصفية التصنيف"
                value={filter}
                onChange={setFilter}
                options={[{ value: 'all', label: 'الكل' }, ...(['challenge', 'opportunity', 'watch', 'strength'] as const).map((g) => ({ value: g, label: GROUP_LABEL[g] }))]}
              />
            </div>
            {groups.length === 0 && <Empty />}
            {groups.map(({ g, rows }) => (
              <div key={g} className="insight-group">
                <h3>
                  {GROUP_LABEL[g]} <span className="muted num">({rows.length})</span>
                </h3>
                <ul className="ind-list card">
                  {rows.map((i) => (
                    <IndicatorRow key={i.key} item={i} thresholds={card.thresholds} />
                  ))}
                </ul>
              </div>
            ))}
          </section>

          <section className="section card method" aria-labelledby="method-h">
            <h2 id="method-h">كيف يُحسب التصنيف؟</h2>
            <ul>
              <li>
                <strong>الموقع:</strong> نسبة دول العالم التي تتفوق عليها عُمان في المؤشر، بعد توجيهه (الأعلى أفضل أو الأدنى أفضل أو الأقرب إلى هدف). ضمن أفضل {formatNumber(100 - card.thresholds.strong_from, 0)}% = قوي، وضمن أضعف {formatNumber(card.thresholds.weak_until, 0)}% = ضعيف.
              </li>
              <li>
                <strong>الاتجاه:</strong> تغيّر المؤشر خلال {formatNumber(card.thresholds.baseline_years, 0)} سنوات مقيسًا بتشتت الدول؛ تغيّر أقل من 10% من التشتت يُعد ثباتًا.
              </li>
              <li>
                <strong>التصنيف:</strong> {Object.values(CLASS_META).map((m) => `${m.label} — ${m.hint}`).join(' · ')}
              </li>
              <li>
                <strong>حدود القراءة:</strong> الترتيب مقارنة إحصائية لا حكمًا على السياسات؛ بعض المؤشرات يسبق نشرها بسنة أو أكثر، وتُعرض سنة كل قيمة. مؤشرات صندوق النقد تقتصر على السنوات الفعلية دون التوقعات.
              </li>
            </ul>
          </section>
        </>
      )}
    </>
  );
}

function BriefList({ title, items }: { title: string; items: ReturnType<typeof headlines>['strengths'] }) {
  return (
    <div>
      <h3>{title}</h3>
      {items.length === 0 ? (
        <p className="muted">لا يوجد.</p>
      ) : (
        <ul>
          {items.map((i) => (
            <li key={i.key}>
              {i.name_ar} <span className="muted">— المرتبة <span className="num">{i.world.rank}</span> من <span className="num">{i.world.of}</span></span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
