import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { peers } from '@/core/lines';

export default function PeersPage() {
  return (
    <>
      <PageHead title="دول المقارنة" lead="عُمان مقابل دول الخليج والنرويج ونيوزيلندا: اقتصادات صغيرة ومفتوحة أو غنية بالموارد، تُقاس بها مؤشرات التنمية والمالية العامة تاريخيًا." />

      <Section title="الدخل والتنمية">
        <SeriesChart title="نصيب الفرد من الناتج المحلي" lines={peers('wb.NY.GDP.PCAP.CD')} from="1990" />
        <SeriesChart title="مؤشر رأس المال البشري" lines={peers('wb.HD.HCI.OVRL')} />
        <SeriesChart title="متوسط العمر المتوقع" lines={peers('wb.SP.DYN.LE00.IN')} from="1990" />
        <SeriesChart title="الإنفاق على البحث والتطوير" lines={peers('wb.GB.XPD.RSDV.GD.ZS')} from="2000" />
      </Section>

      <Section title="المالية العامة">
        <SeriesChart title="إجمالي الدين الحكومي" lines={peers('imf.GGXWDG_NGDP')} from="2000" imfForecast />
        <SeriesChart title="رصيد الميزانية" lines={peers('imf.GGXCNL_NGDP')} from="2000" imfForecast />
        <SeriesChart title="التضخم" lines={peers('imf.PCPIPCH')} from="2000" imfForecast />
        <SeriesChart title="نمو الناتج الحقيقي" lines={peers('imf.NGDP_RPCH')} from="2000" imfForecast />
      </Section>

      <Section title="التعليم والعمل والرقمنة">
        <SeriesChart title="الإنفاق على التعليم" lines={peers('wb.SE.XPD.TOTL.GD.ZS')} from="1995" />
        <SeriesChart title="الالتحاق بالتعليم العالي" lines={peers('wb.SE.TER.ENRR')} from="1995" />
        <SeriesChart title="مستخدمو الإنترنت" lines={peers('wb.IT.NET.USER.ZS')} from="2000" />
        <SeriesChart title="معدل البطالة" lines={peers('wb.SL.UEM.TOTL.ZS')} from="1995" />
      </Section>

      <Section title="الانفتاح والاستثمار">
        <SeriesChart title="صادرات السلع والخدمات" lines={peers('wb.NE.EXP.GNFS.ZS')} from="1990" />
        <SeriesChart title="الاستثمار الأجنبي المباشر الداخل" lines={peers('wb.BX.KLT.DINV.WD.GD.ZS')} from="1995" />
      </Section>
    </>
  );
}
