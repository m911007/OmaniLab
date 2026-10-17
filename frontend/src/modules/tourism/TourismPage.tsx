import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { gccLines, peerLines } from '@/core/groups';

export default function TourismPage() {
  return (
    <>
      <PageHead title="السياحة" lead="الوافدون السياحيون والإيرادات والمنشآت الفندقية، عُمان مقابل دول الخليج. تتوقف بعض السلاسل عند آخر سنة نشرها المصدر." />
      <Section title="الفنادق في عُمان (شهريًا)" lead="نزلاء الفنادق ونسبة الإشغال والإيرادات والتوظيف حتى آخر شهر منشور، من وزارة التراث والسياحة عبر المركز الوطني للإحصاء.">
        <SeriesChart title="نزلاء الفنادق" lines={[{ indicator: 'ncsi.TOUR_HOTEL_GUESTS', entity: 'OMN', label: 'النزلاء' }]} from="2015" />
        <SeriesChart title="نسبة إشغال الغرف" subtitle="%" lines={[{ indicator: 'ncsi.TOUR_ROOM_OCCUPANCY', entity: 'OMN', label: 'الإشغال' }]} from="2015" />
        <SeriesChart title="إيرادات الفنادق" lines={[{ indicator: 'ncsi.TOUR_HOTEL_REVENUE', entity: 'OMN', label: 'الإيرادات' }]} from="2015" />
        <SeriesChart title="العاملون في الفنادق" lines={[{ indicator: 'ncsi.TOUR_HOTEL_EMPLOYEES', entity: 'OMN', label: 'العاملون' }]} from="2015" />
        <SeriesChart title="حصة السياحة من الناتج المحلي" subtitle="%" lines={[{ indicator: 'ncsi.TOUR_GDP_SHARE', entity: 'OMN', label: 'حصة السياحة' }]} />
        <SeriesChart title="عدد الفنادق" lines={[{ indicator: 'ncsi.TOUR_HOTELS', entity: 'OMN', label: 'الفنادق' }]} />
      </Section>
      <Section title="الوافدون والإيرادات">
        <SeriesChart title="الوافدون السياحيون الدوليون" lines={peerLines('wb.ST.INT.ARVL')} from="2000" />
        <SeriesChart title="إيرادات السياحة الدولية" lines={peerLines('wb.ST.INT.RCPT.CD')} from="2000" />
        <SeriesChart title="المغادرون سياحيًا" lines={peerLines('wb.ST.INT.DPRT')} from="2000" />
        <SeriesChart title="صادرات خدمات السفر والسياحة" lines={peerLines('wb.BX.GSR.TRVL.ZS')} from="2000" />
      </Section>
      <Section title="من المركز الإحصائي الخليجي">
        <SeriesChart title="السياح القادمون" lines={gccLines('gccstat.TOURISTS')} />
        <SeriesChart title="المنشآت الفندقية" lines={gccLines('gccstat.HOTELS')} />
      </Section>
      <Section title="عُمان في العالم">
        <WorldRankCard title="الوافدون السياحيون" indicator="wb.ST.INT.ARVL" />
        <WorldRankCard title="إيرادات السياحة الدولية" indicator="wb.ST.INT.RCPT.CD" />
      </Section>
    </>
  );
}
