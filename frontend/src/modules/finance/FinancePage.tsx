import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines, peerLines } from '@/core/groups';

export default function FinancePage() {
  return (
    <>
      <PageHead title="القطاع المالي والاستثمار" lead="النقد والائتمان وأسعار الفائدة والأسواق والادخار والاستثمار والتحويلات واللوجستيات." />
      <Section title="السياسة النقدية في عُمان (البنك المركزي عبر NCSI)" lead="الأرقام الشهرية الرسمية حتى آخر شهر منشور: أسعار الفائدة، وعرض النقد والائتمان للقطاع الخاص، والاحتياطيات الرسمية وسعر الصرف الفعلي.">
        <SeriesChart
          title="أسعار الفائدة"
          subtitle="%"
          lines={[
            { indicator: 'ncsi.MON_RATE_LENDING_RO', entity: 'OMN', label: 'الإقراض بالريال', color: 'var(--series-2)' },
            { indicator: 'ncsi.MON_RATE_DEPOSIT_TIME_RO', entity: 'OMN', label: 'الودائع الزمنية', color: 'var(--series-3)' },
            { indicator: 'ncsi.MON_RATE_REPO', entity: 'OMN', label: 'الريبو', color: 'var(--series-1)' },
            { indicator: 'ncsi.MON_RATE_OVERNIGHT', entity: 'OMN', label: 'بين البنوك لليلة', color: 'var(--series-7)' },
          ]}
          from="2015"
        />
        <SeriesChart
          title="عرض النقد والائتمان للقطاع الخاص"
          subtitle="مليون ريال عُماني"
          lines={[
            { indicator: 'ncsi.MON_M2', entity: 'OMN', label: 'عرض النقد الواسع M2', color: 'var(--series-1)' },
            { indicator: 'ncsi.MON_CREDIT_PRIVATE', entity: 'OMN', label: 'الائتمان للقطاع الخاص', color: 'var(--series-2)' },
          ]}
          from="2010"
        />
        <SeriesChart
          title="الاحتياطيات الرسمية"
          subtitle="مليون دولار"
          lines={[
            { indicator: 'ncsi.MON_RESERVES_EX_GOLD', entity: 'OMN', label: 'الاحتياطيات دون الذهب', color: 'var(--series-1)' },
            { indicator: 'ncsi.MON_GOLD_VALUE', entity: 'OMN', label: 'الذهب', color: 'var(--series-4)' },
          ]}
          from="2010"
        />
        <SeriesChart title="سعر الصرف الفعلي الاسمي" subtitle="1999 = 100" lines={[{ indicator: 'ncsi.MON_NEER', entity: 'OMN', label: 'سعر الصرف الفعلي' }]} from="2005" />
        <SeriesChart title="صافي الأصول الأجنبية" subtitle="مليون ريال عُماني" lines={[{ indicator: 'ncsi.MON_NET_FOREIGN_ASSETS', entity: 'OMN', label: 'صافي الأصول الأجنبية' }]} />
      </Section>
      <Section title="النقد والائتمان">
        <SeriesChart title="عرض النقد (M2) — شهري" lines={gccLines('gccstat.M2')} />
        <SeriesChart title="الائتمان المحلي — شهري" lines={gccLines('gccstat.DOMESTIC_CREDIT')} />
        <SeriesChart title="النقد الواسع من الناتج" lines={peerLines('wb.FM.LBL.BMNY.GD.ZS')} from="2000" />
        <SeriesChart title="سعر الإقراض" lines={peerLines('wb.FR.INR.LEND')} from="2000" />
      </Section>
      <Section title="سلامة القطاع المصرفي" lead="مؤشرات تقيس قدرة البنوك على امتصاص الصدمات: الملاءة والجودة والتركّز والربحية (قاعدة التطور المالي العالمية للبنك الدولي).">
        <SeriesChart title="القروض المتعثرة إلى إجمالي القروض (الأقل أفضل)" lines={gccLines('wb.GFDD.SI.02')} from="2005" />
        <SeriesChart title="رأس المال التنظيمي إلى الأصول المرجّحة بالمخاطر" lines={gccLines('wb.GFDD.SI.05')} from="2005" />
        <SeriesChart title="رأس مال البنوك إلى الأصول" lines={gccLines('wb.GFDD.SI.03')} from="2005" />
        <SeriesChart title="مؤشر Z للبنوك (الأعلى أفضل)" lines={gccLines('wb.GFDD.SI.01')} from="2005" />
        <SeriesChart title="تركّز أصول أكبر 5 بنوك" lines={gccLines('wb.GFDD.OI.06')} from="2005" />
        <SeriesChart title="هامش صافي الفائدة" lines={gccLines('wb.GFDD.EI.01')} from="2005" />
      </Section>
      <Section title="مركز الاستثمار الدولي" lead="ما تملكه الدولة في الخارج مقابل ما عليها للخارج (أصول الدولة السيادية والبنك المركزي والقطاع الخاص). الصافي الموجب = دائن للعالم. مليارات الدولارات، فالمقارنة بين الدول بالحجم المطلق.">
        <SeriesChart title="صافي مركز الاستثمار الدولي" subtitle="مليار دولار" lines={gccLines('imf.iip_net')} from="2005" />
        <SeriesChart title="الأصول الأجنبية" subtitle="مليار دولار" lines={gccLines('imf.iip_assets')} from="2005" />
        <SeriesChart title="الالتزامات تجاه الخارج" subtitle="مليار دولار" lines={gccLines('imf.iip_liabilities')} from="2005" />
      </Section>
      <Section title="الأسواق والشمول المالي">
        <SeriesChart title="القيمة السوقية للشركات المدرجة" lines={peerLines('wb.CM.MKT.LCAP.GD.ZS')} from="2000" />
        <SeriesChart title="امتلاك حساب مصرفي أو محفظة رقمية" lines={peerLines('wb.FX.OWN.TOTL.ZS')} from="2011" />
        <SeriesChart title="الشركات المحلية المدرجة" lines={peerLines('wb.CM.MKT.LDOM.NO')} from="2000" />
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
