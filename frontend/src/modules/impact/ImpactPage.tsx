import { useMemo, useState } from 'react';
import { PageHead } from '@/core/components/PageHead';
import { RelationRow } from '@/core/components/RelatedPanel';
import { Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { edgeBetween, NODES, STORIES, transformUnit, type RelEdge, type Story } from '@/core/relations';
import { useRelationStats, type EdgeStats } from '@/core/useRelations';

const signed = (v: number, d = 1) => `${v > 0 ? '+' : ''}${formatNumber(v, d)}`;
const unitOf = (key: string) => (NODES[key].transform === 'pct' ? '%' : ' نقطة');

/**
 * ينقل صدمة على أول السلسلة عبر حلقاتها بالميل التاريخي لكل حلقة (أثر حدّي: Δأثر = β × Δمحرّك).
 * يتوقف الانتقال عند أول حلقة غير مثبتة إحصائيًا، فلا يُبنى رقم على علاقة غير مؤكدة.
 */
export function propagate(path: readonly string[], stats: readonly EdgeStats[], shock: number): { key: string; value: number | null }[] {
  const out: { key: string; value: number | null }[] = [{ key: path[0], value: shock }];
  let current: number | null = shock;
  for (let i = 1; i < path.length; i++) {
    const s = stats.find((x) => x.edge.from === path[i - 1] && x.edge.to === path[i]);
    current = current != null && s?.usable && s.effect ? s.effect.slope * current : null;
    out.push({ key: path[i], value: current });
  }
  return out;
}

function StoryCard({ story }: { story: Story }) {
  const edges = useMemo(() => story.path.slice(1).map((k, i) => edgeBetween(story.path[i], k)).filter((e): e is RelEdge => Boolean(e)), [story]);
  const { stats, isLoading } = useRelationStats(edges);
  const first = NODES[story.path[0]];
  const step = transformUnit(first.transform);
  const [shock, setShock] = useState(step.step);
  const chain = propagate(story.path, stats, shock);

  return (
    <article className="card story-card" id={story.slug}>
      <header>
        <h3>{story.title}</h3>
        <p className="muted">{story.question}</p>
      </header>
      {isLoading ? <Loading height={160} /> : (
        <>
          <div className="story-shock">
            <label htmlFor={`shock-${story.slug}`}>إذا تغيّر <b>{first.label}</b> بمقدار</label>
            <input dir="ltr" id={`shock-${story.slug}`} type="range" min={-step.step * 3} max={step.step * 3} step={step.step / 10} value={shock} onChange={(e) => setShock(Number(e.target.value))} />
            <div className="story-ends" dir="ltr" aria-hidden="true"><span>{signed(-step.step * 3, 0)}{unitOf(first.key)}</span><span>0</span><span>{signed(step.step * 3, 0)}{unitOf(first.key)}</span></div>
            <output className="num" htmlFor={`shock-${story.slug}`}><bdi>{signed(shock)}</bdi>{unitOf(first.key)}</output>
          </div>
          <ol className="story-flow" aria-label="انتقال الأثر عبر السلسلة">
            {chain.map((n, i) => {
              const s = i > 0 ? stats.find((x) => x.edge.from === story.path[i - 1] && x.edge.to === n.key) : undefined;
              return (
                <li key={n.key} className={`story-node${n.value == null ? ' broken' : ''}`}>
                  {i > 0 && (
                    <span className={`story-link ${s?.usable ? 'ok' : 'weak'}`} title={s?.effect ? `معامل الارتباط ${formatNumber(s.effect.r, 2)} · ${s.effect.n} سنة` : undefined}>
                      {s?.usable ? `β ${formatNumber(s.effect!.slope, 2)}` : 'غير مثبت'}{s?.edge.lag ? ' · بعد سنة' : ''}
                    </span>
                  )}
                  <span className="story-label">{NODES[n.key].label}</span>
                  <b className="num">{n.value == null ? '—' : <><bdi>{signed(n.value)}</bdi>{unitOf(n.key)}</>}</b>
                </li>
              );
            })}
          </ol>
          <details className="story-details">
            <summary>تفاصيل كل حلقة وماذا نراقب الآن</summary>
            <ul className="rel-list">{stats.map((s) => <RelationRow key={`${s.edge.from}>${s.edge.to}`} s={s} />)}</ul>
          </details>
        </>
      )}
    </article>
  );
}

/** سلاسل الأثر: كيف تنتقل صدمة (نفط، فائدة أمريكية، غذاء عالمي) عبر الاقتصاد العُماني، بأحجام مقيسة من التاريخ. */
export default function ImpactPage() {
  return (
    <>
      <PageHead title="سلاسل الأثر" lead="لا يكفي الرقم وحده لاتخاذ القرار: هنا كيف ينتقل تغيّر في محرّك رئيسي عبر الاقتصاد، حلقةً بعد حلقة، بأحجام مقيسة من سنوات عُمان الفعلية. حرّك الشريط لترى الأثر، وافتح التفاصيل لمعرفة ما يرجّحه آخر تغيّر الآن." />
      <div className="story-grid">{STORIES.map((s) => <StoryCard key={s.slug} story={s} />)}</div>
      <p className="source-note">
        كل حلقة: انحدار التغيّر السنوي للأثر على التغيّر السنوي للمحرّك (منذ 2000، سنوات فعلية فقط) واختبار دلالة عند 5%. الأثر المنقول تقريب حدّي (β × التغيّر) يفترض ثبات العوامل الأخرى،
        ويتوقف عند أول حلقة غير مثبتة إحصائيًا. هذه قراءة للتاريخ تساعد على التفكير في السيناريوهات، وليست توقعًا رسميًا.
      </p>
    </>
  );
}
