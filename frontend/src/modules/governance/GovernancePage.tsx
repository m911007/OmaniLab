import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
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
      <Section title="الجاهزية للذكاء الاصطناعي" lead="مؤشر مركّب (0–1) لعام 2023.">
        <WorldRankCard title="مؤشر الجاهزية للذكاء الاصطناعي" indicator="imf.AI_PI" />
        <WorldRankCard title="البنية التحتية الرقمية" indicator="imf.DI" />
        <WorldRankCard title="التنظيم والأخلاقيات" indicator="imf.RE" />
        <WorldRankCard title="رأس المال البشري وسياسات العمل" indicator="imf.HCLMP" />
      </Section>
      <Section title="عُمان في العالم (الحوكمة)">
        <WorldRankCard title="فاعلية الحكومة" indicator="wb.GOV_WGI_GE.SC" />
        <WorldRankCard title="السيطرة على الفساد" indicator="wb.GOV_WGI_CC.SC" />
      </Section>
    </>
  );
}
