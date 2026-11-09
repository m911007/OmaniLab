import { SeriesChart } from '@/core/components/SeriesChart';
import { Section } from '@/core/components/PageHead';
import { OilFiscalSensitivity } from '@/modules/commodities/OilFiscalSensitivity';
import { RevenueSources } from './GovSpending';
import { ByEntity } from './ByEntity';
import { HydroShareChart } from './HydroShareChart';

const om = (code: string, label: string, color?: string) => ({ indicator: `ncsi.${code}`, entity: 'OMN', label, color });

/** الإيرادات: من أين تأتي؟ ما مدى الاعتماد على النفط والغاز؟ وما حساسيتها لسعر النفط؟ */
export default function FiscalRevenueTab() {
  return (
    <>
      <Section id="sources" title="من أين تأتي الإيرادات؟" lead="النفط والغاز مقابل بقية الإيرادات: الحصة غير النفطية الأعلى تعني إيرادًا أقل تقلبًا. الأرقام الفعلية من الحساب الختامي لوزارة المالية.">
        <RevenueSources />
        <HydroShareChart />
        <OilFiscalSensitivity wide title="الإيرادات وسعر النفط" indicator="mof.rev_total" yLabel="إجمالي الإيرادات (مليار ر.ع)" />
      </Section>
      <Section id="by-entity" title="الإيرادات حسب الجهة" lead="أي الجهات الحكومية تحصّل أكبر الإيرادات الجارية (جهاز الضرائب، جهاز الاستثمار العماني، الشرطة…)؟ من جداول الحساب الختامي لوزارة المالية.">
        <ByEntity indicator="mof.unit_rev" title="الإيرادات الجارية حسب الجهة" subtitle="وزارة المالية" note="الإيرادات الجارية لوحدات الجهاز الإداري للدولة والأشخاص الاعتبارية العامة (الجدول 2 في الحساب الختامي). تُعرض فقط السنوات التي يطابق مجموع جهاتها إجمالي الإيراد الجاري المنشور بفارق لا يتجاوز 1.5%. لا تشمل إيرادات النفط والغاز." />
      </Section>
      <Section id="non-oil" title="الإيرادات غير النفطية بالتفصيل" lead="الضرائب والرسوم والخدمات وعوائد الاستثمار: بنودها كما تنشرها بيانات المالية العامة (مليون ريال عُماني).">
        <SeriesChart wide title="مكوّنات الإيرادات غير النفطية" kind="stacked" last={22} lines={[om('PF_TAXES', 'ضرائب', 'var(--series-1)'), om('PF_INVESTMENT', 'استثمارات', 'var(--series-3)'), om('PF_FEES', 'رسوم', 'var(--series-2)'), om('PF_SERVICES', 'خدمات', 'var(--series-5)'), om('PF_MISC', 'متنوعة', 'var(--series-4)'), om('PF_CAPITAL', 'رأسمالية', 'var(--series-7)')]} from="2003" />
        <SeriesChart wide kind="stacked" last={22} title="ضريبة دخل الشركات والجمارك" lines={[om('PF_CORPORATE_TAX', 'ضريبة دخل الشركات'), om('PF_CUSTOMS', 'الرسوم الجمركية'), om('PF_LOCAL_GOODS_TAX', 'ضرائب السلع والخدمات المحلية'), om('PF_PROPERTY_TAX', 'ضرائب الملكية')]} from="2003" />
        <SeriesChart wide title="أرباح الاستثمارات الحكومية" subtitle="توزيعات الأرباح — مليون ريال عُماني" kind="columns" last={22} lines={[om('PF_DIVIDENDS', 'أرباح وتوزيعات')]} from="2003" />
        <SeriesChart wide kind="stacked" last={22} title="رسوم الهجرة واستقدام العمالة والغرامات" lines={[om('PF_IMMIGRATION_FEES', 'رسوم الهجرة والجوازات'), om('PF_WORK_PERMITS', 'استقدام العمالة'), om('PF_FINES', 'غرامات وتعويضات')]} from="2003" />
        <SeriesChart wide title="إيرادات المطارات والاتصالات والتعدين" lines={[om('PF_AIRPORTS', 'المطارات'), om('PF_TELECOM', 'مرافق الاتصالات'), om('PF_MINING', 'التعدين')]} from="2003" />
      </Section>
    </>
  );
}
