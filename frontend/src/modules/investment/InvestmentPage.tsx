import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines, peerLines } from '@/core/groups';

export default function InvestmentPage() {
  return (
    <>
      <PageHead
        title="الاستثمار"
        lead="هل يتدفق المال إلى الاقتصاد العُماني؟ الاستثمار الأجنبي المباشر، وتكوين رأس المال، وسوق الأسهم، والتمويل، ومناخ الأعمال الذي يحدد جاذبية البلد، والأصول التي تملكها عُمان في الخارج."
      />

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="wb.BX.KLT.DINV.WD.GD.ZS" label="الاستثمار الأجنبي الداخل (% من الناتج)" />
        <KeyStat entity="OMN" indicator="wb.NE.GDI.FTOT.ZS" label="تكوين رأس المال الثابت (% من الناتج)" />
        <KeyStat entity="OMN" indicator="wb.CM.MKT.LCAP.GD.ZS" label="القيمة السوقية للأسهم (% من الناتج)" />
        <KeyStat entity="OMN" indicator="imf.iip_net" label="صافي مركز الاستثمار الدولي" compactValue />
      </div>

      <Section title="الاستثمار الأجنبي المباشر" lead="الداخل يعكس ثقة المستثمرين الأجانب، والصادر يعكس توظيف الفوائض في الخارج. الأرقام السالبة في صافي التدفق تعني خروجًا يفوق الدخول.">
        <SeriesChart title="الاستثمار الأجنبي المباشر الداخل (% من الناتج)" lines={gccLines('wb.BX.KLT.DINV.WD.GD.ZS')} from="2000" />
        <SeriesChart title="الاستثمار الأجنبي المباشر الداخل (بالدولار)" lines={gccLines('wb.BX.KLT.DINV.CD.WD')} from="2000" />
        <SeriesChart title="الاستثمار الأجنبي المباشر الصادر (% من الناتج)" lines={gccLines('wb.BM.KLT.DINV.WD.GD.ZS')} from="2000" />
        <SeriesChart title="صافي الاستثمار الأجنبي المباشر (ميزان المدفوعات)" lines={gccLines('wb.BN.KLT.DINV.CD')} from="2000" />
      </Section>

      <Section title="تكوين رأس المال والادخار" lead="الاستثمار المحلي يحتاج ادخارًا؛ والفرق بينهما يُموَّل من الخارج أو يتراكم فائضًا.">
        <SeriesChart title="تكوين رأس المال الثابت (% من الناتج)" lines={peerLines('wb.NE.GDI.FTOT.ZS')} from="1990" />
        <SeriesChart title="الادخار المحلي الإجمالي (% من الناتج)" lines={peerLines('wb.NY.GDS.TOTL.ZS')} from="1990" />
        <SeriesChart title="الاستثمار والادخار في عُمان" subtitle="% من الناتج" lines={[{ indicator: 'wb.NE.GDI.FTOT.ZS', entity: 'OMN', label: 'الاستثمار' }, { indicator: 'wb.NY.GDS.TOTL.ZS', entity: 'OMN', label: 'الادخار' }]} from="1990" />
        <SeriesChart title="الاستثمار الخاص (% من الناتج)" lines={gccLines('wb.NE.GDI.FPRV.ZS')} from="1990" />
      </Section>

      <Section title="سوق الأسهم" lead="عمق السوق المالية يحدد قدرة الشركات على التمويل بالأسهم وسهولة خروج المستثمر.">
        <SeriesChart title="القيمة السوقية للشركات المدرجة (% من الناتج)" lines={gccLines('wb.CM.MKT.LCAP.GD.ZS')} from="2000" />
        <SeriesChart title="القيمة السوقية للشركات المدرجة (بالدولار)" lines={gccLines('wb.CM.MKT.LCAP.CD')} from="2000" />
        <SeriesChart title="الشركات المحلية المدرجة" lines={gccLines('wb.CM.MKT.LDOM.NO')} from="2000" />
        <SeriesChart title="معدل دوران الأسهم" subtitle="% — مقياس السيولة" lines={gccLines('wb.CM.MKT.TRNR')} from="2000" />
        <SeriesChart title="تدفقات المحافظ في الأسهم (صافي)" lines={gccLines('wb.BX.PEF.TOTL.CD.WD')} from="2005" />
      </Section>

      <Section title="التمويل وكلفته">
        <SeriesChart title="الائتمان للقطاع الخاص (% من الناتج)" lines={gccLines('wb.FS.AST.PRVT.GD.ZS')} from="1990" />
        <SeriesChart title="سعر الفائدة الحقيقي" subtitle="% — بعد استبعاد التضخم" lines={gccLines('wb.FR.INR.RINR')} from="2000" />
        <SeriesChart title="المطالبات على القطاع الخاص (شهري)" lines={[{ indicator: 'ncsi.MON_CREDIT_PRIVATE', entity: 'OMN', label: 'الائتمان' }]} />
        <SeriesChart title="هامش صافي الفائدة للبنوك" lines={gccLines('wb.GFDD.EI.01')} from="2000" />
      </Section>

      <Section title="مناخ الأعمال" lead="مؤشرات الحوكمة (البنك الدولي) والابتكار والبنية الأساسية: ما يراه المستثمر قبل أن يقرر.">
        <SeriesChart title="جودة التنظيم" subtitle="مؤشر الحوكمة العالمي (0–100)" lines={gccLines('wb.GOV_WGI_RQ.SC')} from="2000" />
        <SeriesChart title="سيادة القانون" lines={gccLines('wb.GOV_WGI_RL.SC')} from="2000" />
        <SeriesChart title="السيطرة على الفساد" lines={gccLines('wb.GOV_WGI_CC.SC')} from="2000" />
        <SeriesChart title="فعالية الحكومة" lines={gccLines('wb.GOV_WGI_GE.SC')} from="2000" />
        <SeriesChart title="كثافة الأعمال الجديدة" subtitle="لكل 1000 نسمة" lines={gccLines('wb.IC.BUS.NDNS.ZS')} from="2005" />
        <SeriesChart title="مؤشر الأداء اللوجستي" lines={gccLines('wb.LP.LPI.OVRL.XQ')} />
      </Section>

      <Section title="استثمارات عُمان في الخارج" lead="الأصول التي تملكها عُمان خارج حدودها مقابل ما يملكه الأجانب فيها.">
        <SeriesChart title="الأصول الأجنبية" subtitle="مليار دولار" lines={gccLines('imf.iip_assets')} from="2005" />
        <SeriesChart title="الالتزامات تجاه الخارج" subtitle="مليار دولار" lines={gccLines('imf.iip_liabilities')} from="2005" />
        <SeriesChart title="صافي مركز الاستثمار الدولي" subtitle="مليار دولار" lines={gccLines('imf.iip_net')} from="2005" />
        <SeriesChart title="احتياطيات النقد الأجنبي (شهري)" lines={[{ indicator: 'ncsi.MON_RESERVES_FX', entity: 'OMN', label: 'احتياطيات النقد الأجنبي' }]} />
      </Section>
    </>
  );
}
