import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines, peerLines } from '@/core/groups';

const om = (code: string, label: string, color?: string) => ({ indicator: `ncsi.${code}`, entity: 'OMN', label, color });

export default function IncomeSourcesPage() {
  return (
    <>
      <PageHead
        title="مصادر دخل الدولة والحكومة"
        lead="من أين تأتي موارد عُمان؟ إيرادات الحكومة حسب المصدر (نفط وغاز وضرائب ورسوم وخدمات واستثمارات)، وهيكل الناتج والصادرات والموارد الأخرى للاقتصاد: من المركز الوطني للإحصاء (المالية العامة حتى 2024) والبنك الدولي وصندوق النقد."
      />

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="ncsi.PF_REV_TOTAL" label="إجمالي إيرادات الحكومة (مليون ر.ع)" compactValue goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.PF_REV_OIL" label="الإيرادات النفطية (مليون ر.ع)" compactValue />
        <KeyStat entity="OMN" indicator="ncsi.PF_REV_NON_OIL" label="الإيرادات غير النفطية (مليون ر.ع)" compactValue goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.PF_CORPORATE_TAX" label="ضريبة دخل الشركات (مليون ر.ع)" compactValue goodWhen="up" />
      </div>

      <Section title="إيرادات الحكومة حسب المصدر الرئيسي" lead="الاعتماد على النفط والغاز مقابل الإيرادات غير النفطية: كلما ارتفعت الحصة غير النفطية قلّ تأثر الميزانية بتقلب الأسعار.">
        <SeriesChart oil title="إيرادات الحكومة: نفط وغاز وغير نفطية" subtitle="مليون ريال عُماني" kind="stacked" last={22} lines={[om('PF_REV_OIL', 'نفط', 'var(--series-2)'), om('PF_REV_GAS', 'غاز', 'var(--series-4)'), om('PF_REV_NON_OIL', 'غير نفطية', 'var(--series-3)')]} from="2003" />
        <SeriesChart oil title="إيرادات الحكومة مقابل مصروفاتها" subtitle="مليون ريال عُماني" lines={[om('PF_REV_TOTAL', 'الإيرادات'), om('PF_EXP_TOTAL', 'المصروفات')]} from="2003" />
        <SeriesChart title="الإيرادات غير النفطية" subtitle="مليون ريال عُماني" kind="columns" last={22} lines={[om('PF_REV_NON_OIL', 'غير نفطية')]} from="2003" />
        <SeriesChart oil title="الإيرادات النفطية: الإجمالية والصافية" subtitle="مليون ريال عُماني" lines={[om('PF_REV_OIL', 'إجمالي'), om('PF_REV_OIL_NET', 'صافي')]} from="2003" />
      </Section>

      <Section title="الإيرادات غير النفطية بالتفصيل" lead="الضرائب والرسوم والخدمات وعوائد الاستثمار: بنودها كما تنشرها بيانات المالية العامة (مليون ريال عُماني).">
        <SeriesChart title="مكوّنات الإيرادات غير النفطية" kind="stacked" last={22} lines={[om('PF_TAXES', 'ضرائب', 'var(--series-1)'), om('PF_INVESTMENT', 'استثمارات', 'var(--series-3)'), om('PF_FEES', 'رسوم', 'var(--series-2)'), om('PF_SERVICES', 'خدمات', 'var(--series-5)'), om('PF_MISC', 'متنوعة', 'var(--series-4)'), om('PF_CAPITAL', 'رأسمالية', 'var(--series-7)')]} from="2003" />
        <SeriesChart title="ضريبة دخل الشركات والجمارك" lines={[om('PF_CORPORATE_TAX', 'ضريبة دخل الشركات'), om('PF_CUSTOMS', 'الرسوم الجمركية'), om('PF_LOCAL_GOODS_TAX', 'ضرائب السلع والخدمات المحلية'), om('PF_PROPERTY_TAX', 'ضرائب الملكية')]} from="2003" />
        <SeriesChart title="أرباح الاستثمارات الحكومية" subtitle="توزيعات الأرباح — مليون ريال عُماني" kind="columns" last={22} lines={[om('PF_DIVIDENDS', 'أرباح وتوزيعات')]} from="2003" />
        <SeriesChart title="رسوم الهجرة واستقدام العمالة والغرامات" lines={[om('PF_IMMIGRATION_FEES', 'رسوم الهجرة والجوازات'), om('PF_WORK_PERMITS', 'استقدام العمالة'), om('PF_FINES', 'غرامات وتعويضات')]} from="2003" />
        <SeriesChart title="إيرادات المطارات والاتصالات والتعدين" lines={[om('PF_AIRPORTS', 'المطارات'), om('PF_TELECOM', 'مرافق الاتصالات'), om('PF_MINING', 'التعدين')]} from="2003" />
      </Section>

      <Section title="وجه الإنفاق والدعم" lead="كم تنفق الحكومة، وعلى ماذا (حتى 2019 للتفصيل، و2024 للإجمالي).">
        <SeriesChart title="إجمالي المصروفات والجارية" subtitle="مليون ريال عُماني" lines={[om('PF_EXP_TOTAL', 'الإجمالي'), om('PF_EXP_CURRENT', 'الجارية')]} from="2003" />
        <SeriesChart title="الدفاع والأمن" subtitle="مليون ريال عُماني" kind="columns" last={20} lines={[om('PF_EXP_DEFENCE', 'الدفاع والأمن')]} from="2002" />
        <SeriesChart title="الدعم والمساهمات" subtitle="مليون ريال عُماني" kind="stacked" last={20} lines={[om('PF_SUPPORT_FUEL', 'دعم الوقود', 'var(--series-2)'), om('PF_SUPPORT_ELECTRICITY', 'دعم الكهرباء', 'var(--series-4)'), om('PF_SUPPORT_FOOD', 'دعم الغذاء', 'var(--series-3)')]} from="2002" />
      </Section>

      <Section title="مصادر دخل الاقتصاد" lead="كيف يتكوّن الناتج، وما مصادر النقد الأجنبي: القطاعات والصادرات والموارد والسياحة والاستثمار الأجنبي، مقارنةً بدول الخليج.">
        <SeriesChart title="القيمة المضافة للصناعة (% من الناتج)" lines={gccLines('wb.NV.IND.TOTL.ZS')} from="1995" />
        <SeriesChart title="القيمة المضافة للخدمات (% من الناتج)" lines={gccLines('wb.NV.SRV.TOTL.ZS')} from="1995" />
        <SeriesChart title="القيمة المضافة للزراعة (% من الناتج)" lines={gccLines('wb.NV.AGR.TOTL.ZS')} from="1995" />
        <SeriesChart title="القيمة المضافة للتصنيع (% من الناتج)" lines={gccLines('wb.NV.IND.MANF.ZS')} from="1995" />
        <SeriesChart oil title="ريوع الموارد الطبيعية (% من الناتج)" lines={peerLines('wb.NY.GDP.TOTL.RT.ZS')} from="1990" />
        <SeriesChart oil title="صادرات الوقود (% من صادرات السلع)" lines={peerLines('wb.TX.VAL.FUEL.ZS.UN')} from="1995" />
        <SeriesChart title="صادرات السلع الصناعية (% من صادرات السلع)" lines={peerLines('wb.TX.VAL.MANF.ZS.UN')} from="1995" />
        <SeriesChart title="صادرات الغذاء والمعادن (عُمان)" lines={[{ indicator: 'wb.TX.VAL.FOOD.ZS.UN', entity: 'OMN', label: 'غذاء' }, { indicator: 'wb.TX.VAL.MMTL.ZS.UN', entity: 'OMN', label: 'خامات ومعادن' }]} from="1995" />
        <SeriesChart title="إيرادات السياحة الدولية (% من الصادرات)" lines={gccLines('wb.ST.INT.RCPT.XP.ZS')} from="2000" />
        <SeriesChart title="الاستثمار الأجنبي المباشر الداخل (% من الناتج)" lines={gccLines('wb.BX.KLT.DINV.WD.GD.ZS')} from="2000" />
        <SeriesChart title="تحويلات العاملين الواردة (دولار)" lines={gccLines('wb.BX.TRF.PWKR.CD.DT')} from="2000" />
        <SeriesChart title="الاستهلاك الأسري (% من الناتج)" lines={gccLines('wb.NE.CON.PRVT.ZS')} from="1995" />
      </Section>
    </>
  );
}
