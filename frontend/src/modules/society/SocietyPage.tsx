import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { gccLines, peerLines } from '@/core/groups';

export default function SocietyPage() {
  return (
    <>
      <PageHead title="المجتمع والصحة والعمل" lead="السكان والتركيبة العمرية والصحة والعمل والمساواة: عُمان مقابل دول الخليج، من المركز الإحصائي الخليجي والبنك الدولي." />
      <Section title="السكان">
        <SeriesChart title="عدد السكان" lines={gccLines('gccstat.POP')} />
        <SeriesChart title="السكان دون 15 سنة" lines={peerLines('wb.SP.POP.0014.TO.ZS')} from="1990" />
        <SeriesChart title="المهاجرون الدوليون" lines={peerLines('wb.SM.POP.TOTL.ZS')} from="1990" />
        <SeriesChart title="معدل الخصوبة" lines={peerLines('wb.SP.DYN.TFRT.IN')} from="1990" />
      </Section>
      <Section title="الصحة">
        <SeriesChart title="الإنفاق الصحي الجاري" lines={peerLines('wb.SH.XPD.CHEX.GD.ZS')} from="2000" />
        <SeriesChart title="وفيات الرضع" lines={peerLines('wb.SP.DYN.IMRT.IN')} from="1990" />
        <SeriesChart title="الأطباء لكل 1000 نسمة" lines={peerLines('wb.SH.MED.PHYS.ZS')} from="1995" />
        <SeriesChart title="أسرّة المستشفيات لكل 1000" lines={peerLines('wb.SH.MED.BEDS.ZS')} from="1995" />
      </Section>
      <Section title="السكان حتى 2050" lead="تقديرات وتوقعات البنك الدولي: التحول الديموغرافي يحدد حجم سوق العمل وعبء التقاعد والصحة. الخط المتقطع توقعات.">
        <SeriesChart title="عدد السكان" lines={peerLines('wbp.SP.POP.TOTL')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
        <SeriesChart title="السكان 65 سنة فأكثر" subtitle="% من السكان" lines={peerLines('wbp.SP.POP.65UP.TO.ZS')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
        <SeriesChart title="نسبة الإعالة العمرية" lines={peerLines('wbp.SP.POP.DPND')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
        <SeriesChart title="الأطفال دون 15 سنة" subtitle="% من السكان" lines={peerLines('wbp.SP.POP.0014.TO.ZS')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
      </Section>
      <Section title="مؤشرات صحية إضافية" lead="من منظمة الصحة العالمية: صحة السكان والأمراض غير المعدية وتغطية الخدمات.">
        <SeriesChart title="العمر الصحي المتوقع عند الولادة" lines={peerLines('who.hale')} from="2000" />
        <SeriesChart title="السمنة بين البالغين (معيارية العمر)" lines={peerLines('who.obesity')} from="2000" />
        <SeriesChart title="مؤشر تغطية الخدمات الصحية الشاملة" lines={peerLines('who.uhc_index')} from="2000" />
        <SeriesChart title="الوفاة المبكرة من الأمراض غير المعدية (30–70)" lines={peerLines('who.ncd_mortality')} from="2000" />
        <SeriesChart title="الأطباء لكل 10 آلاف نسمة" lines={peerLines('who.doctors')} from="2000" />
        <SeriesChart title="التمريض والقبالة لكل 10 آلاف نسمة" lines={peerLines('who.nurses')} from="2000" />
      </Section>
      <Section title="مؤشر الأمن الغذائي الوطني" lead="مؤشر مركّب (0–1) ومكوّناته الأربعة: التوافر والإتاحة وتحمّل الكلفة والاستخدام. التوافر وتحمّل الكلفة أضعف حلقتين. (المركز الوطني للإحصاء)">
        <SeriesChart title="مؤشر الأمن الغذائي" lines={[{ indicator: 'ncsi.FOOD_SECURITY_INDEX', entity: 'OMN', label: 'المؤشر المركّب' }]} />
        <SeriesChart
          title="مكوّنات الأمن الغذائي"
          lines={[
            { indicator: 'ncsi.FOOD_AVAILABILITY', entity: 'OMN', label: 'التوافر', color: 'var(--series-1)' },
            { indicator: 'ncsi.FOOD_ACCESSIBILITY', entity: 'OMN', label: 'الإتاحة', color: 'var(--series-3)' },
            { indicator: 'ncsi.FOOD_AFFORDABILITY', entity: 'OMN', label: 'تحمّل الكلفة', color: 'var(--series-2)' },
            { indicator: 'ncsi.FOOD_UTILIZATION', entity: 'OMN', label: 'الاستخدام', color: 'var(--series-7)' },
          ]}
        />
      </Section>
      <Section title="الأمن الغذائي" lead="عُمان تستورد معظم حبوبها: نسبة الاعتماد على الاستيراد وكلفة واردات الغذاء مؤشران مباشران على المخاطر الغذائية (منظمة الأغذية والزراعة).">
        <SeriesChart title="نسبة اعتماد الحبوب على الاستيراد" lines={peerLines('fao.cereal_import_dependency')} from="2005" />
        <SeriesChart title="واردات الغذاء إلى الصادرات السلعية" lines={peerLines('fao.food_imports_to_exports')} from="2005" />
        <SeriesChart title="كفاية الإمداد بالطاقة الغذائية" lines={peerLines('fao.energy_supply_adequacy')} from="2005" />
        <SeriesChart title="تقزّم الأطفال دون الخامسة" lines={peerLines('fao.child_stunting')} from="2000" />
      </Section>
      <Section title="إنفاق وزارة الصحة" lead="مليون ريال عُماني، سنويًا (المركز الوطني للإحصاء).">
        <SeriesChart title="إنفاق وزارة الصحة" lines={[{ indicator: 'ncsi.HEALTH_MOH_SPENDING', entity: 'OMN', label: 'إجمالي الإنفاق' }]} kind="columns" />
      </Section>
      <Section title="العمل والمساواة">
        <SeriesChart title="معدل البطالة" lines={gccLines('gccstat.UNEMPLOYMENT')} />
        <SeriesChart title="نسبة العاملين إلى السكان" lines={peerLines('wb.SL.EMP.TOTL.SP.ZS')} from="2000" />
        <SeriesChart title="المقاعد النيابية للنساء" lines={peerLines('wb.SG.GEN.PARL.ZS')} from="2000" />
        <SeriesChart title="التوظيف في الخدمات" lines={peerLines('wb.SL.SRV.EMPL.ZS')} from="2000" />
      </Section>
      <Section title="عُمان في العالم">
        <WorldRankCard title="الإنفاق الصحي من الناتج" indicator="wb.SH.XPD.CHEX.GD.ZS" />
        <WorldRankCard title="متوسط العمر المتوقع" indicator="wb.SP.DYN.LE00.IN" />
      </Section>
      <Section title="التركيبة السكانية حسب الجنسية والنوع (المركز الإحصائي الخليجي)" lead="كم يشكّل المواطنون وغير المواطنين، والذكور والإناث، وكيف تتطور المواليد والوفيات؟">
        <SeriesChart title="سكان عُمان: مواطنون وغير مواطنين" kind="stacked" lines={[{ indicator: 'gccstat.POP_CITIZEN', entity: 'OMN', label: 'مواطنون', color: 'var(--series-3)' }, { indicator: 'gccstat.POP_NON_CITIZEN', entity: 'OMN', label: 'غير مواطنين', color: 'var(--series-2)' }]} />
        <SeriesChart title="سكان عُمان حسب النوع والجنسية" kind="stacked" lines={[{ indicator: 'gccstat.POP_CITIZEN_MALE', entity: 'OMN', label: 'مواطنون ذكور', color: 'var(--series-1)' }, { indicator: 'gccstat.POP_CITIZEN_FEMALE', entity: 'OMN', label: 'مواطنات', color: 'var(--series-5)' }, { indicator: 'gccstat.POP_NON_CITIZEN_MALE', entity: 'OMN', label: 'غير مواطنين ذكور', color: 'var(--series-2)' }, { indicator: 'gccstat.POP_NON_CITIZEN_FEMALE', entity: 'OMN', label: 'غير مواطنات', color: 'var(--series-4)' }]} />
        <SeriesChart title="المواليد والوفيات في عُمان" lines={[{ indicator: 'gccstat.BIRTHS', entity: 'OMN', label: 'مواليد' }, { indicator: 'gccstat.DEATHS', entity: 'OMN', label: 'وفيات' }]} />
        <SeriesChart title="العاملون في عُمان حسب النوع" kind="stacked" lines={[{ indicator: 'gccstat.EMPLOYED_MALE', entity: 'OMN', label: 'ذكور', color: 'var(--series-1)' }, { indicator: 'gccstat.EMPLOYED_FEMALE', entity: 'OMN', label: 'إناث', color: 'var(--series-5)' }]} />
        <SeriesChart title="معلمو المدارس (الخليج)" lines={gccLines('gccstat.TEACHERS_SCHOOL')} />
        <SeriesChart title="أطباء الأسنان (الخليج)" lines={gccLines('gccstat.DENTISTS')} />
        <SeriesChart title="الصيادلة (الخليج)" lines={gccLines('gccstat.PHARMACISTS')} />
        <SeriesChart title="السكان المتصلون بالكهرباء (%)" lines={gccLines('gccstat.ACCESS_ELECTRICITY')} />
      </Section>
      <Section title="صحة الأطفال والأمهات (اليونيسف والبنك الدولي)" lead="وفيات الأطفال حسب الفئة العمرية بتقديرات الأمم المتحدة المشتركة (IGME)، مقارنةً بدول الخليج.">
        <SeriesChart title="وفيات الأطفال دون الخامسة (لكل 1000)" lines={peerLines('unicef.under5_mortality')} from="1990" />
        <SeriesChart title="وفيات الرضّع (لكل 1000)" lines={peerLines('unicef.infant_mortality')} from="1990" />
        <SeriesChart title="وفيات حديثي الولادة (لكل 1000)" lines={peerLines('unicef.neonatal_mortality')} from="1990" />
        <SeriesChart title="وفيات الأطفال من سنة إلى أربع (لكل 1000)" lines={peerLines('unicef.child_mortality_1_4')} from="1990" />
      </Section>

      <Section title="السكان إلى 2100 (الأمم المتحدة)" lead="تقديرات وإسقاطات شعبة السكان في الأمم المتحدة (السيناريو المتوسط) عبر OWID: كم سيبلغ عدد سكان عُمان ودول الخليج، وكيف يشيخ المجتمع؟ الإسقاطات تقدير لا توقع قاطع.">
        <SeriesChart wide height={340} title="عدد سكان عُمان: تقديرات وإسقاط حتى 2100" subtitle="نسمة" lines={[{ indicator: 'owid.population', entity: 'OMN', label: 'تقديرات' }, { indicator: 'owid.population_proj', entity: 'OMN', label: 'إسقاط الأمم المتحدة' }]} />
        <SeriesChart title="إسقاط عدد السكان في دول الخليج" subtitle="نسمة — حتى 2100" lines={gccLines('owid.population_proj')} from="2020" />
        <SeriesChart title="العمر الوسيط: عُمان تقديرات وإسقاط" subtitle="سنة" lines={[{ indicator: 'owid.median_age', entity: 'OMN', label: 'تقديرات' }, { indicator: 'owid.median_age_proj', entity: 'OMN', label: 'إسقاط' }]} />
        <SeriesChart title="إسقاط العمر الوسيط في دول الخليج" subtitle="سنة" lines={gccLines('owid.median_age_proj')} from="2020" />
        <SeriesChart title="العمر المتوقع عند الميلاد (سلسلة طويلة)" subtitle="سنة" lines={peerLines('owid.life_expectancy_long')} from="1950" />
        <SeriesChart title="نسبة سكان الحضر" subtitle="%" lines={gccLines('owid.urban_share')} from="1950" />
      </Section>

      <Section title="نظرة طويلة المدى (Our World in Data)" lead="سلاسل تاريخية طويلة تجمعها OWID من مصادرها الأصلية: الناتج للفرد منذ 1820، والغذاء والطاقة والنظام السياسي والجريمة والبيئة.">
        <SeriesChart wide height={340} title="الناتج المحلي للفرد منذ 1820" subtitle="دولار ثابت 2011 — Maddison" lines={peerLines('owid.gdp_pc_maddison')} from="1900" />
        <SeriesChart title="السعرات اليومية المتاحة للفرد" lines={gccLines('owid.calories')} from="1961" />
        <SeriesChart title="المتاح من اللحوم للفرد" subtitle="كغ/سنة" lines={gccLines('owid.meat_supply')} from="1961" />
        <SeriesChart title="استهلاك الطاقة الأولية للفرد" subtitle="كيلوواط ساعة" lines={peerLines('owid.energy_per_capita')} from="1965" />
        <SeriesChart title="حصة المتجددة من الطاقة الأولية" subtitle="%" lines={peerLines('owid.renewables_energy_share')} from="1990" />
        <SeriesChart title="إنتاج النفط" subtitle="تيراواط ساعة" lines={gccLines('owid.oil_production')} from="1965" />
        <SeriesChart title="معدل جرائم القتل لكل 100 ألف" subtitle="UNODC" lines={peerLines('owid.homicide_rate')} from="2000" />
        <SeriesChart title="النظام السياسي (Regimes of the World)" subtitle="0 استبداد مغلق — 3 ديمقراطية كاملة" lines={gccLines('owid.political_regime')} from="1950" />
        <SeriesChart title="النفايات البلاستيكية للفرد" subtitle="كغ/يوم" lines={gccLines('owid.plastic_waste')} />
        <SeriesChart title="انبعاثات الميثان (مكافئ CO2)" subtitle="طن" lines={gccLines('owid.methane')} from="1990" />
      </Section>
    </>
  );
}
