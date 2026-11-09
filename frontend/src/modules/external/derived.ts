export type Yearly = Map<string, number>;

/** تغطية الاحتياطيات للواردات بالأشهر: الاحتياطيات (مليون ريال) ÷ متوسط الواردات الشهري (الواردات السلعية فوب بالموجب). */
export function importCoverMonths(reservesOmr: Yearly, importsFob: Yearly): Yearly {
  return new Map([...reservesOmr].filter(([y]) => importsFob.has(y) && Math.abs(importsFob.get(y)!) > 0).map(([y, r]) => [y, r / (Math.abs(importsFob.get(y)!) / 12)]));
}

/** حصة الهيدروكربون (خام + مشتقات + غاز) من الصادرات السلعية فوب %. */
export function hydrocarbonExportShare(crude: Yearly, refined: Yearly, gas: Yearly, fob: Yearly): Yearly {
  return new Map([...fob].filter(([y, v]) => v > 0 && crude.has(y) && refined.has(y) && gas.has(y)).map(([y, v]) => [y, ((crude.get(y)! + refined.get(y)! + gas.get(y)!) / v) * 100]));
}

/** صافي مركز الاستثمار الدولي = الأصول − الالتزامات (نفس الوحدة). */
export function netPosition(assets: Yearly, liabilities: Yearly): Yearly {
  return new Map([...assets].filter(([y]) => liabilities.has(y)).map(([y, v]) => [y, v - liabilities.get(y)!]));
}
