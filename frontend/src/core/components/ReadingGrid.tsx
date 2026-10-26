import { Children, useLayoutEffect, useRef, useState, type ReactNode } from 'react';

const GAP = 14;
const MIN_WIDTH = 220;
const MIN_WIDTH_PHONE = 150;

/**
 * يوزّع n بطاقة على عرض متاح w: أكبر عدد أعمدة يتسع بالحد الأدنى للعرض، ثم صفوف متوازنة
 * (12 بطاقة تُصبح 4×3 لا 5+5+2، و7 تُصبح 4+3)، وعرض البطاقة = العرض المتاح ÷ الأعمدة فيغطي الصف الشاشة.
 */
export function layoutCards(n: number, width: number, gap = GAP, minWidth = MIN_WIDTH): { columns: number; cardWidth: number } {
  if (n <= 0 || width <= 0) return { columns: 1, cardWidth: Math.max(0, width) };
  const maxColumns = Math.max(1, Math.min(n, Math.floor((width + gap) / (minWidth + gap))));
  const rows = Math.ceil(n / maxColumns);
  const columns = Math.ceil(n / rows);
  return { columns, cardWidth: Math.floor((width - (columns - 1) * gap) / columns) };
}

/** شبكة بطاقات القراءة: حجم موحّد يُحسب من عرض الشاشة وعدد البطاقات، والصف الأخير غير المكتمل في المنتصف. */
export function ReadingGrid({ children, className = '' }: { children: ReactNode; className?: string }) {
  const ref = useRef<HTMLDivElement>(null);
  const [width, setWidth] = useState(0);
  const count = Children.toArray(children).length;

  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const measure = () => setWidth(el.clientWidth);
    measure();
    // متصفحات قديمة وبيئة الاختبار بلا ResizeObserver: نكتفي بإعادة القياس عند تغيّر حجم النافذة
    if (typeof ResizeObserver === 'undefined') {
      window.addEventListener('resize', measure);
      return () => window.removeEventListener('resize', measure);
    }
    const ro = new ResizeObserver(measure);
    ro.observe(el);
    return () => ro.disconnect();
  }, []);

  const phone = width > 0 && width < 600;
  const { cardWidth } = layoutCards(count, width, phone ? 10 : GAP, phone ? MIN_WIDTH_PHONE : MIN_WIDTH);
  return (
    <div ref={ref} className={`reading-grid ${className}`.trim()} style={width ? ({ '--card-w': `${cardWidth}px`, gap: phone ? 10 : GAP } as React.CSSProperties) : undefined}>
      {children}
    </div>
  );
}
