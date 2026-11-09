import type { ReactNode } from 'react';

/** مسارات أيقونات خطية (24×24) تُختار بكلمات من عنوان المؤشر أو مفتاحه؛ الافتراضي رسم بياني صغير. */
const PATHS: Record<string, ReactNode> = {
  chart: <path d="M4 19V5M4 19h16M8 15v-3M12 15V8M16 15v-5" />,
  building: <path d="M5 20V5a1 1 0 0 1 1-1h8a1 1 0 0 1 1 1v15M15 10h3a1 1 0 0 1 1 1v9M3 20h18M8.5 8h3M8.5 12h3M8.5 16h3" />,
  money: <path d="M12 3v18M16 7.5c-.6-1-1.9-1.5-4-1.5-2.4 0-3.8 1-3.8 2.6 0 3.7 7.8 1.6 7.8 5.5 0 1.6-1.5 2.6-4 2.6-2.2 0-3.6-.6-4.3-1.7" />,
  people: <path d="M9 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM2.5 20a6.5 6.5 0 0 1 13 0M16 4.3a3.5 3.5 0 0 1 0 6.4M18 14a6.5 6.5 0 0 1 3.5 6" />,
  oil: <path d="M12 3s6 6.3 6 11a6 6 0 0 1-12 0c0-4.7 6-11 6-11ZM9.5 14.5a2.6 2.6 0 0 0 2.5 2.5" />,
  trade: <path d="M4 8h14l-3-3M20 16H6l3 3" />,
  price: <path d="M3 12V4h8l10 10-8 8L3 12ZM7.5 8.5h.01" />,
  home: <path d="M3 11 12 4l9 7M5.5 9.5V20h13V9.5M10 20v-5h4v5" />,
  work: <path d="M4 8h16v11H4zM9 8V5.5A1.5 1.5 0 0 1 10.5 4h3A1.5 1.5 0 0 1 15 5.5V8M4 13h16" />,
  leaf: <path d="M5 19C5 10 10 5 20 4c0 10-5 15-14 15ZM5 19c2-4 5-7 9-9" />,
  ship: <path d="M3 17c2 1.5 4 1.5 6 0s4-1.5 6 0 4 1.5 6 0M5 14l1.5-6h11L19 14M12 8V4h3" />,
  bolt: <path d="M13 3 5 13.5h6L10 21l8-10.5h-6L13 3Z" />,
  drop: <path d="M12 3s6 6 6 10.5a6 6 0 0 1-12 0C6 9 12 3 12 3Z" />,
  school: <path d="m3 9 9-5 9 5-9 5-9-5ZM7 11.5V16c0 1.5 2.2 3 5 3s5-1.5 5-3v-4.5M21 9v6" />,
  health: <path d="M12 20s-8-4.7-8-10.5A4.5 4.5 0 0 1 12 7a4.5 4.5 0 0 1 8 2.5C20 15.3 12 20 12 20ZM12 10v4M10 12h4" />,
};

const RULES: [RegExp, keyof typeof PATHS][] = [
  [/شركة|شركات|منشأ|سجل تجاري|مؤسس/u, 'building'],
  [/نفط|خام|غاز|برميل|طاقة/u, 'oil'],
  [/كهرب|ميغاواط|ميجاواط/u, 'bolt'],
  [/مياه|مائي|ماء|أمطار/u, 'drop'],
  [/سكان|عمالة|عمّال|قوى عاملة|وافد|مواطن|ولادات|وفيات/u, 'people'],
  [/بطالة|توظيف|وظائف|عمل|أجور|رواتب/u, 'work'],
  [/تضخم|أسعار|سعر|رقم قياسي/u, 'price'],
  [/تجار|صادرات|واردات|ميزان/u, 'trade'],
  [/ميناء|موانئ|سفن|ملاحة|عبور|ناقلات|حاويات/u, 'ship'],
  [/زراع|محصول|ماشية|سمك|أسماك|غذاء|خضروات/u, 'leaf'],
  [/مساكن|سكن|عقار|إسكان|بناء/u, 'home'],
  [/تعليم|طلاب|مدارس|جامع/u, 'school'],
  [/صحة|مستشف|أطباء|أسرّة/u, 'health'],
  [/دين|عجز|إيراد|إنفاق|ميزانية|ناتج|دخل|استثمار|احتياط|ريال|قروض|ائتمان|ودائع|نقد|مال/u, 'money'],
];

export const iconFor = (label: string): keyof typeof PATHS => RULES.find(([re]) => re.test(label))?.[1] ?? 'chart';

/** أيقونة صغيرة في مربع مدوّر بلون التمييز، تدل على موضوع المؤشر. */
export function IndicatorIcon({ label }: { label: string }) {
  return (
    <span className="ind-icon" aria-hidden="true" data-icon={iconFor(label)}>
      <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
        {PATHS[iconFor(label)]}
      </svg>
    </span>
  );
}

/** سهم صعود/هبوط خطي لشارة التغيّر. */
export function TrendArrow({ direction }: { direction: 'up' | 'down' | 'flat' }) {
  return (
    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {direction === 'up' && <path d="M4 16 10 10l4 4 6-7M15 7h5v5" />}
      {direction === 'down' && <path d="M4 8l6 6 4-4 6 7M15 17h5v-5" />}
      {direction === 'flat' && <path d="M5 12h14" />}
    </svg>
  );
}

/** عنوان بطاقة رقم مع أيقونة الموضوع. */
export function StatLabel({ children, label }: { children: ReactNode; label?: string }) {
  const text = label ?? (typeof children === 'string' ? children : '');
  return (
    <span className="stat-head">
      <IndicatorIcon label={text} />
      <span className="stat-label">{children}</span>
    </span>
  );
}

/** شارة تغيّر بارزة: سهم ونص، بلون التحسّن/التراجع. */
export function DeltaPill({ direction, tone, children, title }: { direction: 'up' | 'down' | 'flat'; tone: 'good' | 'bad' | 'flat'; children: ReactNode; title?: string }) {
  return (
    <span className={`reading-delta ${tone}`} title={title}>
      <TrendArrow direction={direction} />
      {children}
    </span>
  );
}
