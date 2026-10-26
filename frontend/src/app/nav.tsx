import type { ReactNode } from 'react';

const svg = (d: string): ReactNode => (
  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
    <path d={d} />
  </svg>
);

export interface NavItem {
  to: string;
  label: string;
  icon: ReactNode;
  end?: boolean;
  /** يظهر لمشرف مسجَّل الدخول فقط */
  adminOnly?: boolean;
}

export interface NavGroup {
  title: string;
  items: NavItem[];
}

export const NAV: NavGroup[] = [
  {
    title: 'الموجز',
    items: [
      { to: '/', label: 'الموجز التنفيذي', icon: svg('M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'), end: true },
      { to: '/scorecard', label: 'بطاقة الأداء', icon: svg('M12 3v3m0 12v3M5 12H2m20 0h-3M6.3 6.3L4.2 4.2m15.6 15.6l-2.1-2.1M17.7 6.3l2.1-2.1M4.2 19.8l2.1-2.1M12 8a4 4 0 100 8 4 4 0 000-8z') },
      { to: '/impact', label: 'سلاسل الأثر', icon: svg('M5 6h4v4H5zM15 14h4v4h-4zM9 8h3a2 2 0 012 2v4a2 2 0 002 2M12 4l2 2-2 2') },
      { to: '/indices', label: 'المؤشرات العالمية', icon: svg('M12 15a6 6 0 100-12 6 6 0 000 12zM8.2 13.9L7 22l5-3 5 3-1.2-8.1') },
    ],
  },
  {
    title: 'الاقتصاد الكلي',
    items: [
      { to: '/growth', label: 'النمو والناتج', icon: svg('M3 17l6-6 4 4 8-8M15 7h6v6') },
      { to: '/prices-living', label: 'الأسعار والمعيشة', icon: svg('M4 7h16l-1.5 11h-13zM9 7a3 3 0 0 1 6 0') },
      { to: '/fiscal', label: 'المالية العامة', icon: svg('M4 6h16M4 12h16M4 18h10') },
      { to: '/money', label: 'النقد والمصارف', icon: svg('M12 3v18M17 7H9.5a2.5 2.5 0 000 5h5a2.5 2.5 0 010 5H7') },
      { to: '/external', label: 'القطاع الخارجي', icon: svg('M3 7h13l-3-3m3 3l-3 3M21 17H8l3-3m-3 3l3 3') },
    ],
  },
  {
    title: 'السكان والمجتمع',
    items: [
      { to: '/population', label: 'السكان', icon: svg('M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8zM22 21v-2a4 4 0 00-3-3.9M16 3.1a4 4 0 010 7.8') },
      { to: '/labour', label: 'سوق العمل والتعمين', icon: svg('M3 7h18v13H3zM8 7V4h8v3M3 13h18') },
      { to: '/human', label: 'التعليم والصحة والتنمية', icon: svg('M12 2a4 4 0 100 8 4 4 0 000-8zM4 22v-2a8 8 0 0116 0v2') },
      { to: '/governance', label: 'الحوكمة والرقمنة', icon: svg('M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6z') },
    ],
  },
  {
    title: 'القطاعات',
    items: [
      { to: '/energy', label: 'الطاقة والنفط', icon: svg('M13 2L4 14h7l-1 8 9-12h-7z') },
      { to: '/minerals', label: 'المعادن والخامات', icon: svg('M12 3l8 6-3 11H7L4 9zM4 9h16M12 3v17') },
      { to: '/agriculture', label: 'الزراعة والثروة السمكية والمياه', icon: svg('M12 21V9M12 9c0-3 2-5 5-5 0 3-2 5-5 5zM12 13c0-3-2-5-5-5 0 3 2 5 5 5z') },
      { to: '/maritime', label: 'الملاحة والموانئ', icon: svg('M3 17c3 3 6 3 9 0s6-3 9 0M12 3v9M8 8l4-5 4 5') },
      { to: '/tourism', label: 'السياحة', icon: svg('M12 21s-7-6-7-11a7 7 0 0114 0c0 5-7 11-7 11zM12 12a2 2 0 100-4 2 2 0 000 4z') },
      { to: '/digital', label: 'الاقتصاد الرقمي', icon: svg('M4 5h16v11H4zM8 20h8M12 16v4') },
    ],
  },
  {
    title: 'الأعمال والاستثمار',
    items: [
      { to: '/investment', label: 'الاستثمار', icon: svg('M3 17l6-6 4 4 8-8M15 7h6v6') },
      { to: '/companies', label: 'الشركات العُمانية', icon: svg('M3 21h18M5 21V7l7-4 7 4v14M9 9h1m4 0h1M9 13h1m4 0h1M9 17h1m4 0h1') },
      { to: '/local-content', label: 'المحتوى المحلي', icon: svg('M12 21V9M12 9c0-3 2-5 5-5 0 3-2 5-5 5zM5 21h14') },
      { to: '/market-intel', label: 'ذكاء الأسواق', icon: svg('M12 3l2.1 5.2L19 10l-4.9 1.8L12 17l-2.1-5.2L5 10l4.9-1.8zM19 17l.8 2 2 .8-2 .8-.8 2-.8-2-2-.8 2-.8z'), end: true },
      { to: '/market-intel/chains', label: 'سلاسل القيمة', icon: svg('M5 6h4v4H5zM15 6h4v4h-4zM10 16h4v4h-4zM9 8h6M7 10l4 6M17 10l-4 6') },
      { to: '/institutions', label: 'المؤسسات والموارد', icon: svg('M3 21h18M5 21V10l7-6 7 6v11M9 21v-6h6v6') },
    ],
  },
  {
    title: 'عُمان والعالم',
    items: [
      { to: '/gcc', label: 'دول الخليج', icon: svg('M12 3l9 5v8l-9 5-9-5V8z') },
      { to: '/peers', label: 'دول المقارنة', icon: svg('M3 12h18M8 7l-5 5 5 5M16 7l5 5-5 5') },
      { to: '/world', label: 'دول وأسواق العالم', icon: svg('M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3a14 14 0 010 18M12 3a14 14 0 000 18') },
      { to: '/global', label: 'الاقتصاد العالمي', icon: svg('M12 21a9 9 0 100-18 9 9 0 000 18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18') },
      { to: '/commodities', label: 'النفط والسلع العالمية', icon: svg('M12 2s6 6.5 6 11a6 6 0 01-12 0c0-4.5 6-11 6-11z') },
      { to: '/world-companies', label: 'الشركات العالمية', icon: svg('M3 21h18M6 21V9h5v12M13 21V3h5v18M8 12h1M8 16h1M15 7h1M15 11h1M15 15h1') },
    ],
  },
  {
    title: 'المنصة',
    items: [
      { to: '/settings', label: 'مفاتيح الخدمات', adminOnly: true, icon: svg('M21 2l-2 2m-7.6 7.6a5.5 5.5 0 11-7.8 7.8 5.5 5.5 0 017.8-7.8zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4') },
      { to: '/indicators', label: 'قاموس المؤشرات', icon: svg('M12 15a6 6 0 100-12 6 6 0 000 12zM8.2 13.9L7 22l5-3 5 3-1.2-8.1') },
      { to: '/methodology', label: 'المنهجية', icon: svg('M5 12l4 4 10-10') },
      { to: '/status', label: 'حالة البيانات', icon: svg('M5 12l4 4 10-10') },
    ],
  },
];
