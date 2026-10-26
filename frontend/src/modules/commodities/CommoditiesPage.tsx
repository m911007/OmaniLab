import { ReadingGrid } from '@/core/components/ReadingGrid';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart, type RangeOption } from '@/core/components/SeriesChart';
import type { LineSpec } from '@/core/hooks';
import { OilFiscalSensitivity } from './OilFiscalSensitivity';

const wld = (code: string, label: string, color: string): LineSpec => ({ indicator: `imf.pcps.${code}`, entity: 'WLD', label, color });
const us = (code: string, label: string, color: string): LineSpec => ({ indicator: `us.yield_${code}`, entity: 'USA', label, color });

const WEEKLY: readonly RangeOption[] = [
  { value: '1y', label: '1y', months: 12 },
  { value: '5y', label: '5y', months: 60 },
  { value: '10y', label: '10y', months: 120 },
  { value: 'all', label: 'الكل', months: 0 },
];

export default function CommoditiesPage() {
  return (
    <>
      <PageHead
        title="النفط والسلع"
        lead="أسعار النفط والغاز والمعادن والغذاء عالميًا وأثرها على الاقتصاد العُماني، مع أسعار الفائدة الأمريكية التي يرتبط بها الريال. أسعار صندوق النقد شهرية ومعدّلة بالدولار، وعوائد الخزانة الأمريكية يومية."
      />

      <ReadingGrid>
        <KeyStat entity="OMN" indicator="ncsi.OIL_PRICE" label="نفط عُمان" />
        <KeyStat entity="WLD" indicator="imf.pcps.poilbre" label="خام برنت" />
        <KeyStat entity="WLD" indicator="imf.pcps.pngasjp" label="الغاز المسال في آسيا" />
        <KeyStat entity="USA" indicator="us.yield_10y" label="عائد الخزانة الأمريكية 10 سنوات" />
      </ReadingGrid>

      <Section title="أسعار النفط من مصادر متعددة" lead="سعر برنت يُنشر من ثلاثة مصادر مستقلة: صندوق النقد (شهري) والبنك الدولي (الورقة الوردية) وإدارة معلومات الطاقة الأمريكية (يومي، والمتوسط الشهري المحسوب منه). تحت كل رسم شارات المصادر وحكم المطابقة وزر التبديل بينها.">
        <SeriesChart title="خام برنت (شهري)" subtitle="دولار/برميل" lines={[wld('poilbre', 'برنت', 'var(--series-1)')]} from="2015" />
        <SeriesChart
          title="برنت: المصادر الثلاثة معًا"
          subtitle="دولار/برميل، شهري"
          lines={[
            { indicator: 'imf.pcps.poilbre', entity: 'WLD', label: 'صندوق النقد', color: 'var(--series-1)' },
            { indicator: 'wbpink.crude_brent', entity: 'WLD', label: 'البنك الدولي', color: 'var(--series-2)' },
            { indicator: 'eia.brent_m', entity: 'WLD', label: 'EIA', color: 'var(--series-3)' },
          ]}
          from="2023"
        />
        <SeriesChart title="برنت اليومي (EIA)" subtitle="دولار/برميل" lines={[{ indicator: 'eia.brent_d', entity: 'WLD', label: 'برنت', color: 'var(--series-1)' }]} />
        <SeriesChart title="غرب تكساس اليومي (EIA)" subtitle="دولار/برميل" lines={[{ indicator: 'eia.wti_d', entity: 'WLD', label: 'غرب تكساس', color: 'var(--series-3)' }]} />
        <SeriesChart title="خام غرب تكساس (شهري)" subtitle="دولار/برميل" lines={[wld('poilwti', 'غرب تكساس', 'var(--series-3)')]} from="2015" />
        <SeriesChart title="نفط عُمان (شهري)" subtitle="دولار/برميل — المركز الوطني للإحصاء" lines={[{ indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'نفط عُمان', color: 'var(--series-1)' }]} from="2015" />
        <SeriesChart title="غاز هنري هب (شهري)" subtitle="دولار/مليون وحدة حرارية" lines={[wld('pngasus', 'هنري هب', 'var(--series-4)')]} from="2015" />
        <SeriesChart title="الغاز المسال في آسيا (شهري)" subtitle="دولار/مليون وحدة حرارية" lines={[wld('pngasjp', 'الغاز المسال', 'var(--series-7)')]} from="2015" />
      </Section>

      <Section title="أثر النفط على عُمان" lead="العلاقة التاريخية بين سعر النفط والميزانية والحساب الجاري: ما تكسبه عُمان أو تخسره مع كل حركة في السعر.">
        <OilFiscalSensitivity title="رصيد الميزانية وسعر النفط" indicator="imf.GGXCNL_NGDP" yLabel="رصيد الميزانية (% من الناتج)" />
        <OilFiscalSensitivity title="الحساب الجاري وسعر النفط" indicator="imf.BCA_NGDPD" yLabel="الحساب الجاري (% من الناتج)" />
      </Section>

      <Section title="النفط">
        <SeriesChart
          title="أسعار النفط الخام"
          subtitle="متوسط شهري"
          lines={[{ indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'نفط عُمان', color: 'var(--series-1)' }, wld('poilbre', 'برنت', 'var(--series-2)'), wld('poilwti', 'غرب تكساس', 'var(--series-3)')]}
          from="2014"
        />
      </Section>

      <Section title="سوق النفط الأمريكي أسبوعيًا (EIA)" lead="التقرير الأسبوعي الأهم لاتجاه الأسعار على المدى القصير: ارتفاع المخزون يضغط على السعر وانخفاضه يدعمه؛ والإنتاج والصادرات الأمريكية منافس مباشر للنفط العُماني.">
        <SeriesChart wide height={340} ranges={WEEKLY} defaultRange="5y" title="مخزون النفط الخام الأمريكي: التجاري والاستراتيجي" subtitle="ألف برميل — أسبوعي" lines={[{ indicator: 'eia.us_crude_stocks', entity: 'USA', label: 'تجاري' }, { indicator: 'eia.us_spr_stocks', entity: 'USA', label: 'احتياطي استراتيجي' }]} />
        <SeriesChart height={340} ranges={WEEKLY} defaultRange="5y" title="مخزون البنزين والمقطّرات" subtitle="ألف برميل" lines={[{ indicator: 'eia.us_gasoline_stocks', entity: 'USA', label: 'بنزين' }, { indicator: 'eia.us_distillate_stocks', entity: 'USA', label: 'مقطّرات (ديزل)' }]} />
        <SeriesChart height={340} ranges={WEEKLY} defaultRange="10y" title="إنتاج النفط الأمريكي" subtitle="ألف برميل/يوم" lines={[{ indicator: 'eia.us_crude_production', entity: 'USA', label: 'إنتاج' }]} />
        <SeriesChart height={340} ranges={WEEKLY} defaultRange="10y" title="واردات وصادرات النفط الخام الأمريكية" subtitle="ألف برميل/يوم" lines={[{ indicator: 'eia.us_crude_imports', entity: 'USA', label: 'واردات' }, { indicator: 'eia.us_crude_exports', entity: 'USA', label: 'صادرات' }]} />
        <SeriesChart height={340} ranges={WEEKLY} defaultRange="5y" title="معدل تشغيل المصافي الأمريكية" subtitle="%" lines={[{ indicator: 'eia.us_refinery_utilization', entity: 'USA', label: 'تشغيل المصافي' }]} />
        <SeriesChart height={340} ranges={WEEKLY} defaultRange="10y" title="الطلب الأمريكي على المنتجات النفطية والبنزين" subtitle="ألف برميل/يوم" lines={[{ indicator: 'eia.us_product_supplied', entity: 'USA', label: 'كل المنتجات' }, { indicator: 'eia.us_gasoline_demand', entity: 'USA', label: 'البنزين' }]} />
        <SeriesChart height={340} ranges={WEEKLY} defaultRange="10y" title="مخزون الغاز الطبيعي الأمريكي" subtitle="مليار قدم مكعب" lines={[{ indicator: 'eia.us_gas_storage', entity: 'USA', label: 'مخزون الغاز' }]} />
      </Section>

      <Section title="الغاز والفحم">
        <SeriesChart
          title="أسعار الغاز الطبيعي"
          subtitle="متوسط شهري"
          lines={[wld('pngasjp', 'الغاز المسال (آسيا)', 'var(--series-1)'), wld('pngaseu', 'أوروبا', 'var(--series-2)'), wld('pngasus', 'هنري هب (أمريكا)', 'var(--series-3)')]}
          from="2014"
        />
        <SeriesChart title="الفحم (أستراليا)" subtitle="متوسط شهري" lines={[wld('pcoalau', 'الفحم', 'var(--series-4)')]} from="2014" />
      </Section>

      <Section title="المعادن" lead="مدخلات الصناعات التحويلية والتعدين، ومقياس الطلب الصناعي العالمي.">
        <SeriesChart
          title="المعادن الصناعية"
          subtitle="متوسط شهري"
          lines={[wld('pcopp', 'النحاس', 'var(--series-1)'), wld('palum', 'الألمنيوم', 'var(--series-2)'), wld('pzinc', 'الزنك', 'var(--series-3)'), wld('pnick', 'النيكل', 'var(--series-4)')]}
          from="2014"
        />
        <SeriesChart title="الذهب" subtitle="متوسط شهري" lines={[wld('pgold', 'الذهب', 'var(--series-4)')]} from="2014" />
        <SeriesChart title="خام الحديد" subtitle="متوسط شهري" lines={[wld('piorecr', 'خام الحديد', 'var(--series-5)')]} from="2014" />
      </Section>

      <Section title="الغذاء والأسمدة" lead="عُمان مستورد صافٍ للغذاء، فارتفاع هذه الأسعار تضخم مستورد.">
        <SeriesChart
          title="مؤشر أسعار الغذاء العالمي (FAO)"
          subtitle="2014-2016 = 100 — شهري منذ 1990"
          from="1990"
          lines={[{ indicator: 'fao.ffpi', entity: 'WLD', label: 'المؤشر العام' }]}
        />
        <SeriesChart
          title="مؤشرات الغذاء حسب المجموعة (FAO)"
          subtitle="2014-2016 = 100"
          from="2000"
          lines={[{ indicator: 'fao.ffpi_cereals', entity: 'WLD', label: 'حبوب' }, { indicator: 'fao.ffpi_oils', entity: 'WLD', label: 'زيوت' }, { indicator: 'fao.ffpi_meat', entity: 'WLD', label: 'لحوم' }, { indicator: 'fao.ffpi_dairy', entity: 'WLD', label: 'ألبان' }, { indicator: 'fao.ffpi_sugar', entity: 'WLD', label: 'سكر' }]}
        />
        <SeriesChart
          title="الحبوب والأرز"
          subtitle="متوسط شهري"
          lines={[wld('pwheamt', 'القمح', 'var(--series-1)'), wld('pmaizmt', 'الذرة', 'var(--series-2)'), wld('pricenpq', 'الأرز', 'var(--series-3)')]}
          from="2014"
        />
        <SeriesChart title="السكر وفول الصويا واليوريا" subtitle="متوسط شهري" lines={[wld('psoyb', 'فول الصويا', 'var(--series-1)'), wld('purea', 'اليوريا', 'var(--series-2)')]} from="2014" />
        <SeriesChart
          title="مؤشرات الأسعار المجمَّعة"
          subtitle="2016 = 100"
          lines={[wld('pallfnf', 'السلع غير الوقودية', 'var(--series-1)'), wld('pallmeta', 'المعادن', 'var(--series-2)'), wld('pagri', 'المواد الزراعية الخام', 'var(--series-3)')]}
          from="2014"
        />
      </Section>

      <Section title="أسعار الفائدة" lead="الريال العُماني مربوط بالدولار، فتنتقل إليه حركة الفائدة الأمريكية، وهي مرجع كلفة الدين السيادي والتمويل.">
        <SeriesChart
          title="منحنى عوائد الخزانة الأمريكية"
          subtitle="يومي"
          lines={[us('3m', '3 أشهر', 'var(--series-1)'), us('2y', 'سنتان', 'var(--series-2)'), us('10y', '10 سنوات', 'var(--series-3)'), us('30y', '30 سنة', 'var(--series-4)')]}
        />
      </Section>
      <Section title="عملات الشركاء التجاريين" lead="الريال مربوط بالدولار (0.3845)، فحين يقوى الدولار تصبح واردات عُمان من أوروبا والصين والهند أرخص بالريال، وتضعف عوائد صادراتها المسعّرة بتلك العملات. القيم: وحدات العملة لكل دولار.">
        <SeriesChart title="اليوان الصيني والروبية الهندية" subtitle="لكل دولار" lines={[{ indicator: 'fx.usd_cny', entity: 'USA', label: 'اليوان' }, { indicator: 'fx.usd_inr', entity: 'USA', label: 'الروبية' }]} from="2020" />
        <SeriesChart title="اليورو والجنيه والفرنك" subtitle="لكل دولار" lines={[{ indicator: 'fx.usd_eur', entity: 'USA', label: 'اليورو' }, { indicator: 'fx.usd_gbp', entity: 'USA', label: 'الجنيه' }, { indicator: 'fx.usd_chf', entity: 'USA', label: 'الفرنك' }]} from="2020" />
        <SeriesChart title="الين الياباني والوون الكوري" subtitle="لكل دولار — الوون على مقياس مستقل في الجدول" lines={[{ indicator: 'fx.usd_jpy', entity: 'USA', label: 'الين' }, { indicator: 'fx.usd_krw', entity: 'USA', label: 'الوون' }]} from="2020" />
        <SeriesChart title="عملات العمالة والتجارة الآسيوية" subtitle="لكل دولار" lines={[{ indicator: 'fx.usd_php', entity: 'USA', label: 'البيزو الفلبيني' }, { indicator: 'fx.usd_sgd', entity: 'USA', label: 'الدولار السنغافوري' }]} from="2020" />
      </Section>
    </>
  );
}
