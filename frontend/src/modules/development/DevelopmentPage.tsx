import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { peerLines } from '@/core/groups';

export default function DevelopmentPage() {
  return (
    <>
      <PageHead title="التنمية البشرية" lead="مؤشر التنمية البشرية ومكوّناته وعدم المساواة بين الجنسين والاستدامة، من برنامج الأمم المتحدة الإنمائي (UNDP): عُمان مقابل دول الخليج والنرويج ونيوزيلندا والعالم." />
      <Section title="مؤشر التنمية البشرية ومكوّناته">
        <SeriesChart title="مؤشر التنمية البشرية" lines={peerLines('undp.hdi')} from="1990" />
        <SeriesChart title="العمر المتوقع عند الولادة" lines={peerLines('undp.le')} from="1990" />
        <SeriesChart title="سنوات الدراسة المتوقعة" lines={peerLines('undp.eys')} from="1990" />
        <SeriesChart title="نصيب الفرد من الدخل القومي (تعادل القوة الشرائية)" lines={peerLines('undp.gnipc')} from="1990" />
      </Section>
      <Section title="عدم المساواة">
        <SeriesChart title="مؤشر التنمية البشرية المعدَّل بعدم المساواة" lines={peerLines('undp.ihdi')} from="2010" />
        <SeriesChart title="مؤشر عدم المساواة بين الجنسين (الأقل أفضل)" lines={peerLines('undp.gii')} from="1995" />
        <SeriesChart title="مؤشر التنمية حسب الجنس" lines={peerLines('undp.gdi')} from="1990" />
        <SeriesChart title="معدل الولادة بين المراهقات" lines={peerLines('undp.abr')} from="1990" />
      </Section>
      <Section title="الاستدامة والصحة">
        <SeriesChart title="مؤشر التنمية المعدَّل بالضغوط الكوكبية" lines={peerLines('undp.phdi')} from="1990" />
        <SeriesChart title="انبعاثات CO₂ للفرد" lines={peerLines('undp.co2_prod')} from="1990" />
        <SeriesChart title="معدل وفيات الأمهات" lines={peerLines('undp.mmr')} from="2000" />
      </Section>
      <Section title="عُمان في العالم">
        <WorldRankCard title="مؤشر التنمية البشرية" indicator="undp.hdi" />
        <WorldRankCard title="العمر المتوقع" indicator="undp.le" />
        <WorldRankCard title="نصيب الفرد من الدخل القومي" indicator="undp.gnipc" />
        <WorldRankCard title="سنوات الدراسة المتوقعة" indicator="undp.eys" />
      </Section>
    </>
  );
}
