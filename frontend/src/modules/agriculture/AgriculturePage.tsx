import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines, peerLines } from '@/core/groups';

const om = (code: string, label: string, color?: string) => ({ indicator: `ncsi.${code}`, entity: 'OMN', label, color });

export default function AgriculturePage() {
  return (
    <>
      <PageHead
        title="الزراعة والثروة الحيوانية والسمكية والمياه"
        lead="إنتاج المحاصيل والخضروات والفواكه، وأعداد الماشية والإنتاج الحيواني، والأسماك المنزلة والاستزراع، والمنشآت الاقتصادية في الزراعة والصناعة والتعدين: من المركز الوطني للإحصاء والمعلومات، مع مقارنة الأمن الغذائي والأراضي والموارد المائية بدول الخليج من البنك الدولي."
      />

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="ncsi.AGR_CROP_PROD" label="إنتاج المحاصيل (طن)" compactValue goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.AGR_LIVESTOCK_TOTAL" label="الثروة الحيوانية (ألف رأس)" />
        <KeyStat entity="OMN" indicator="ncsi.AGR_MILK" label="إنتاج الألبان الطازجة (طن)" compactValue goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.FISH_AQUACULTURE" label="الاستزراع السمكي (طن)" compactValue goodWhen="up" />
      </div>

      <Section title="المحاصيل" lead="كمية الإنتاج الزراعي حسب نوع المحصول والمساحة المزروعة.">
        <SeriesChart title="إنتاج المحاصيل حسب النوع" subtitle="طن" kind="stacked" lines={[om('AGR_CROP_VEG', 'خضروات', 'var(--series-3)'), om('AGR_CROP_FRUIT', 'فواكه', 'var(--series-2)'), om('AGR_CROP_FIELD', 'محاصيل حقلية', 'var(--series-1)'), om('AGR_CROP_FODDER', 'علفية معمرة', 'var(--series-4)')]} />
        <SeriesChart title="إجمالي إنتاج المحاصيل" subtitle="طن" kind="columns" lines={[om('AGR_CROP_PROD', 'إجمالي الإنتاج')]} />
        <SeriesChart title="إجمالي المساحة المزروعة" subtitle="فدان" kind="columns" lines={[om('AGR_CULTIVATED_AREA', 'المساحة المزروعة')]} />
        <SeriesChart title="الأراضي الزراعية (% من مساحة اليابسة)" lines={gccLines('wb.AG.LND.AGRI.ZS')} from="1995" />
        <SeriesChart title="مؤشر إنتاج الغذاء (2014-2016 = 100)" lines={gccLines('wb.AG.PRD.FOOD.XD')} from="1995" />
        <SeriesChart title="إنتاج الحبوب (طن)" lines={gccLines('wb.AG.PRD.CREL.MT')} from="1995" />
      </Section>

      <Section title="الثروة الحيوانية" lead="أعداد الماشية حسب النوع وما تنتجه من لحوم وألبان.">
        <SeriesChart title="أعداد الماشية حسب النوع" subtitle="ألف رأس" lines={[om('AGR_SHEEP', 'أغنام'), om('AGR_GOATS', 'ماعز'), om('AGR_CATTLE', 'أبقار'), om('AGR_CAMELS', 'جمال')]} />
        <SeriesChart title="إجمالي الثروة الحيوانية" subtitle="ألف رأس" kind="columns" lines={[om('AGR_LIVESTOCK_TOTAL', 'الإجمالي')]} />
        <SeriesChart title="الإنتاج الحيواني" subtitle="طن" lines={[om('AGR_RED_MEAT', 'لحوم حمراء'), om('AGR_MILK', 'ألبان طازجة'), om('AGR_POULTRY_MEAT', 'لحوم دواجن')]} />
      </Section>

      <Section title="الثروة السمكية" lead="الأسماك المنزلة شهريًا حسب نوع الصيد (التقليدي والتجاري) والاستزراع، مع إنتاج الأسماك في دول الخليج.">
        <SeriesChart title="الأسماك المنزلة: التقليدي والتجاري" subtitle="طن شهريًا" lines={[om('FISH_ARTISANAL', 'صيد تقليدي'), om('FISH_COMMERCIAL', 'صيد تجاري')]} />
        <SeriesChart title="إجمالي الأسماك المنزلة" subtitle="طن شهريًا" kind="columns" lines={[om('FISH_LANDED', 'الإجمالي')]} />
        <SeriesChart title="الاستزراع السمكي" subtitle="طن" kind="columns" lines={[om('FISH_AQUACULTURE', 'استزراع')]} />
        <SeriesChart title="إنتاج الصيد البحري (طن)" lines={gccLines('wb.ER.FSH.CAPT.MT')} from="1990" />
        <SeriesChart title="إنتاج الاستزراع والصيد (طن)" lines={gccLines('wb.ER.FSH.PROD.MT')} from="1990" />
      </Section>

      <Section title="المياه" lead="إنتاج المياه واستهلاكها شهريًا حتى آخر شهر منشور، والاستهلاك حسب القطاع، والتوصيلات والصرف الصحي؛ مع الإجهاد المائي مقارنةً بدول الخليج.">
        <SeriesChart title="إنتاج المياه (شهري)" subtitle="متر مكعب" kind="columns" last={60} lines={[om('WATER_PROD_M', 'إنتاج المياه')]} />
        <SeriesChart title="استهلاك المياه (شهري)" subtitle="مليون متر مكعب" kind="columns" last={60} lines={[om('WATER_CONS_M', 'استهلاك المياه')]} />
        <SeriesChart title="استهلاك المياه حسب القطاع" subtitle="مليون متر مكعب" kind="stacked" lines={[om('WATER_CONS_RES', 'سكني', 'var(--series-1)'), om('WATER_CONS_COM', 'تجاري', 'var(--series-2)'), om('WATER_CONS_GOV', 'حكومي', 'var(--series-3)')]} />
        <SeriesChart title="توصيلات المياه" subtitle="توصيلة" kind="columns" lines={[om('WATER_CONNECTIONS', 'عدد التوصيلات')]} />
        <SeriesChart title="الوصول إلى الصرف الصحي المحسّن" subtitle="% من السكان" lines={[om('WATER_SANITATION', 'الصرف الصحي')]} />
        <SeriesChart title="الإجهاد المائي (السحب إلى الموارد المتجددة)" lines={peerLines('wb.ER.H2O.FWST.ZS')} from="2000" />
      </Section>

      <Section title="الأراضي والإسكان" lead="القطع المخططة والممنوحة وإباحات البناء (المركز الوطني للإحصاء، سنوات منشورة محدودة).">
        <SeriesChart title="قطع الأراضي المخططة والممنوحة" subtitle="قطعة" kind="columns" lines={[om('LAND_PLANNED', 'مخططة'), om('LAND_GRANTED', 'ممنوحة'), om('LAND_REGISTERED', 'مسجلة لأول مرة')]} />
        <SeriesChart title="إباحات البناء" subtitle="إباحة" kind="columns" lines={[om('BUILDING_PERMITS', 'إباحات البناء')]} />
      </Section>

      <Section title="المنشآت الاقتصادية والعاملون" lead="عدد المنشآت والعاملين في الزراعة والتعدين والصناعة التحويلية والتشييد والتجارة (سنوات منشورة محدودة).">
        <SeriesChart title="عدد المنشآت حسب النشاط" kind="columns" lines={[om('EST_AGRI', 'زراعة وصيد'), om('EST_MINING', 'تعدين ومحاجر'), om('EST_MANUFACTURING', 'صناعة تحويلية'), om('EST_CONSTRUCTION', 'تشييد')]} />
        <SeriesChart title="العاملون حسب النشاط" kind="columns" lines={[om('EST_AGRI_WORKERS', 'زراعة وصيد'), om('EST_MINING_WORKERS', 'تعدين ومحاجر'), om('EST_MANUF_WORKERS', 'صناعة تحويلية')]} />
      </Section>
    </>
  );
}
