import { HeadlineStrip, type TileSpec } from '@/core/components/HeadlineStrip';
import { formatNumber, formatPeriod } from '@/core/format';

const n = (v: number, d = 1) => formatNumber(v, d);

const GROWTH: TileSpec[] = [
  { indicator: 'imf.NGDP_RPCH', label: 'نمو الناتج الحقيقي', suffix: '%', goodWhen: 'up', meta: 'صندوق النقد' },
  { indicator: 'imf.NGDPD', label: 'الناتج بالأسعار الجارية', digits: 0, suffix: ' B$', goodWhen: 'up', meta: 'صندوق النقد' },
  { indicator: 'imf.NGDPDPC', label: 'نصيب الفرد من الناتج', digits: 0, suffix: ' $', goodWhen: 'up', meta: 'صندوق النقد' },
  { indicator: 'ncsi.CPI_INFLATION', label: 'التضخم السنوي', suffix: '%', goodWhen: 'down', meta: 'المركز الوطني' },
];

/** النمو والناتج: السرعة، الحجم، المستوى للفرد، وتكلفة المعيشة المرافقة. */
export function GrowthHeadline() {
  return <HeadlineStrip tiles={GROWTH} sentence={(v) => {
    const g = v['imf.NGDP_RPCH'];
    const gdp = v['imf.NGDPD'];
    if (!g || !gdp) return null;
    const trend = g.previous === null ? '' : g.value > g.previous ? `، أسرع من ${n(g.previous)}% في السنة السابقة` : `، أبطأ من ${n(g.previous)}% في السنة السابقة`;
    return `نما الناتج الحقيقي ${n(g.value)}% في ${formatPeriod(g.period)}${trend}، ويبلغ الناتج بالأسعار الجارية نحو ${n(gdp.value, 0)} مليار دولار.`;
  }} />;
}

const MONEY: TileSpec[] = [
  { indicator: 'ncsi.MON_CREDIT_PRIVATE', label: 'نمو الائتمان للقطاع الخاص', mode: 'yoy', suffix: '%', goodWhen: 'up', meta: 'سنوي، المركز الوطني' },
  { indicator: 'ncsi.MON_M2', label: 'نمو عرض النقد الواسع', mode: 'yoy', suffix: '%', meta: 'سنوي، المركز الوطني' },
  { indicator: 'ncsi.MON_RATE_LENDING_RO', label: 'سعر الإقراض بالريال', digits: 2, suffix: '%', goodWhen: 'down', meta: 'البنوك التقليدية' },
  { indicator: 'ncsi.MON_RATE_REPO', label: 'سعر الريبو', digits: 2, suffix: '%', meta: 'البنك المركزي' },
  { indicator: 'ncsi.MON_RESERVES_FX', label: 'احتياطيات النقد الأجنبي', digits: 0, suffix: ' M$', goodWhen: 'up', meta: 'البنك المركزي' },
];

/** النقد والمصارف: وتيرة الائتمان والنقد، كلفة الإقراض، وكفاية الاحتياطيات للدفاع عن الربط بالدولار. */
export function MoneyHeadline() {
  return <HeadlineStrip tiles={MONEY} from={String(new Date().getFullYear() - 3)} sentence={(v) => {
    const c = v['ncsi.MON_CREDIT_PRIVATE'];
    const r = v['ncsi.MON_RATE_LENDING_RO'];
    if (!c || !r) return null;
    return `نما الائتمان للقطاع الخاص ${n(c.value)}% على أساس سنوي حتى ${formatPeriod(c.period)}، وسعر الإقراض بالريال ${n(r.value, 2)}%${v['ncsi.MON_RATE_REPO'] ? ` مقابل ريبو ${n(v['ncsi.MON_RATE_REPO']!.value, 2)}%` : ''}.`;
  }} />;
}

const EXTERNAL: TileSpec[] = [
  { indicator: 'imf.BCA_NGDPD', label: 'الحساب الجاري', suffix: '% من الناتج', goodWhen: 'up', meta: 'صندوق النقد' },
  { indicator: 'ncsi.BOP_CURRENT_ACCOUNT', label: 'رصيد الحساب الجاري', digits: 0, suffix: ' M ر.ع', goodWhen: 'up', meta: 'ميزان المدفوعات' },
  { indicator: 'ncsi.BOP_GOODS', label: 'رصيد السلع', digits: 0, suffix: ' M ر.ع', goodWhen: 'up', meta: 'ميزان المدفوعات' },
  { indicator: 'ncsi.MON_RESERVES_FX', label: 'احتياطيات النقد الأجنبي', digits: 0, suffix: ' M$', goodWhen: 'up', meta: 'البنك المركزي' },
];

/** القطاع الخارجي: هل تبيع عُمان للعالم أكثر مما تشتري؟ وهل احتياطياتها تكفي؟ */
export function ExternalHeadline() {
  return <HeadlineStrip tiles={EXTERNAL} from={String(new Date().getFullYear() - 5)} sentence={(v) => {
    const ca = v['imf.BCA_NGDPD'];
    const g = v['ncsi.BOP_GOODS'];
    if (!ca) return null;
    const side = ca.value >= 0 ? 'فائضًا' : 'عجزًا';
    return `سجّل الحساب الجاري ${side} بنحو ${n(Math.abs(ca.value))}% من الناتج في ${formatPeriod(ca.period)}${g ? `، ورصيد السلع ${g.value >= 0 ? 'فائض' : 'عجز'} ${n(Math.abs(g.value), 0)} مليون ر.ع` : ''}.`;
  }} />;
}

const PRICES: TileSpec[] = [
  { indicator: 'ncsi.CPI_INFLATION', label: 'التضخم العام', suffix: '%', goodWhen: 'down', meta: 'سنوي' },
  { indicator: 'ncsi.CPIG_FOOD_BEV_INFL', label: 'الغذاء والمشروبات', suffix: '%', goodWhen: 'down', meta: 'سنوي' },
  { indicator: 'ncsi.CPIG_HOUSING_INFL', label: 'السكن والمرافق', suffix: '%', goodWhen: 'down', meta: 'سنوي' },
  { indicator: 'ncsi.CPIG_TRANSPORT_INFL', label: 'النقل', suffix: '%', goodWhen: 'down', meta: 'سنوي' },
];

/** الأسعار والمعيشة: التضخم العام وأي البنود تقوده. */
export function PricesHeadline() {
  return <HeadlineStrip tiles={PRICES} from={String(new Date().getFullYear() - 2)} sentence={(v) => {
    const all = v['ncsi.CPI_INFLATION'];
    if (!all) return null;
    const parts = ['ncsi.CPIG_FOOD_BEV_INFL', 'ncsi.CPIG_HOUSING_INFL', 'ncsi.CPIG_TRANSPORT_INFL'].map((k, i) => ({ label: ['الغذاء', 'السكن', 'النقل'][i], v: v[k]?.value ?? -Infinity })).sort((a, b) => b.v - a.v);
    return `بلغ التضخم السنوي ${n(all.value)}% في ${formatPeriod(all.period)}، والبند الأعلى ارتفاعًا بين الثلاثة هو ${parts[0].label} (${n(parts[0].v)}%).`;
  }} />;
}

const ENERGY: TileSpec[] = [
  { indicator: 'ncsi.OIL_PRICE', label: 'سعر النفط العُماني', suffix: ' $', goodWhen: 'up', meta: 'متوسط شهري' },
  { indicator: 'ncsi.OIL_PRODUCTION', label: 'إنتاج النفط الخام', digits: 0, suffix: ' K ب/ي', meta: 'متوسط يومي' },
  { indicator: 'ncsi.GAS_PRODUCTION', label: 'إنتاج الغاز', mode: 'yoy', suffix: '%', goodWhen: 'up', meta: 'سنوي، شهري' },
  { indicator: 'ncsi.ELEC_PRODUCTION', label: 'إنتاج الكهرباء', mode: 'yoy', suffix: '%', meta: 'سنوي، شهري' },
];

/** الطاقة والنفط: السعر والإنتاج وتغيّر الغاز والكهرباء. */
export function EnergyHeadline() {
  return <HeadlineStrip tiles={ENERGY} from={String(new Date().getFullYear() - 3)} sentence={(v) => {
    const p = v['ncsi.OIL_PRICE'];
    const q = v['ncsi.OIL_PRODUCTION'];
    if (!p || !q) return null;
    return `بلغ متوسط سعر نفط عُمان ${n(p.value)}$ للبرميل في ${formatPeriod(p.period)}${p.previous !== null ? ` (${p.value >= p.previous ? 'ارتفاعًا' : 'انخفاضًا'} من ${n(p.previous)}$)` : ''}، والإنتاج اليومي نحو ${n(q.value, 0)} ألف برميل.`;
  }} />;
}

const HUMAN: TileSpec[] = [
  { indicator: 'undp.hdi', label: 'مؤشر التنمية البشرية', digits: 3, goodWhen: 'up', meta: 'UNDP' },
  { indicator: 'undp.le', label: 'العمر المتوقع', digits: 1, suffix: ' سنة', goodWhen: 'up', meta: 'UNDP' },
  { indicator: 'undp.eys', label: 'سنوات الدراسة المتوقعة', digits: 1, suffix: ' سنة', goodWhen: 'up', meta: 'UNDP' },
  { indicator: 'wb.SH.XPD.CHEX.GD.ZS', label: 'الإنفاق الصحي', digits: 1, suffix: '% من الناتج', meta: 'البنك الدولي' },
  { indicator: 'wb.SP.DYN.IMRT.IN', label: 'وفيات الرضع', digits: 1, suffix: ' لكل 1000', goodWhen: 'down', meta: 'البنك الدولي' },
];

/** التعليم والصحة والتنمية: التنمية البشرية والعمر وسنوات الدراسة والإنفاق الصحي ووفيات الرضع. */
export function HumanHeadline() {
  return <HeadlineStrip tiles={HUMAN} from="2015" sentence={(v) => {
    const h = v['undp.hdi'];
    const le = v['undp.le'];
    if (!h || !le) return null;
    return `بلغ مؤشر التنمية البشرية لعُمان ${n(h.value, 3)} في ${formatPeriod(h.period)}${h.delta !== null ? ` (${h.delta >= 0 ? 'ارتفاعًا' : 'انخفاضًا'} ${n(Math.abs(h.delta), 3)} عن السنة السابقة)` : ''}، والعمر المتوقع عند الولادة ${n(le.value)} سنة${v['undp.eys'] ? ` وسنوات الدراسة المتوقعة ${n(v['undp.eys']!.value)}` : ''}.`;
  }} />;
}

const INVEST: TileSpec[] = [
  { indicator: 'wb.BX.KLT.DINV.WD.GD.ZS', label: 'الاستثمار الأجنبي الداخل', digits: 1, suffix: '% من الناتج', goodWhen: 'up', meta: 'البنك الدولي' },
  { indicator: 'wb.NE.GDI.FTOT.ZS', label: 'تكوين رأس المال الثابت', digits: 1, suffix: '% من الناتج', goodWhen: 'up', meta: 'البنك الدولي' },
  { indicator: 'wb.NY.GNS.ICTR.ZS', label: 'الادخار القومي', digits: 1, suffix: '% من الناتج', goodWhen: 'up', meta: 'البنك الدولي' },
  { indicator: 'wb.CM.MKT.LCAP.GD.ZS', label: 'القيمة السوقية للأسهم', digits: 0, suffix: '% من الناتج', goodWhen: 'up', meta: 'البنك الدولي' },
  { indicator: 'imf.iip_net', label: 'صافي مركز الاستثمار الدولي', digits: 0, suffix: ' B$', goodWhen: 'up', meta: 'صندوق النقد' },
];

/** الاستثمار: تدفقات الأجنبي والاستثمار المحلي والادخار وعمق السوق والمركز الدولي. */
export function InvestmentHeadline() {
  return <HeadlineStrip tiles={INVEST} from="2015" sentence={(v) => {
    const fdi = v['wb.BX.KLT.DINV.WD.GD.ZS'];
    const inv = v['wb.NE.GDI.FTOT.ZS'];
    const sv = v['wb.NY.GNS.ICTR.ZS'];
    if (!fdi || !inv) return null;
    return `بلغ الاستثمار الأجنبي المباشر الداخل ${n(fdi.value)}% من الناتج في ${formatPeriod(fdi.period)}، وتكوين رأس المال الثابت ${n(inv.value)}%${sv ? `، والادخار القومي ${n(sv.value)}%` : ''}.`;
  }} />;
}
