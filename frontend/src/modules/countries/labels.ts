export const REGION_AR: [prefix: string, label: string][] = [
  ['East Asia', 'شرق آسيا والمحيط الهادئ'],
  ['Europe', 'أوروبا وآسيا الوسطى'],
  ['Latin America', 'أمريكا اللاتينية والكاريبي'],
  ['Middle East', 'الشرق الأوسط وشمال أفريقيا'],
  ['North America', 'أمريكا الشمالية'],
  ['South Asia', 'جنوب آسيا'],
  ['Sub-Saharan', 'أفريقيا جنوب الصحراء'],
];

export const INCOME_AR: [prefix: string, label: string, order: number][] = [
  ['High income', 'دخل مرتفع', 1],
  ['Upper middle', 'دخل فوق المتوسط', 2],
  ['Lower middle', 'دخل دون المتوسط', 3],
  ['Low income', 'دخل منخفض', 4],
];

export const regionLabel = (r: string | null): string => (r ? (REGION_AR.find(([p]) => r.startsWith(p))?.[1] ?? r) : '—');
export const incomeLabel = (r: string | null): string => (r ? (INCOME_AR.find(([p]) => r.startsWith(p))?.[1] ?? r) : '—');

/** علم الدولة من رمز ISO2 (أحرف المؤشر الإقليمي). */
export const flagOf = (iso2: string | null): string => (iso2 && /^[A-Z]{2}$/.test(iso2) ? String.fromCodePoint(...[...iso2].map((c) => 127397 + c.charCodeAt(0))) : '🏳️');
