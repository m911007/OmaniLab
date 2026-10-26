import { useEffect, useMemo, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { entityName } from '@/core/format';
import { useSearch } from '@/core/hooks';
import { NAV } from './nav';

/** يوحّد الحروف العربية كما يفعل الخادم: أ/إ/آ ← ا، ى ← ي، ة ← ه، ويحذف التشكيل والتطويل. */
export function normaliseArabic(text: string): string {
  return text
    .toLowerCase()
    .trim()
    .replace(/[أإآ]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ة/g, 'ه')
    .replace(/[ـً-ْ]/g, '');
}

interface Hit {
  group: 'صفحات' | 'مؤشرات' | 'دول';
  label: string;
  hint?: string;
  to: string;
}

const PAGES = NAV.flatMap((g) => g.items.map((i) => ({ label: i.label, section: g.title, to: i.to })));

/** بحث شامل في الصفحات والمؤشرات والدول (Ctrl/⌘+K)، بالعربية أو الإنجليزية، ويُتنقَّل فيه بلوحة المفاتيح. */
export function SearchPalette() {
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState('');
  const [active, setActive] = useState(0);
  const input = useRef<HTMLInputElement>(null);
  const navigate = useNavigate();
  const { data, isFetching } = useSearch(open ? q : '');

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        setOpen((v) => !v);
      }
    };
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, []);

  useEffect(() => {
    if (open) setTimeout(() => input.current?.focus(), 0);
    else setQ('');
  }, [open]);

  const hits = useMemo<Hit[]>(() => {
    const term = normaliseArabic(q);
    if (term.length < 2) return [];
    const pages: Hit[] = PAGES.filter((p) => normaliseArabic(p.label).includes(term)).slice(0, 5).map((p) => ({ group: 'صفحات', label: p.label, hint: p.section, to: p.to }));
    const inds: Hit[] = (data?.indicators ?? []).slice(0, 10).map((i) => ({ group: 'مؤشرات', label: i.name_ar, hint: `${i.dataset}${i.omn ? '' : ' · لا بيانات لعُمان'}`, to: `/indicator/${i.key}` }));
    const ents: Hit[] = (data?.entities ?? []).map((e) => ({ group: 'دول', label: entityName(e), hint: e.code, to: `/world/markets/${e.code}` }));
    return [...pages, ...inds, ...ents];
  }, [q, data]);

  useEffect(() => setActive(0), [q]);

  const go = (h: Hit | undefined) => {
    if (!h) return;
    setOpen(false);
    navigate(h.to);
  };

  return (
    <>
      <button type="button" className="btn search-trigger" onClick={() => setOpen(true)} aria-label="بحث (Ctrl+K)">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
        <span className="search-trigger-text">ابحث عن مؤشر أو صفحة أو دولة</span>
        <kbd dir="ltr">⌘K</kbd>
      </button>
      {open && (
        <div className="palette-backdrop" onClick={() => setOpen(false)} role="presentation">
          <div className="palette" role="dialog" aria-modal="true" aria-label="بحث شامل" onClick={(e) => e.stopPropagation()}>
            <input
              ref={input}
              type="search"
              className="palette-input"
              placeholder="مثال: التضخم، الدين، السعودية، inflation"
              value={q}
              onChange={(e) => setQ(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Escape') setOpen(false);
                if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(a + 1, hits.length - 1)); }
                if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)); }
                if (e.key === 'Enter') go(hits[active]);
              }}
              aria-controls="palette-results"
              aria-activedescendant={hits[active] ? `hit-${active}` : undefined}
            />
            <ul id="palette-results" className="palette-results" role="listbox">
              {q.trim().length < 2 && <li className="muted palette-empty">اكتب حرفين على الأقل.</li>}
              {q.trim().length >= 2 && hits.length === 0 && <li className="muted palette-empty">{isFetching ? 'جارٍ البحث…' : 'لا نتائج.'}</li>}
              {hits.map((h, i) => (
                <li key={`${h.group}-${h.to}`} id={`hit-${i}`} role="option" aria-selected={i === active} className={`palette-hit${i === active ? ' active' : ''}`} onMouseEnter={() => setActive(i)} onClick={() => go(h)}>
                  {(i === 0 || hits[i - 1].group !== h.group) && <span className="palette-group">{h.group}</span>}
                  <span className="palette-label">{h.label}</span>
                  {h.hint && <small className="muted">{h.hint}</small>}
                </li>
              ))}
            </ul>
            <p className="palette-foot muted">↑↓ للتنقل · Enter للفتح · Esc للإغلاق · <a href="/indicators" onClick={(e) => { e.preventDefault(); setOpen(false); navigate(`/indicators?q=${encodeURIComponent(q)}`); }}>كل النتائج في قاموس المؤشرات</a></p>
          </div>
        </div>
      )}
    </>
  );
}
