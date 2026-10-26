import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SwitchChart } from '@/core/components/SwitchChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { peerLines } from '@/core/groups';

export default function GovernancePage() {
  return (
    <>
      <PageHead title="الحوكمة والجاهزية الرقمية" lead="مؤشرات الحوكمة العالمية (البنك الدولي) ومؤشر الجاهزية للذكاء الاصطناعي (صندوق النقد): عُمان مقابل دول المقارنة والعالم." />
      <Section title="الحوكمة (درجة 0–100)">
        <SeriesChart title="فاعلية الحكومة" lines={peerLines('wb.GOV_WGI_GE.SC')} from="2005" />
        <SeriesChart title="جودة التنظيم" lines={peerLines('wb.GOV_WGI_RQ.SC')} from="2005" />
        <SeriesChart title="سيادة القانون" lines={peerLines('wb.GOV_WGI_RL.SC')} from="2005" />
        <SeriesChart title="السيطرة على الفساد" lines={peerLines('wb.GOV_WGI_CC.SC')} from="2005" />
        <SeriesChart title="الاستقرار السياسي" lines={peerLines('wb.GOV_WGI_PV.SC')} from="2005" />
        <SeriesChart title="الصوت والمساءلة" lines={peerLines('wb.GOV_WGI_VA.SC')} from="2005" />
      </Section>
      <Section title="النزاهة والرفاه (مؤشرات دولية مركّبة)" lead="مدركات الفساد من منظمة الشفافية الدولية، والسعادة من تقرير السعادة العالمي (عبر Our World in Data).">
        <SeriesChart title="مؤشر مدركات الفساد (الأعلى = أنظف)" lines={peerLines('owid.cpi_score')} from="2012" />
        <SeriesChart title="مؤشر السعادة (سلّم كانتريل)" lines={peerLines('owid.happiness')} from="2011" />
      </Section>
      <Section title="الجاهزية للذكاء الاصطناعي" lead="مؤشر مركّب (0–1) لعام 2023.">
        <WorldRankCard title="مؤشر الجاهزية للذكاء الاصطناعي" indicator="imf.AI_PI" />
        <WorldRankCard title="البنية التحتية الرقمية" indicator="imf.DI" />
        <WorldRankCard title="التنظيم والأخلاقيات" indicator="imf.RE" />
        <WorldRankCard title="رأس المال البشري وسياسات العمل" indicator="imf.HCLMP" />
      </Section>
      <Section title="مخاطر الكوارث والأزمات (INFORM)" lead="مؤشر INFORM للمخاطر (0 = الأقل خطرًا، 10 = الأعلى): احتمال أزمة تتجاوز قدرة الدولة على المواجهة، مركّب من التعرض للمخاطر وهشاشة السكان ونقص القدرة على المواجهة. عُمان مقابل دول المقارنة.">
        <SwitchChart wide height={340} title="مؤشر INFORM للمخاطر" variants={[
          { value: 'risk', label: 'المؤشر الكلي', subtitle: 'درجة 0–10', lines: peerLines('imf.inform_risk') },
          { value: 'hazard', label: 'التعرض للمخاطر', subtitle: 'درجة 0–10', lines: peerLines('imf.inform_hazard') },
          { value: 'vuln', label: 'الهشاشة', subtitle: 'درجة 0–10', lines: peerLines('imf.inform_vulnerability') },
          { value: 'coping', label: 'نقص القدرة على المواجهة', subtitle: 'درجة 0–10', lines: peerLines('imf.inform_coping') },
        ]} />
        <WorldRankCard title="ترتيب الدول بمؤشر المخاطر" indicator="imf.inform_risk" />
      </Section>
      <Section title="عُمان في العالم (الحوكمة)">
        <WorldRankCard title="فاعلية الحكومة" indicator="wb.GOV_WGI_GE.SC" />
        <WorldRankCard title="السيطرة على الفساد" indicator="wb.GOV_WGI_CC.SC" />
      </Section>
    </>
  );
}
