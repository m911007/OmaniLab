import { useEffect, useMemo, useRef, useState } from 'react';
import { isPending, useCatalog, useProductSearch, type Catalog, type Product } from './useMarketIntel';

const RECENT_KEY = 'omanilab.mi.recent';

function loadRecent(): Product[] {
  try {
    return JSON.parse(localStorage.getItem(RECENT_KEY) ?? '[]') as Product[];
  } catch {
    return [];
  }
}

function useDebounced<T>(value: T, ms = 300): T {
  const [v, setV] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setV(value), ms);
    return () => clearTimeout(t);
  }, [value, ms]);
  return v;
}

/**
 * اختيار المنتج بثلاث طرق: بحث ذكي بقائمة منسدلة تُدار بلوحة المفاتيح (عربي/إنجليزي/رمز HS)، أو تصفح متسلسل (القسم ← الفصل ← المنتج)،
 * أو من آخر المنتجات المختارة. يغطي كل منتجات HS4 (نحو 1200) لا قائمة مختصرة.
 */
export function ProductPicker({ value, onPick, onNoMatch }: { value: Product | null; onPick: (p: Product) => void; onNoMatch?: (q: string) => void }) {
  const catalogQ = useCatalog();
  const catalog = catalogQ.data && !isPending(catalogQ.data) ? (catalogQ.data as Catalog) : null;
  const [text, setText] = useState('');
  const q = useDebounced(text.trim());
  const search = useProductSearch(q);
  const hits = search.data && !isPending(search.data) ? search.data.data : [];
  const [open, setOpen] = useState(false);
  const [active, setActive] = useState(0);
  const [section, setSection] = useState<number | ''>('');
  const [chapter, setChapter] = useState('');
  const [recent, setRecent] = useState<Product[]>(loadRecent);
  const boxRef = useRef<HTMLDivElement>(null);

  const chapters = useMemo(() => (catalog && section !== '' ? catalog.chapters.filter((c) => c.section === section) : []), [catalog, section]);
  const products = useMemo(() => (catalog && chapter ? catalog.products.filter((p) => p.hs2 === chapter) : []), [catalog, chapter]);

  useEffect(() => {
    // مزامنة القوائم مع المنتج المختار من أي طريق
    if (value?.id && catalog) {
      const p = catalog.products.find((x) => x.id === value.id);
      if (p) {
        setSection(p.section);
        setChapter(p.hs2);
      }
    }
  }, [value?.id, catalog]);

  useEffect(() => setActive(0), [q]);
  useEffect(() => {
    const close = (e: MouseEvent) => {
      if (boxRef.current && !boxRef.current.contains(e.target as Node)) setOpen(false);
    };
    document.addEventListener('mousedown', close);
    return () => document.removeEventListener('mousedown', close);
  }, []);

  const pick = (p: Product) => {
    const full = catalog?.products.find((x) => x.id === p.id) ?? p;
    onPick(full);
    setText('');
    setOpen(false);
    const next = [full, ...recent.filter((r) => r.id !== full.id)].slice(0, 8);
    setRecent(next);
    try {
      localStorage.setItem(RECENT_KEY, JSON.stringify(next));
    } catch {
      /* بلا تخزين: القائمة للجلسة فقط */
    }
  };

  const onKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (!open && (e.key === 'ArrowDown' || e.key === 'Enter')) setOpen(true);
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setActive((a) => Math.min(a + 1, Math.max(hits.length - 1, 0)));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setActive((a) => Math.max(a - 1, 0));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      if (hits[active]) pick(hits[active]);
    } else if (e.key === 'Escape') {
      setOpen(false);
    }
  };

  const sectionName = (id: number) => catalog?.sections.find((s) => s.id === id)?.name ?? '';

  return (
    <div className="mi-picker">
      <div className="mi-combobox" ref={boxRef}>
        <label className="intel-field">
          <span>ابحث عن أي منتج (بالعربية أو الإنجليزية أو رمز HS)</span>
          <input
            type="search"
            role="combobox"
            aria-expanded={open && q.length >= 2}
            aria-controls="mi-listbox"
            aria-autocomplete="list"
            aria-activedescendant={hits[active] ? `mi-opt-${hits[active].id}` : undefined}
            value={text}
            onChange={(e) => {
              setText(e.target.value);
              setOpen(true);
            }}
            onFocus={() => setOpen(true)}
            onKeyDown={onKey}
            placeholder="مثال: قمح، سيارات، ألواح شمسية، 3102"
            aria-label="بحث عن منتج"
          />
        </label>
        {open && q.length >= 2 && (
          <div className="mi-listbox" id="mi-listbox" role="listbox" aria-label="نتائج البحث">
            {search.data && isPending(search.data) && <p className="muted">جارٍ تحميل قائمة المنتجات من المصدر (أول مرة فقط)…</p>}
            {hits.map((p, i) => (
              <button key={p.id} id={`mi-opt-${p.id}`} type="button" role="option" aria-selected={i === active} className={`intel-result${i === active ? ' active' : ''}`} onMouseEnter={() => setActive(i)} onClick={() => pick(p)}>
                <strong dir="ltr">{p.hs4}</strong>
                <span>{p.name_ar}</span>
              </button>
            ))}
            {search.data && !isPending(search.data) && hits.length === 0 && (
              <div className="mi-nomatch">
                <span className="muted">لا تطابق في قائمة المنتجات.</span>
                {onNoMatch && <button type="button" className="btn" onClick={() => { onNoMatch(q); setOpen(false); }}>حدّد الرمز بالذكاء الاصطناعي</button>}
              </div>
            )}
          </div>
        )}
      </div>

      <div className="mi-cascade" role="group" aria-label="تصفح حسب التصنيف">
        <label className="intel-field">
          <span>القسم</span>
          <select value={section} onChange={(e) => { setSection(e.target.value ? Number(e.target.value) : ''); setChapter(''); }} disabled={!catalog} aria-label="قسم المنتج">
            <option value="">{catalog ? `كل الأقسام (${catalog.sections.length})` : 'جارٍ التحميل…'}</option>
            {catalog?.sections.map((s) => <option key={s.id} value={s.id}>{s.id}. {s.name} ({s.count})</option>)}
          </select>
        </label>
        <label className="intel-field">
          <span>الفصل</span>
          <select value={chapter} onChange={(e) => setChapter(e.target.value)} disabled={section === ''} aria-label="فصل المنتج">
            <option value="">{section === '' ? 'اختر القسم أولًا' : `اختر الفصل (${chapters.length})`}</option>
            {chapters.map((c) => <option key={c.hs2} value={c.hs2}>{c.hs2} — {c.name} ({c.count})</option>)}
          </select>
        </label>
        <label className="intel-field">
          <span>المنتج</span>
          <select value={value && products.some((p) => p.id === value.id) ? value.id : ''} onChange={(e) => { const p = products.find((x) => x.id === Number(e.target.value)); if (p) pick(p); }} disabled={!chapter} aria-label="المنتج من القائمة">
            <option value="">{chapter ? `اختر المنتج (${products.length})` : 'اختر الفصل أولًا'}</option>
            {products.map((p) => <option key={p.id} value={p.id}>{p.hs4} — {p.name_en}{p.commodity ? ' ★' : ''}</option>)}
          </select>
        </label>
      </div>
      {chapter && <p className="muted mi-hint">★ منتج له سعر سلعة عالمي شهري (صندوق النقد) يُعرض في التقرير.</p>}

      {recent.length > 0 && (
        <div className="intel-chips">
          <span className="muted">آخر ما اخترت:</span>
          {recent.map((r) => (
            <button key={r.id} type="button" className={`chip${value?.id === r.id ? ' good' : ''}`} onClick={() => pick(r)} title={r.name_ar}>
              <span dir="ltr">{r.hs4}</span> {r.name_en}
            </button>
          ))}
        </div>
      )}
      {value && value.section && <p className="muted mi-hint">المختار: {sectionName(value.section)} ← {value.name_ar}</p>}
    </div>
  );
}
