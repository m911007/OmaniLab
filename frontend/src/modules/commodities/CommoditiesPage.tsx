import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import type { LineSpec } from '@/core/hooks';
import { OilFiscalSensitivity } from './OilFiscalSensitivity';

const wld = (code: string, label: string, color: string): LineSpec => ({ indicator: `imf.pcps.${code}`, entity: 'WLD', label, color });
const us = (code: string, label: string, color: string): LineSpec => ({ indicator: `us.yield_${code}`, entity: 'USA', label, color });

export default function CommoditiesPage() {
  return (
    <>
      <PageHead
        title="النفط والسلع"
        lead="أسعار النفط والغاز والمعادن والغذاء عالميًا وأثرها على الاقتصاد العُماني، مع أسعار الفائدة الأمريكية التي يرتبط بها الريال. أسعار صندوق النقد شهرية ومعدّلة بالدولار، وعوائد الخزانة الأمريكية يومية."
      />

      <div className="grid cols-4">
        <KeyStat entity="WLD" indicator="imf.pcps.poildub" label="خام دبي (مرجع النفط العُماني)" />
        <KeyStat entity="WLD" indicator="imf.pcps.poilbre" label="خام برنت" />
        <KeyStat entity="WLD" indicator="imf.pcps.pngasjp" label="الغاز المسال في آسيا" />
        <KeyStat entity="USA" indicator="us.yield_10y" label="عائد الخزانة الأمريكية 10 سنوات" />
      </div>

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
        <SeriesChart title="خام دبي (شهري)" subtitle="دولار/برميل — مرجع تسعير النفط العُماني" lines={[wld('poildub', 'دبي', 'var(--series-1)')]} from="2015" />
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
          lines={[wld('poildub', 'دبي', 'var(--series-1)'), wld('poilbre', 'برنت', 'var(--series-2)'), wld('poilwti', 'غرب تكساس', 'var(--series-3)')]}
          from="2014"
        />
        <SeriesChart
          title="النفط العُماني مقابل دبي"
          subtitle="متوسط شهري"
          lines={[
            { indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'النفط العُماني (وزارة الطاقة)', color: 'var(--series-1)' },
            wld('poildub', 'دبي (صندوق النقد)', 'var(--series-2)'),
          ]}
          from="2018"
          note="يُسعَّر الخام العُماني بالرجوع إلى دبي؛ الفرق بين الخطين علاوة الجودة والتوقيت."
        />
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
    </>
  );
}
