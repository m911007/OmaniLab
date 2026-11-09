/** مقاييس المالية العامة المشتقة من سلاسل الحساب الختامي (مليار ر.ع) وسعر النفط؛ كلها حسابات واجهة نقية موسومة «مشتق». */
export type Yearly = Map<string, number>;

const at = (m: Yearly, y: string): number | undefined => m.get(y);
const years = (...ms: Yearly[]): string[] => [...ms[0].keys()].filter((y) => ms.every((m) => m.has(y))).sort();

/** حصة النفط والغاز من إجمالي الإيراد % */
export function hydrocarbonShare(oil: Yearly, gas: Yearly, total: Yearly): Yearly {
  return new Map(years(oil, gas, total).filter((y) => total.get(y)! > 0).map((y) => [y, ((oil.get(y)! + gas.get(y)!) / total.get(y)!) * 100]));
}

/** الرصيد دون إيرادات النفط والغاز (مليار ر.ع): الميزانية لو غاب الهيدروكربون. */
export function nonHydrocarbonBalance(balance: Yearly, oil: Yearly, gas: Yearly): Yearly {
  return new Map(years(balance, oil, gas).map((y) => [y, balance.get(y)! - oil.get(y)! - gas.get(y)!]));
}

/**
 * سعر التعادل التقريبي للنفط ($/برميل): السعر الذي يجعل الرصيد صفرًا لو تغيّر إيراد النفط وحده بنسبة السعر
 * (حجم إنتاج ثابت وبقية الإيرادات والإنفاق ثابتة): السعر × (1 − الرصيد ÷ إيراد النفط).
 */
export function breakevenOilPrice(price: Yearly, balance: Yearly, oil: Yearly): Yearly {
  return new Map(years(price, balance, oil).filter((y) => oil.get(y)! > 0).map((y) => [y, price.get(y)! * (1 - balance.get(y)! / oil.get(y)!)]));
}

/** الفعلي ÷ المعتمد % (فوق 100 = تجاوز المعتمد). */
export function executionRate(actual: Yearly, approved: Yearly): Yearly {
  return new Map(years(actual, approved).filter((y) => approved.get(y)! > 0).map((y) => [y, (actual.get(y)! / approved.get(y)!) * 100]));
}

/** نمو سنوي % (من السنة السابقة المتاحة فعلًا، فلا يُقفز فوق فجوة مثل 2021). */
export function growth(m: Yearly): Yearly {
  const ys = [...m.keys()].sort();
  const out: Yearly = new Map();
  for (let i = 1; i < ys.length; i++) {
    const prev = m.get(ys[i - 1])!;
    if (Number(ys[i]) - Number(ys[i - 1]) === 1 && prev !== 0) out.set(ys[i], ((m.get(ys[i])! - prev) / Math.abs(prev)) * 100);
  }
  return out;
}

/** حصة كل مكوّن % من مجموع المكوّنات في السنة (للمكدّس 100%). */
export function sharesOf(parts: Yearly[], year: string): number[] | null {
  const vals = parts.map((p) => at(p, year));
  if (vals.some((v) => v === undefined)) return null;
  const total = (vals as number[]).reduce((a, b) => a + b, 0);
  return total > 0 ? (vals as number[]).map((v) => (v / total) * 100) : null;
}

/** آخر سنة مشتركة بين السلاسل. */
export function lastCommonYear(...ms: Yearly[]): string | null {
  const ys = years(...ms);
  return ys.length ? ys[ys.length - 1] : null;
}

export interface Assessment {
  tone: 'good' | 'warning' | 'critical';
  text: string;
}

const f = (v: number, d = 1) => v.toLocaleString('en-US', { minimumFractionDigits: d, maximumFractionDigits: d });

/** جملة تقييم من قوالب ثابتة (لا ذكاء اصطناعي): تقرأ الرصيد والاعتماد على الهيدروكربون والسعر مقابل التعادل. */
export function assess(p: { year: string; balance: number; hydroShare: number; price?: number; breakeven?: number }): Assessment {
  const bal = p.balance >= 0 ? `فائض ${f(p.balance, 2)} مليار ر.ع` : `عجز ${f(-p.balance, 2)} مليار ر.ع`;
  const dep = `وتأتي ${f(p.hydroShare, 0)}% من الإيرادات من النفط والغاز`;
  const be = p.price !== undefined && p.breakeven !== undefined
    ? p.price >= p.breakeven ? `، وسعر النفط (${f(p.price, 0)}$) أعلى من سعر التعادل التقريبي (${f(p.breakeven, 0)}$)` : `، وسعر النفط (${f(p.price, 0)}$) دون سعر التعادل التقريبي (${f(p.breakeven, 0)}$)`
    : '';
  const tone: Assessment['tone'] = p.balance >= 0 ? 'good' : p.balance > -1 ? 'warning' : 'critical';
  return { tone, text: `في ${p.year} سجّلت المالية العامة ${bal}، ${dep}${be}.` };
}
