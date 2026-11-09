import { Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SwitchChart } from '@/core/components/SwitchChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { gccLines, peerLines } from '@/core/groups';
import { ScoreTable, type ScoreRow } from '@/modules/fiscal/GulfScorecard';
import { SchoolRatios } from './HumanDerived';

/** الصورة العامة: التنمية البشرية ومكوّناتها (صحة وتعليم ودخل) وترتيب عُمان عالميًا. */
export function HumanOverviewTab() {
  return (
    <>
      <Section title="مؤشر التنمية البشرية ومكوّناته">
        <SeriesChart wide title="مؤشر التنمية البشرية" lines={peerLines('undp.hdi')} from="1990" />
        <SeriesChart wide title="العمر المتوقع عند الولادة" lines={peerLines('undp.le')} from="1990" />
        <SeriesChart wide title="سنوات الدراسة المتوقعة" lines={peerLines('undp.eys')} from="1990" />
        <SeriesChart wide title="نصيب الفرد من الدخل القومي (تعادل القوة الشرائية)" lines={peerLines('undp.gnipc')} from="1990" />
      </Section>
      <Section title="عُمان في العالم">
        <WorldRankCard title="مؤشر التنمية البشرية" indicator="undp.hdi" />
        <WorldRankCard title="العمر المتوقع" indicator="undp.le" />
        <WorldRankCard title="سنوات الدراسة المتوقعة" indicator="undp.eys" />
        <WorldRankCard title="الإنفاق الصحي من الناتج" indicator="wb.SH.XPD.CHEX.GD.ZS" />
      </Section>
    </>
  );
}

/** الصحة: الإنفاق والكوادر والعمر الصحي والأمراض وصحة الأطفال والأمهات. */
export function HumanHealthTab() {
  return (
    <>
      <Section title="الصحة">
        <SeriesChart wide title="الإنفاق الصحي الجاري" lines={peerLines('wb.SH.XPD.CHEX.GD.ZS')} from="2000" />
        <SeriesChart wide title="وفيات الرضع" lines={peerLines('wb.SP.DYN.IMRT.IN')} from="1990" />
        <SeriesChart wide title="الأطباء لكل 1000 نسمة" lines={peerLines('wb.SH.MED.PHYS.ZS')} from="1995" />
        <SeriesChart wide title="أسرّة المستشفيات لكل 1000" lines={peerLines('wb.SH.MED.BEDS.ZS')} from="1995" />
      </Section>
      <Section title="مؤشرات صحية إضافية" lead="من منظمة الصحة العالمية: صحة السكان والأمراض غير المعدية وتغطية الخدمات.">
        <SeriesChart wide title="العمر الصحي المتوقع عند الولادة" lines={peerLines('who.hale')} from="2000" />
        <SeriesChart wide title="السمنة بين البالغين (معيارية العمر)" lines={peerLines('who.obesity')} from="2000" />
        <SeriesChart wide title="مؤشر تغطية الخدمات الصحية الشاملة" lines={peerLines('who.uhc_index')} from="2000" />
        <SeriesChart wide title="الوفاة المبكرة من الأمراض غير المعدية (30–70)" lines={peerLines('who.ncd_mortality')} from="2000" />
        <SeriesChart wide title="الأطباء لكل 10 آلاف نسمة" lines={peerLines('who.doctors')} from="2000" />
        <SeriesChart wide title="التمريض والقبالة لكل 10 آلاف نسمة" lines={peerLines('who.nurses')} from="2000" />
      </Section>
      <Section title="إنفاق وزارة الصحة" lead="مليون ريال عُماني، سنويًا (المركز الوطني للإحصاء).">
        <SeriesChart wide title="إنفاق وزارة الصحة" lines={[{ indicator: 'ncsi.HEALTH_MOH_SPENDING', entity: 'OMN', label: 'إجمالي الإنفاق' }]} kind="columns" />
      </Section>
      <Section title="صحة الأطفال والأمهات (اليونيسف والبنك الدولي)" lead="وفيات الأطفال حسب الفئة العمرية بتقديرات الأمم المتحدة المشتركة (IGME)، مقارنةً بدول الخليج.">
        <SeriesChart wide title="وفيات الأطفال دون الخامسة (لكل 1000)" lines={peerLines('unicef.under5_mortality')} from="1990" />
        <SeriesChart wide title="وفيات الرضّع (لكل 1000)" lines={peerLines('unicef.infant_mortality')} from="1990" />
        <SeriesChart wide title="وفيات حديثي الولادة (لكل 1000)" lines={peerLines('unicef.neonatal_mortality')} from="1990" />
        <SeriesChart wide title="وفيات الأطفال من سنة إلى أربع (لكل 1000)" lines={peerLines('unicef.child_mortality_1_4')} from="1990" />
      </Section>
    </>
  );
}

/** التعليم والبحث: الالتحاق والمعلمون والإنفاق، ثم البحث والتعلم. */
export function HumanEducationTab() {
  return (
    <>
      <Section id="enrol" title="من يلتحق بالتعليم؟" lead="الالتحاق الإجمالي في المراحل الثلاث، ومعلمو المدارس والطلبة، ومحو الأمية، والإنفاق على التعليم (اليونسكو والبنك الدولي والمركز الخليجي).">
        <SwitchChart wide height={340} title="الالتحاق بالتعليم" variants={[
          { value: 'stages', label: 'المراحل: عُمان', subtitle: '% إجمالي الالتحاق', from: '1995', lines: [{ indicator: 'unesco.ger_primary', entity: 'OMN', label: 'الابتدائي', color: 'var(--series-1)' }, { indicator: 'unesco.ger_secondary', entity: 'OMN', label: 'الثانوي', color: 'var(--series-3)' }, { indicator: 'unesco.ger_tertiary', entity: 'OMN', label: 'العالي', color: 'var(--series-2)' }] },
          { value: 'tert', label: 'العالي: الخليج', subtitle: '% إجمالي الالتحاق', from: '1995', lines: gccLines('unesco.ger_tertiary') },
          { value: 'sec', label: 'الثانوي: الخليج', subtitle: '% إجمالي الالتحاق', from: '1995', lines: gccLines('unesco.ger_secondary') },
          { value: 'lit', label: 'محو الأمية: الخليج', subtitle: '% من الكبار 15+', from: '2003', lines: gccLines('unesco.literacy_adult') },
          { value: 'spend', label: 'الإنفاق على التعليم: الخليج', subtitle: '% من الناتج', from: '1995', lines: gccLines('wb.SE.XPD.TOTL.GD.ZS') },
        ]} />
        <SchoolRatios />
      </Section>
      <Section title="البحث والتعلم">
        <SeriesChart wide title="الباحثون لكل مليون نسمة" lines={peerLines('wb.SP.POP.SCIE.RD.P6')} from="2000" />
        <SeriesChart wide title="المقالات العلمية والتقنية" lines={peerLines('wb.IP.JRN.ARTC.SC')} from="2000" />
        <SeriesChart wide title="سنوات الدراسة المعدَّلة بالتعلم" lines={peerLines('wb.HD.HCI.LAYS')} from="2010" />
        <SeriesChart wide title="نتيجة TIMSS في الرياضيات (الصف الثامن)" lines={peerLines('wb.LO.TIMSS.MAT8')} from="1995" />
      </Section>
    </>
  );
}

/** الثروة والمساواة: ثروة الأمة (ما وراء النفط)، وعدم المساواة، والاستدامة، والعمل والمساواة. */
export function HumanWealthTab() {
  return (
    <>
      <Section title="ثروة الأمة: ما وراء النفط" lead="حسابات الثروة للبنك الدولي تقيس رصيد الدولة من رأس المال بأنواعه للفرد: المنتَج (مصانع وبنية تحتية)، والبشري، والطبيعي المتجدد وغير المتجدد (نفط وغاز ومعادن)، والأصول الأجنبية. تنويع الثروة هو جوهر التنويع الاقتصادي.">
        <SeriesChart
          wide
          title="تركيبة ثروة الفرد في عُمان"
          subtitle="دولار ثابت 2019 للفرد"
          kind="stacked"
          lines={[
            { indicator: 'wb.NW.HCA.PC', entity: 'OMN', label: 'رأس المال البشري', color: 'var(--series-1)' },
            { indicator: 'wb.NW.PCA.PC', entity: 'OMN', label: 'رأس المال المنتَج', color: 'var(--series-2)' },
            { indicator: 'wb.NW.NCA.SSOI.PC', entity: 'OMN', label: 'النفط والغاز والمعادن', color: 'var(--series-4)' },
            { indicator: 'wb.NW.NCA.TOTL.PC', entity: 'OMN', label: 'رأس المال الطبيعي المتجدد', color: 'var(--series-3)' },
            { indicator: 'wb.NW.NFA.PC', entity: 'OMN', label: 'الأصول الأجنبية الصافية', color: 'var(--series-7)' },
          ]}
        />
        <SeriesChart wide title="الثروة الشاملة للفرد" subtitle="دولار ثابت 2019" lines={peerLines('wb.NW.TOW.PC')} from="1995" />
        <SeriesChart wide title="رأس المال البشري للفرد" subtitle="دولار ثابت 2019" lines={peerLines('wb.NW.HCA.PC')} from="1995" />
        <SeriesChart wide title="رأس المال الطبيعي غير المتجدد للفرد" subtitle="دولار ثابت 2019" lines={peerLines('wb.NW.NCA.SSOI.PC')} from="1995" />
      </Section>
      <Section title="عدم المساواة">
        <SeriesChart wide title="مؤشر التنمية البشرية المعدَّل بعدم المساواة" lines={peerLines('undp.ihdi')} from="2010" />
        <SeriesChart wide title="مؤشر عدم المساواة بين الجنسين (الأقل أفضل)" lines={peerLines('undp.gii')} from="1995" />
        <SeriesChart wide title="مؤشر التنمية حسب الجنس" lines={peerLines('undp.gdi')} from="1990" />
        <SeriesChart wide title="معدل الولادة بين المراهقات" lines={peerLines('undp.abr')} from="1990" />
      </Section>
      <Section title="الاستدامة والصحة">
        <SeriesChart wide title="مؤشر التنمية المعدَّل بالضغوط الكوكبية" lines={peerLines('undp.phdi')} from="1990" />
        <SeriesChart wide title="انبعاثات CO₂ للفرد" lines={peerLines('undp.co2_prod')} from="1990" />
        <SeriesChart wide title="معدل وفيات الأمهات" lines={peerLines('undp.mmr')} from="2000" />
      </Section>
      <Section title="العمل والمساواة">
        <SeriesChart wide title="معدل البطالة" lines={gccLines('gccstat.UNEMPLOYMENT')} />
        <SeriesChart wide title="نسبة العاملين إلى السكان" lines={peerLines('wb.SL.EMP.TOTL.SP.ZS')} from="2000" />
        <SeriesChart wide title="المقاعد النيابية للنساء" lines={peerLines('wb.SG.GEN.PARL.ZS')} from="2000" />
        <SeriesChart wide title="التوظيف في الخدمات" lines={peerLines('wb.SL.SRV.EMPL.ZS')} from="2000" />
      </Section>
    </>
  );
}

/** الأمن الغذائي: المؤشر الوطني ومكوّناته واعتماد الحبوب على الاستيراد. */
export function HumanFoodTab() {
  return (
    <>
      <Section title="مؤشر الأمن الغذائي الوطني" lead="مؤشر مركّب (0–1) ومكوّناته الأربعة: التوافر والإتاحة وتحمّل الكلفة والاستخدام. التوافر وتحمّل الكلفة أضعف حلقتين. (المركز الوطني للإحصاء)">
        <SeriesChart wide title="مؤشر الأمن الغذائي" lines={[{ indicator: 'ncsi.FOOD_SECURITY_INDEX', entity: 'OMN', label: 'المؤشر المركّب' }]} />
        <SeriesChart
          wide
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
        <SeriesChart wide title="نسبة اعتماد الحبوب على الاستيراد" lines={peerLines('fao.cereal_import_dependency')} from="2005" />
        <SeriesChart wide title="واردات الغذاء إلى الصادرات السلعية" lines={peerLines('fao.food_imports_to_exports')} from="2005" />
        <SeriesChart wide title="كفاية الإمداد بالطاقة الغذائية" lines={peerLines('fao.energy_supply_adequacy')} from="2005" />
        <SeriesChart wide title="تقزّم الأطفال دون الخامسة" lines={peerLines('fao.child_stunting')} from="2000" />
      </Section>
    </>
  );
}

/** الديموغرافيا: السكان الحاليون والإسقاطات حتى 2050 و2100 والمدى الطويل. */
export function HumanDemographyTab() {
  return (
    <>
      <Section title="السكان">
        <SeriesChart wide title="عدد السكان" lines={gccLines('gccstat.POP')} />
        <SeriesChart wide title="السكان دون 15 سنة" lines={peerLines('wb.SP.POP.0014.TO.ZS')} from="1990" />
        <SeriesChart wide title="المهاجرون الدوليون" lines={peerLines('wb.SM.POP.TOTL.ZS')} from="1990" />
        <SeriesChart wide title="معدل الخصوبة" lines={peerLines('wb.SP.DYN.TFRT.IN')} from="1990" />
      </Section>
      <Section title="السكان حتى 2050" lead="تقديرات وتوقعات البنك الدولي: التحول الديموغرافي يحدد حجم سوق العمل وعبء التقاعد والصحة. الخط المتقطع توقعات.">
        <SeriesChart wide title="عدد السكان" lines={peerLines('wbp.SP.POP.TOTL')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
        <SeriesChart wide title="السكان 65 سنة فأكثر" subtitle="% من السكان" lines={peerLines('wbp.SP.POP.65UP.TO.ZS')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
        <SeriesChart wide title="نسبة الإعالة العمرية" lines={peerLines('wbp.SP.POP.DPND')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
        <SeriesChart wide title="الأطفال دون 15 سنة" subtitle="% من السكان" lines={peerLines('wbp.SP.POP.0014.TO.ZS')} from="1990" forecastFromYear={new Date().getFullYear() + 1} />
      </Section>
      <Section title="التركيبة السكانية حسب الجنسية والنوع (المركز الإحصائي الخليجي)" lead="كم يشكّل المواطنون وغير المواطنين، والذكور والإناث، وكيف تتطور المواليد والوفيات؟">
        <SeriesChart wide title="سكان عُمان: مواطنون وغير مواطنين" kind="stacked" lines={[{ indicator: 'gccstat.POP_CITIZEN', entity: 'OMN', label: 'مواطنون', color: 'var(--series-3)' }, { indicator: 'gccstat.POP_NON_CITIZEN', entity: 'OMN', label: 'غير مواطنين', color: 'var(--series-2)' }]} />
        <SeriesChart wide title="سكان عُمان حسب النوع والجنسية" kind="stacked" lines={[{ indicator: 'gccstat.POP_CITIZEN_MALE', entity: 'OMN', label: 'مواطنون ذكور', color: 'var(--series-1)' }, { indicator: 'gccstat.POP_CITIZEN_FEMALE', entity: 'OMN', label: 'مواطنات', color: 'var(--series-5)' }, { indicator: 'gccstat.POP_NON_CITIZEN_MALE', entity: 'OMN', label: 'غير مواطنين ذكور', color: 'var(--series-2)' }, { indicator: 'gccstat.POP_NON_CITIZEN_FEMALE', entity: 'OMN', label: 'غير مواطنات', color: 'var(--series-4)' }]} />
        <SeriesChart wide title="المواليد والوفيات في عُمان" lines={[{ indicator: 'gccstat.BIRTHS', entity: 'OMN', label: 'مواليد' }, { indicator: 'gccstat.DEATHS', entity: 'OMN', label: 'وفيات' }]} />
        <SeriesChart wide title="العاملون في عُمان حسب النوع" kind="stacked" lines={[{ indicator: 'gccstat.EMPLOYED_MALE', entity: 'OMN', label: 'ذكور', color: 'var(--series-1)' }, { indicator: 'gccstat.EMPLOYED_FEMALE', entity: 'OMN', label: 'إناث', color: 'var(--series-5)' }]} />
        <SeriesChart wide title="معلمو المدارس (الخليج)" lines={gccLines('gccstat.TEACHERS_SCHOOL')} />
        <SeriesChart wide title="أطباء الأسنان (الخليج)" lines={gccLines('gccstat.DENTISTS')} />
        <SeriesChart wide title="الصيادلة (الخليج)" lines={gccLines('gccstat.PHARMACISTS')} />
        <SeriesChart wide title="السكان المتصلون بالكهرباء (%)" lines={gccLines('gccstat.ACCESS_ELECTRICITY')} />
      </Section>
      <Section title="السكان إلى 2100 (الأمم المتحدة)" lead="تقديرات وإسقاطات شعبة السكان في الأمم المتحدة (السيناريو المتوسط) عبر OWID: كم سيبلغ عدد سكان عُمان ودول الخليج، وكيف يشيخ المجتمع؟ الإسقاطات تقدير لا توقع قاطع.">
        <SeriesChart wide height={340} title="عدد سكان عُمان: تقديرات وإسقاط حتى 2100" subtitle="نسمة" lines={[{ indicator: 'owid.population', entity: 'OMN', label: 'تقديرات' }, { indicator: 'owid.population_proj', entity: 'OMN', label: 'إسقاط الأمم المتحدة' }]} />
        <SeriesChart wide title="إسقاط عدد السكان في دول الخليج" subtitle="نسمة — حتى 2100" lines={gccLines('owid.population_proj')} from="2020" />
        <SeriesChart wide title="العمر الوسيط: عُمان تقديرات وإسقاط" subtitle="سنة" lines={[{ indicator: 'owid.median_age', entity: 'OMN', label: 'تقديرات' }, { indicator: 'owid.median_age_proj', entity: 'OMN', label: 'إسقاط' }]} />
        <SeriesChart wide title="إسقاط العمر الوسيط في دول الخليج" subtitle="سنة" lines={gccLines('owid.median_age_proj')} from="2020" />
        <SeriesChart wide title="العمر المتوقع عند الميلاد (سلسلة طويلة)" subtitle="سنة" lines={peerLines('owid.life_expectancy_long')} from="1950" />
        <SeriesChart wide title="نسبة سكان الحضر" subtitle="%" lines={gccLines('owid.urban_share')} from="1950" />
      </Section>
      <Section title="نظرة طويلة المدى (Our World in Data)" lead="سلاسل تاريخية طويلة تجمعها OWID من مصادرها الأصلية: الناتج للفرد منذ 1820، والغذاء والطاقة والنظام السياسي والجريمة والبيئة.">
        <SeriesChart wide height={340} title="الناتج المحلي للفرد منذ 1820" subtitle="دولار ثابت 2011 — Maddison" lines={peerLines('owid.gdp_pc_maddison')} from="1900" />
        <SeriesChart wide title="السعرات اليومية المتاحة للفرد" lines={gccLines('owid.calories')} from="1961" />
        <SeriesChart wide title="المتاح من اللحوم للفرد" subtitle="كغ/سنة" lines={gccLines('owid.meat_supply')} from="1961" />
        <SeriesChart wide title="استهلاك الطاقة الأولية للفرد" subtitle="كيلوواط ساعة" lines={peerLines('owid.energy_per_capita')} from="1965" />
        <SeriesChart wide title="حصة المتجددة من الطاقة الأولية" subtitle="%" lines={peerLines('owid.renewables_energy_share')} from="1990" />
        <SeriesChart wide title="إنتاج النفط" subtitle="تيراواط ساعة" lines={gccLines('owid.oil_production')} from="1965" />
        <SeriesChart wide title="معدل جرائم القتل لكل 100 ألف" subtitle="UNODC" lines={peerLines('owid.homicide_rate')} from="2000" />
        <SeriesChart wide title="النظام السياسي (Regimes of the World)" subtitle="0 استبداد مغلق — 3 ديمقراطية كاملة" lines={gccLines('owid.political_regime')} from="1950" />
        <SeriesChart wide title="النفايات البلاستيكية للفرد" subtitle="كغ/يوم" lines={gccLines('owid.plastic_waste')} />
        <SeriesChart wide title="انبعاثات الميثان (مكافئ CO2)" subtitle="طن" lines={gccLines('owid.methane')} from="1990" />
      </Section>
    </>
  );
}

const ROWS: ScoreRow[] = [
  { key: 'undp.hdi', label: 'مؤشر التنمية البشرية', better: 'high', digits: 3 },
  { key: 'undp.le', label: 'العمر المتوقع (سنة)', better: 'high' },
  { key: 'undp.eys', label: 'سنوات الدراسة المتوقعة', better: 'high' },
  { key: 'undp.gii', label: 'عدم المساواة بين الجنسين (الأقل أفضل)', better: 'low', digits: 3 },
  { key: 'wb.SH.XPD.CHEX.GD.ZS', label: 'الإنفاق الصحي (% من الناتج)', better: 'high' },
  { key: 'wb.SP.DYN.IMRT.IN', label: 'وفيات الرضع (لكل 1000)', better: 'low' },
  { key: 'unesco.ger_tertiary', label: 'الالتحاق بالتعليم العالي (%)', better: 'high', digits: 0 },
  { key: 'wb.SP.POP.SCIE.RD.P6', label: 'الباحثون (لكل مليون)', better: 'high', digits: 0 },
];

/** الخليج: بطاقة أداء التنمية البشرية للدول الست. */
export function HumanGulfTab() {
  return (
    <Section id="scorecard" title="أين نقف بين الجيران؟" lead="آخر قيمة متاحة لكل دولة؛ الأخضر الأفضل بين الدول الست.">
      <ScoreTable rows={ROWS} title="بطاقة أداء التنمية البشرية الخليجية" subtitle="آخر قيمة فعلية — UNDP والبنك الدولي واليونسكو" note="الأخضر الأفضل بين الدول الست في كل صف (الأقل أفضل في عدم المساواة ووفيات الرضع). تتفاوت سنوات آخر قيمة بين الدول والمؤشرات." />
    </Section>
  );
}
