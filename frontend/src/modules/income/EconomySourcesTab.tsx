import { SeriesChart } from '@/core/components/SeriesChart';
import { Section } from '@/core/components/PageHead';
import { gccLines, peerLines } from '@/core/groups';

/** مصادر دخل الاقتصاد (غير المالية): هيكل الناتج والصادرات والموارد والسياحة والاستثمار الأجنبي والتحويلات، مقارنةً بالخليج. */
export default function EconomySourcesTab() {
  return (
    <>
      <Section title="مصادر دخل الاقتصاد" lead="كيف يتكوّن الناتج، وما مصادر النقد الأجنبي: القطاعات والصادرات والموارد والسياحة والاستثمار الأجنبي، مقارنةً بدول الخليج.">
        <SeriesChart title="القيمة المضافة للصناعة (% من الناتج)" lines={gccLines('wb.NV.IND.TOTL.ZS')} from="1995" />
        <SeriesChart title="القيمة المضافة للخدمات (% من الناتج)" lines={gccLines('wb.NV.SRV.TOTL.ZS')} from="1995" />
        <SeriesChart title="القيمة المضافة للزراعة (% من الناتج)" lines={gccLines('wb.NV.AGR.TOTL.ZS')} from="1995" />
        <SeriesChart title="القيمة المضافة للتصنيع (% من الناتج)" lines={gccLines('wb.NV.IND.MANF.ZS')} from="1995" />
        <SeriesChart oil title="ريوع الموارد الطبيعية (% من الناتج)" lines={peerLines('wb.NY.GDP.TOTL.RT.ZS')} from="1990" />
        <SeriesChart oil title="صادرات الوقود (% من صادرات السلع)" lines={peerLines('wb.TX.VAL.FUEL.ZS.UN')} from="1995" />
        <SeriesChart title="صادرات السلع الصناعية (% من صادرات السلع)" lines={peerLines('wb.TX.VAL.MANF.ZS.UN')} from="1995" />
        <SeriesChart title="صادرات الغذاء والمعادن (عُمان)" lines={[{ indicator: 'wb.TX.VAL.FOOD.ZS.UN', entity: 'OMN', label: 'غذاء' }, { indicator: 'wb.TX.VAL.MMTL.ZS.UN', entity: 'OMN', label: 'خامات ومعادن' }]} from="1995" />
        <SeriesChart title="إيرادات السياحة الدولية (% من الصادرات)" lines={gccLines('wb.ST.INT.RCPT.XP.ZS')} from="2000" />
        <SeriesChart title="الاستثمار الأجنبي المباشر الداخل (% من الناتج)" lines={gccLines('wb.BX.KLT.DINV.WD.GD.ZS')} from="2000" />
        <SeriesChart title="تحويلات العاملين الواردة (دولار)" lines={gccLines('wb.BX.TRF.PWKR.CD.DT')} from="2000" />
        <SeriesChart title="الاستهلاك الأسري (% من الناتج)" lines={gccLines('wb.NE.CON.PRVT.ZS')} from="1995" />
      </Section>
    </>
  );
}
