import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import type { Explanation } from '@/core/explain';
import { gccLines, peerLines } from '@/core/groups';

const MONEY_AND_CREDIT: Explanation = {
  meaning:
    'عرض النقد الواسع (M2) هو إجمالي الأموال المتاحة للإنفاق في الاقتصاد: النقد الذي في أيدي الناس، وأرصدة الحسابات الجارية، والودائع الادخارية والزمنية في البنوك. أما الائتمان للقطاع الخاص فهو ما أقرضته البنوك للشركات والأفراد، من قروض الأعمال إلى القروض السكنية والشخصية.',
  shows:
    'خطّان شهريان بملايين الريالات العُمانية: الأول (M2) يقيس حجم الأموال المتداولة والمودعة، والثاني يقيس ما وصل منها إلى القطاع الخاص على شكل قروض. الرسم يتيح مقارنة نمو الأموال بنمو الإقراض عبر السنوات.',
  read: [
    'ارتفاع الخطّين معًا يعني أن الاقتصاد يتوسع: ودائع أكثر وقروض أكثر لتمويل الاستثمار والاستهلاك.',
    'إن صعد خط الائتمان أسرع من M2 فالبنوك تُقرض نسبة أكبر مما تجمعه من ودائع؛ هذا يدعم النشاط لكنه يرفع المخاطر ويقلّل السيولة الفائضة لدى البنوك.',
    'إن صعد M2 أسرع من الائتمان فالأموال تتراكم دون أن تتحول إلى قروض: سيولة وفيرة أو إقراض حذر أو طلب ضعيف على التمويل.',
    'المسافة بين الخطّين تعني أن جزءًا من الأموال في الودائع لا يذهب قروضًا للقطاع الخاص (يُحتفظ به سيولة أو يُوظَّف في أدوات أخرى).',
    'ركّز على اتجاه السنوات لا على القفزة الشهرية الواحدة، فالأرقام الشهرية تتأثر بمواسم الصرف والتسويات.',
  ],
  why: 'يخبرك هل يموّل النظام المصرفي النمو أم يتراجع: الإقراض النشط يعني استثمارًا وتوظيفًا وعقارًا أكثر، والتراجع الحاد قد ينذر ببطء اقتصادي. والبنك المركزي يراقب النسبة بين الائتمان والودائع لحماية استقرار البنوك.',
  terms: [
    { term: 'عرض النقد الواسع (M2)', definition: 'مقياس لكل الأموال القابلة للإنفاق السريع أو القريب: النقد خارج البنوك + الحسابات الجارية + الودائع الادخارية والزمنية.' },
    { term: 'الائتمان', definition: 'القروض والتسهيلات التي تمنحها البنوك. ائتمان القطاع الخاص يشمل الشركات والأفراد دون الحكومة.' },
    { term: 'الودائع', definition: 'أموال العملاء المحفوظة لدى البنوك، وهي المصدر الرئيس لما تُقرضه.' },
    { term: 'السيولة', definition: 'مقدار الأموال القابلة للاستخدام فورًا؛ سيولة فائضة تعني أموالًا لم تُوظَّف بعد.' },
    { term: 'القطاع الخاص', definition: 'الشركات والأفراد، بمقابل القطاع الحكومي.' },
  ],
  caution: 'ارتفاع الائتمان ليس خيرًا دائمًا: إقراض متسارع جدًا قد يضخّم الأسعار (العقار مثلًا) ويصعّب السداد لاحقًا. تُراجَع الأرقام الشهرية أحيانًا بعد نشرها.',
};

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
          explain={MONEY_AND_CREDIT}
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
      <Section title="الشمول المالي" lead="نسبة البالغين المتعاملين مع النظام المصرفي، وفجوة الجنسين فيه (مسح Global Findex للبنك الدولي: 2011 و2014 و2017 و2021 و2024).">
        <SeriesChart title="من لديهم حساب مصرفي (15+)" lines={gccLines('wb.account.t.d')} kind="columns" from="2011" />
        <SeriesChart title="حساب مصرفي: النساء والرجال في عُمان" subtitle="% من الفئة" lines={[{ indicator: 'wb.account.t.d.1', entity: 'OMN', label: 'النساء' }, { indicator: 'wb.account.t.d.2', entity: 'OMN', label: 'الرجال' }]} from="2011" />
        <SeriesChart title="من اقترضوا خلال السنة" lines={gccLines('wb.borrow.any.t.d')} from="2011" />
        <SeriesChart title="من يملكون بطاقة خصم" lines={gccLines('wb.fin2.t.d')} from="2011" />
      </Section>
    </>
  );
}
