import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines, peerLines } from '@/core/groups';

export default function FinancePage() {
  return (
    <>
      <PageHead title="القطاع المالي والاستثمار" lead="النقد والائتمان وأسعار الفائدة والأسواق والادخار والاستثمار والتحويلات واللوجستيات." />
      <Section title="النقد والائتمان">
        <SeriesChart title="عرض النقد (M2) — شهري" lines={gccLines('gccstat.M2')} />
        <SeriesChart title="الائتمان المحلي — شهري" lines={gccLines('gccstat.DOMESTIC_CREDIT')} />
        <SeriesChart title="النقد الواسع من الناتج" lines={peerLines('wb.FM.LBL.BMNY.GD.ZS')} from="2000" />
        <SeriesChart title="سعر الإقراض" lines={peerLines('wb.FR.INR.LEND')} from="2000" />
      </Section>
      <Section title="الأسواق والشمول المالي">
        <SeriesChart title="القيمة السوقية للشركات المدرجة" lines={peerLines('wb.CM.MKT.LCAP.GD.ZS')} from="2000" />
        <SeriesChart title="امتلاك حساب مصرفي أو محفظة رقمية" lines={peerLines('wb.FX.OWN.TOTL.ZS')} from="2011" />
      </Section>
      <Section title="الادخار والاستثمار والتحويلات">
        <SeriesChart title="الادخار الإجمالي" lines={peerLines('wb.NY.GNS.ICTR.ZS')} from="1995" />
        <SeriesChart title="تكوين رأس المال الإجمالي" lines={peerLines('wb.NE.GDI.TOTL.ZS')} from="1995" />
        <SeriesChart title="الاستثمار الأجنبي المباشر الداخل" lines={peerLines('wb.BX.KLT.DINV.WD.GD.ZS')} from="1995" />
        <SeriesChart title="تحويلات العاملين الواردة" lines={peerLines('wb.BX.TRF.PWKR.CD.DT')} from="2000" />
      </Section>
      <Section title="اللوجستيات">
        <SeriesChart title="مؤشر الأداء اللوجستي" lines={peerLines('wb.LP.LPI.OVRL.XQ')} />
        <SeriesChart title="مؤشر الاتصال الملاحي" lines={peerLines('wb.IS.SHP.GCNW.XQ')} />
      </Section>
    </>
  );
}
