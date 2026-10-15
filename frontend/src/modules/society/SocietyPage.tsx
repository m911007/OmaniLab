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
