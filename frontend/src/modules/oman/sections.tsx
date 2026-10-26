import { useState } from 'react';
import { Section } from '@/core/components/PageHead';
import { TABLE_ICON } from '@/core/components/Segmented';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { oman } from '@/core/lines';
import { CreditRatings } from './CreditRatings';
import { GrowthAnalysis } from './GrowthAnalysis';
import { PopulationExplorer } from './PopulationExplorer';

/*
 * أقسام الاقتصاد العُماني كمكوّنات مستقلة: كانت صفحة /oman واحدة تحمل كل شيء، فصارت تُركَّب في صفحاتها المتخصصة
 * (النمو، الأسعار والمعيشة، المالية العامة، النقد والمصارف، القطاع الخارجي، الطاقة، السكان).
 */

const on = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'OMN', label, color });
// مؤشرات الدولار التي تُعرض بالريال العُماني
const onr = (indicator: string, label: string, color?: string) => ({ ...on(indicator, label, color), omr: true });
const omr = <T extends object>(lines: readonly T[]) => lines.map((l) => ({ ...l, omr: true }));
const H = 340;

export function GrowthSection() {
  const [analysis, setAnalysis] = useState(false);
  const [growthVariant, setGrowthVariant] = useState('growth');
  return (
        <Section id="growth" title="النمو والناتج">
          <SwitchChart wide height={H} title="النمو والناتج المحلي" onVariantChange={setGrowthVariant} extraActions={<button type="button" className={`btn btn-analysis${analysis ? ' active' : ''}`} aria-pressed={analysis} onClick={() => setAnalysis((v) => !v)} title="تحليل المؤشر المختار مع النفط والسكان ومعاملات الارتباط">{TABLE_ICON}<span>تحليل وارتباط</span></button>} variants={[
            { value: 'growth', label: 'نمو الناتج الحقيقي', subtitle: '% — صندوق النقد (الخط المتقطع توقعات)', lines: oman('imf.NGDP_RPCH', 'عُمان'), from: '2005', oil: true, imfForecast: true },
            { value: 'gdp', label: 'الناتج الجاري', subtitle: 'صندوق النقد — محوَّل من الدولار', lines: omr(oman('imf.NGDPD', 'صندوق النقد')), from: '2005', oil: true, imfForecast: true },
            { value: 'pc', label: 'نصيب الفرد', subtitle: 'صندوق النقد — محوَّل من الدولار', lines: omr(oman('imf.NGDPDPC', 'عُمان')), from: '2005', oil: true, imfForecast: true },
            { value: 'quarterly', label: 'الناتج الفصلي', subtitle: 'المركز الإحصائي الخليجي (محوَّل من الدولار)، مع سعر النفط العُماني (متوسط الفصل)', kind: 'columns', last: 24, oil: true, lines: [onr('gccstat.GDP_CUR_Q', 'الناتج الفصلي (جارٍ)')] },
            { value: 'ppp', label: 'بتعادل القوة الشرائية', subtitle: 'صندوق النقد — بالدولار الدولي (وحدة مقارنة بين الدول، لا تُحوَّل إلى الريال)', from: '2005', imfForecast: true, lines: oman('imf.PPPGDP', 'عُمان') },
            { value: 'world', label: 'حصتها من الناتج العالمي', subtitle: '% بتعادل القوة الشرائية', from: '2005', imfForecast: true, lines: oman('imf.PPPSH', 'عُمان') },
            { value: 'ncsi', label: 'الجارية والثابتة', subtitle: 'الحسابات القومية الرسمية — مليون ريال', kind: 'columns', last: 14, lines: [on('ncsi.GDP_CURRENT', 'الأسعار الجارية', 'var(--series-1)'), on('ncsi.GDP_CONSTANT', 'الأسعار الثابتة', 'var(--series-2)')] },
          ]} />
          {analysis && <GrowthAnalysis focus={growthVariant} />}
        </Section>
  );
}

export function StructureSection() {
  return (
        <Section id="structure" title="الهيكل الاقتصادي والاستثمار" lead="كم تعتمد عُمان على النفط؟ وكيف تتوزع القيمة المضافة بين القطاعات؟ وهل يكفي الادخار لتمويل الاستثمار؟">
          <SwitchChart wide height={H} title="هيكل الاقتصاد والتنويع" variants={[
            { value: 'sectors', label: 'القطاعات', subtitle: '% من الناتج (القيمة المضافة)', kind: 'columns', from: '2000', lines: [on('wb.NV.SRV.TOTL.ZS', 'الخدمات', 'var(--series-1)'), on('wb.NV.IND.TOTL.ZS', 'الصناعة (تشمل النفط)', 'var(--series-2)'), on('wb.NV.AGR.TOTL.ZS', 'الزراعة والأسماك', 'var(--series-3)')] },
            { value: 'manuf', label: 'التصنيع', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NV.IND.MANF.ZS') },
            { value: 'rent', label: 'ريع النفط', subtitle: '% من الناتج', from: '1990', lines: gccLines('wb.NY.GDP.PETR.RT.ZS') },
            { value: 'invest', label: 'الاستثمار والادخار', subtitle: '% من الناتج', from: '2000', lines: [on('wb.NE.GDI.TOTL.ZS', 'تكوين رأس المال', 'var(--series-1)'), on('wb.NY.GNS.ICTR.ZS', 'الادخار الإجمالي', 'var(--series-2)')] },
            { value: 'cons', label: 'الاستهلاك الحكومي', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NE.CON.GOVT.ZS') },
          ]} />
        </Section>
  );
}

export function FiscalSection() {
  return (
    <>
      <CreditRatings />
          <Section id="fiscal" title="المالية العامة">
            <SwitchChart wide height={H} title="المالية العامة" variants={[
              { value: 'debt', label: 'الدين الحكومي', subtitle: '% من الناتج — الخط المتقطع توقعات', lines: oman('imf.GGXWDG_NGDP', 'عُمان'), from: '2005', oil: true, imfForecast: true },
              { value: 'balance', label: 'رصيد الميزانية', subtitle: '% من الناتج', lines: oman('imf.GGXCNL_NGDP', 'عُمان'), from: '2005', oil: true, imfForecast: true },
              { value: 'rev-exp', label: 'الإيرادات والمصروفات', subtitle: '% من الناتج — مرصد المالية العامة (الخط المتقطع توقعات)', from: '2000', imfForecast: true, oil: true, lines: [on('imf.GGR_G01_GDP_PT', 'الإيرادات', 'var(--series-3)'), on('imf.G_X_G01_GDP_PT', 'المصروفات', 'var(--series-2)')] },
              { value: 'primary', label: 'الرصيد الأولي', subtitle: '% من الناتج — قبل الفوائد', from: '2000', imfForecast: true, oil: true, lines: [on('imf.GGXONLB_G01_GDP_PT', 'الرصيد الأولي'), on('imf.GGXCNL_G01_GDP_PT', 'الرصيد الكلي')] },
              { value: 'net-debt', label: 'صافي الدين', subtitle: '% من الناتج — الدين مطروحًا منه الأصول السائلة', from: '2000', imfForecast: true, oil: true, lines: [on('imf.G_XWDG_G01_GDP_PT', 'إجمالي الدين', 'var(--series-2)'), on('imf.GGXWDN_G01_GDP_PT', 'صافي الدين', 'var(--series-1)')] },
              { value: 'interest', label: 'الفوائد والمصروف الأولي', subtitle: '% من الناتج — سلسلة صندوق النقد الطويلة', from: '1990', lines: [on('imf.ie', 'الفوائد المدفوعة', 'var(--series-4)'), on('imf.prim_exp', 'المصروفات الأولية', 'var(--series-1)')] },
              { value: 'peers-debt', label: 'الدين: الخليج', subtitle: '% من الناتج', from: '2005', imfForecast: true, lines: gccLines('imf.GGXWDG_NGDP') },
              { value: 'peers-bal', label: 'الميزانية: الخليج', subtitle: '% من الناتج', from: '2005', imfForecast: true, lines: gccLines('imf.GGXCNL_NGDP') },
              { value: 'private-debt', label: 'دين القطاع الخاص', subtitle: '% من الناتج — قروض وسندات', from: '2000', lines: [on('imf.PVD_LS', 'القطاع الخاص'), on('imf.CG_DEBT_GDP', 'الحكومة المركزية')] },
              { value: 'tax', label: 'إيرادات الضرائب', subtitle: '% من الناتج', lines: oman('wb.GC.TAX.TOTL.GD.ZS', 'عُمان'), from: '2000' },
            ]} />
          </Section>
    </>
  );
}

export function MoneySection() {
  return (
        <Section id="money" title="النقد والمصارف والاحتياطيات" lead="السيولة والائتمان وأسعار الفائدة المحلية والاحتياطيات الأجنبية للبنك المركزي (بالمليون ريال عُماني، شهري، المركز الوطني للإحصاء) وسلامة القطاع المصرفي.">
          <SwitchChart wide height={H} title="النقد والائتمان" variants={[
            { value: 'm2', label: 'عرض النقد', subtitle: 'مليون ريال', from: '2005', lines: [on('ncsi.MON_M2', 'M2 الواسع', 'var(--series-1)'), on('ncsi.MON_M1', 'M1 الضيق', 'var(--series-2)'), on('ncsi.MON_QUASI_MONEY', 'شبه النقد', 'var(--series-3)')] },
            { value: 'credit', label: 'الائتمان والودائع', subtitle: 'مليون ريال (بيانات المركز الخليجي محوَّلة من الدولار)', from: '2005', lines: [on('ncsi.MON_CREDIT_PRIVATE', 'ائتمان القطاع الخاص', 'var(--series-1)'), onr('gccstat.BANK_DEPOSITS', 'ودائع البنوك', 'var(--series-2)'), onr('gccstat.DOMESTIC_CREDIT', 'الائتمان المحلي', 'var(--series-3)')] },
            { value: 'rates', label: 'أسعار الفائدة', subtitle: '% — الإقراض والإيداع المحلية', from: '2008', lines: [on('ncsi.MON_RATE_LENDING_RO', 'الإقراض بالريال', 'var(--series-1)'), on('ncsi.MON_RATE_DEPOSIT_TIME_RO', 'الودائع الزمنية', 'var(--series-2)'), on('ncsi.MON_RATE_DEPOSIT_TOTAL_RO', 'إجمالي الودائع', 'var(--series-3)')] },
            { value: 'interbank', label: 'بين البنوك والريبو', subtitle: '% — أدوات السياسة النقدية', from: '2008', lines: [on('ncsi.MON_RATE_OVERNIGHT', 'بين البنوك لليلة', 'var(--series-1)'), on('ncsi.MON_RATE_REPO', 'الريبو مع المركزي', 'var(--series-2)')] },
            { value: 'reserves', label: 'الاحتياطيات', subtitle: 'مليون ريال — البنك المركزي (الاحتياطيات محوَّلة من الدولار)', from: '2005', oil: true, lines: [onr('ncsi.MON_RESERVES_FX', 'احتياطيات النقد الأجنبي', 'var(--series-1)'), onr('ncsi.MON_RESERVES_EX_GOLD', 'الرسمية دون الذهب', 'var(--series-2)'), on('ncsi.MON_CBO_FOREIGN_ASSETS', 'الأصول الأجنبية للمركزي', 'var(--series-3)')] },
            { value: 'nfa', label: 'الأصول الأجنبية والحكومة', subtitle: 'مليون ريال — الجهاز المصرفي', from: '2005', oil: true, lines: [on('ncsi.MON_NET_FOREIGN_ASSETS', 'صافي الأصول الأجنبية', 'var(--series-1)'), on('ncsi.MON_NET_CLAIMS_GOV', 'صافي المطالبات على الحكومة', 'var(--series-2)')] },
            { value: 'neer', label: 'سعر الصرف الفعلي', subtitle: 'مؤشر 1999 = 100 — الريال مربوط بالدولار', from: '2005', lines: [on('ncsi.MON_NEER', 'الفعلي الاسمي')] },
            { value: 'credit-gdp', label: 'الائتمان للقطاع الخاص (% ناتج)', subtitle: '% من الناتج — مقارنة بالخليج', from: '2000', lines: gccLines('wb.FS.AST.PRVT.GD.ZS') },
          ]} />
          <SwitchChart wide height={H} title="سلامة القطاع المصرفي والسوق" variants={[
            { value: 'npl', label: 'القروض المتعثرة', subtitle: '% من القروض', lines: gccLines('wb.GFDD.SI.02') },
            { value: 'cap', label: 'رأس المال', subtitle: '% من الأصول المرجّحة بالمخاطر', lines: gccLines('wb.GFDD.SI.05') },
            { value: 'ldr', label: 'القروض إلى الودائع', subtitle: '%', lines: gccLines('wb.GFDD.SI.04') },
            { value: 'nim', label: 'هامش الفائدة', subtitle: '% — صافي هامش الفائدة', lines: gccLines('wb.GFDD.EI.01') },
            { value: 'z', label: 'مؤشر Z', subtitle: 'بُعد الإعسار — الأعلى أمتن', lines: gccLines('wb.GFDD.SI.01') },
            { value: 'mcap', label: 'القيمة السوقية المدرجة', subtitle: '% من الناتج — بورصة مسقط وغيرها', from: '2000', lines: gccLines('wb.CM.MKT.LCAP.GD.ZS') },
          ]} />
        </Section>
  );
}

export function BopSection() {
  return (
        <Section id="bop" title="ميزان المدفوعات (البنك المركزي عبر NCSI)" lead="الأرقام الرسمية بالمليون ريال عُماني: من أين تأتي صادرات عُمان (الخام والمشتقات والغاز وغير النفطي)، وما بنود الحساب الجاري.">
          <SwitchChart wide height={H} title="ميزان المدفوعات" subtitle="مليون ريال عُماني" variants={[
            { value: 'exports', label: 'الصادرات حسب النوع', kind: 'stacked', lines: [on('ncsi.BOP_EXPORTS_CRUDE', 'النفط الخام', 'var(--series-4)'), on('ncsi.BOP_EXPORTS_REFINED', 'المشتقات النفطية', 'var(--series-2)'), on('ncsi.BOP_EXPORTS_GAS', 'الغاز', 'var(--series-7)'), on('ncsi.BOP_EXPORTS_NON_OIL', 'غير نفطية', 'var(--series-3)'), on('ncsi.BOP_REEXPORTS', 'إعادة التصدير', 'var(--series-1)')] },
            { value: 'items', label: 'بنود الحساب الجاري', kind: 'columns', lines: [on('ncsi.BOP_GOODS', 'السلع', 'var(--series-3)'), on('ncsi.BOP_SERVICES', 'الخدمات', 'var(--series-4)'), on('ncsi.BOP_PRIMARY_INCOME', 'الدخل الأولي', 'var(--series-7)'), on('ncsi.BOP_SECONDARY_INCOME', 'الدخل الثانوي', 'var(--series-8)')] },
            { value: 'ca', label: 'رصيد الحساب الجاري', kind: 'columns', oil: true, lines: [on('ncsi.BOP_CURRENT_ACCOUNT', 'الحساب الجاري')] },
            { value: 'remit', label: 'تحويلات العاملين', subtitle: 'مليون ريال — سالب = خارج', kind: 'columns', lines: [on('ncsi.BOP_WORKERS_REMITTANCES', 'تحويلات العاملين')] },
          ]} />
        </Section>
  );
}

export function ExternalSection() {
  return (
        <Section id="external" title="القطاع الخارجي">
          <SwitchChart wide height={H} title="القطاع الخارجي" variants={[
            { value: 'ca', label: 'الحساب الجاري', subtitle: '% من الناتج — الخط المتقطع توقعات', lines: oman('imf.BCA_NGDPD', 'عُمان'), from: '2005', oil: true, imfForecast: true },
            { value: 'fdi', label: 'الاستثمار الأجنبي', subtitle: 'الداخل المباشر، % من الناتج', lines: oman('wb.BX.KLT.DINV.WD.GD.ZS', 'عُمان'), from: '2000' },
            { value: 'trade', label: 'الصادرات والواردات', subtitle: 'سلع وخدمات — مليون ريال', kind: 'columns', last: 14, lines: [on('ncsi.EXPORTS_GS', 'الصادرات', 'var(--series-1)'), on('ncsi.IMPORTS_GS', 'الواردات', 'var(--series-2)')] },
            { value: 'reserves', label: 'الاحتياطيات الدولية', subtitle: 'البنك الدولي — محوَّل من الدولار', lines: omr(oman('wb.FI.RES.TOTL.CD', 'عُمان')), from: '2000', oil: true },
            { value: 'iip', label: 'مركز الاستثمار الدولي', subtitle: 'الأصول مقابل الالتزامات — محوَّل من الدولار', lines: [onr('imf.iip_assets', 'الأصول الأجنبية', 'var(--series-3)'), onr('imf.iip_liabilities', 'الالتزامات', 'var(--series-2)'), onr('imf.iip_net', 'الصافي', 'var(--series-1)')] },
            { value: 'imts', label: 'التجارة السلعية الشهرية', subtitle: 'صندوق النقد — محوَّل من الدولار', from: '2019', lines: [onr('imf.imts_exports', 'الصادرات', 'var(--series-3)'), onr('imf.imts_imports', 'الواردات', 'var(--series-2)'), onr('imf.imts_balance', 'الرصيد', 'var(--series-1)')] },
            { value: 'tot', label: 'معدل التبادل التجاري', subtitle: 'مؤشر — صافي صادرات السلع', lines: oman('imf.ctot_net_exports', 'عُمان') },
            { value: 'goods', label: 'السلع: صادرات وواردات وإعادة تصدير', subtitle: 'المركز الإحصائي الخليجي — محوَّل من الدولار', kind: 'columns', last: 13, lines: [onr('gccstat.GOODS_EXPORTS', 'الصادرات', 'var(--series-3)'), onr('gccstat.GOODS_IMPORTS', 'الواردات', 'var(--series-2)'), onr('gccstat.RE_EXPORTS', 'إعادة التصدير', 'var(--series-1)')] },
            { value: 'remit', label: 'تحويلات العاملين الواردة', subtitle: 'البنك الدولي — محوَّل من الدولار', from: '2000', lines: omr(gccLines('wb.BX.TRF.PWKR.CD.DT')) },
          ]} />
        </Section>
  );
}

export function OmanOilSection() {
  return (
        <Section id="energy" title="النفط والغاز">
          <SwitchChart wide height={H} title="النفط والغاز" variants={[
            { value: 'price', label: 'سعر النفط العُماني', subtitle: 'دولار/برميل، متوسط شهري — المركز الوطني للإحصاء (مرجع إيرادات الميزانية)', from: '2005', lines: [{ indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'نفط عُمان' }] },
            { value: 'oil', label: 'إنتاج وصادرات الخام', subtitle: 'متوسط يومي', lines: [on('ncsi.OIL_PRODUCTION', 'الإنتاج', 'var(--series-1)'), on('ncsi.OIL_EXPORTS', 'الصادرات', 'var(--series-2)')] },
            { value: 'gas', label: 'إنتاج الغاز', subtitle: 'شهري', lines: [on('ncsi.GAS_PRODUCTION', 'الإنتاج')] },
            { value: 'rent', label: 'ريع النفط', subtitle: '% من الناتج', from: '1990', lines: oman('wb.NY.GDP.PETR.RT.ZS', 'عُمان') },
          ]} />
        </Section>
  );
}

export function PeopleSection() {
  return (
        <Section id="people" title="السكان والعمل">
          <PopulationExplorer />
          <SwitchChart wide height={H} title="العمل والتعليم" variants={[
            { value: 'unemp', label: 'البطالة', subtitle: '%', lines: [on('gccstat.UNEMPLOYMENT', 'عُمان')] },
            { value: 'emp', label: 'العاملون', lines: [on('gccstat.EMPLOYED', 'إجمالي العاملين', 'var(--series-1)'), on('gccstat.EMPLOYED_CITIZEN', 'المواطنون', 'var(--series-2)')] },
            { value: 'uni', label: 'طلبة التعليم العالي', lines: [on('gccstat.STUDENTS_UNI', 'الطلبة')] },
          ]} />
        </Section>
  );
}
