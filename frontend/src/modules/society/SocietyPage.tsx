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
      <Section title="الأمن الغذائي" lead="عُمان تستورد معظم حبوبها: نسبة الاعتماد على الاستيراد وكلفة واردات الغذاء مؤشران مباشران على المخاطر الغذائية (منظمة الأغذية والزراعة).">
        <SeriesChart title="نسبة اعتماد الحبوب على الاستيراد" lines={peerLines('fao.cereal_import_dependency')} from="2005" />
        <SeriesChart title="واردات الغذاء إلى الصادرات السلعية" lines={peerLines('fao.food_imports_to_exports')} from="2005" />
        <SeriesChart title="كفاية الإمداد بالطاقة الغذائية" lines={peerLines('fao.energy_supply_adequacy')} from="2005" />
        <SeriesChart title="تقزّم الأطفال دون الخامسة" lines={peerLines('fao.child_stunting')} from="2000" />
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
    </>
  );
}
