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
      <Section title="ثروة الأمة: ما وراء النفط" lead="حسابات الثروة للبنك الدولي تقيس رصيد الدولة من رأس المال بأنواعه للفرد: المنتَج (مصانع وبنية تحتية)، والبشري، والطبيعي المتجدد وغير المتجدد (نفط وغاز ومعادن)، والأصول الأجنبية. تنويع الثروة هو جوهر التنويع الاقتصادي.">
        <SeriesChart
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
        <SeriesChart title="الثروة الشاملة للفرد" subtitle="دولار ثابت 2019" lines={peerLines('wb.NW.TOW.PC')} from="1995" />
        <SeriesChart title="رأس المال البشري للفرد" subtitle="دولار ثابت 2019" lines={peerLines('wb.NW.HCA.PC')} from="1995" />
        <SeriesChart title="رأس المال الطبيعي غير المتجدد للفرد" subtitle="دولار ثابت 2019" lines={peerLines('wb.NW.NCA.SSOI.PC')} from="1995" />
      </Section>
      <Section title="البحث والتعلم">
        <SeriesChart title="الباحثون لكل مليون نسمة" lines={peerLines('wb.SP.POP.SCIE.RD.P6')} from="2000" />
        <SeriesChart title="المقالات العلمية والتقنية" lines={peerLines('wb.IP.JRN.ARTC.SC')} from="2000" />
        <SeriesChart title="سنوات الدراسة المعدَّلة بالتعلم" lines={peerLines('wb.HD.HCI.LAYS')} from="2010" />
        <SeriesChart title="نتيجة TIMSS في الرياضيات (الصف الثامن)" lines={peerLines('wb.LO.TIMSS.MAT8')} from="1995" />
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
