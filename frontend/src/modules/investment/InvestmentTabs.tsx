import { Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines, peerLines } from '@/core/groups';
import { ScoreTable, type ScoreRow } from '@/modules/fiscal/GulfScorecard';
import { SavingInvestmentGap } from './InvestmentDerived';

/** الصورة العامة: هل يكفي الادخار لتمويل الاستثمار؟ ثم الاستثمار الأجنبي المباشر في عُمان. */
export function InvestmentOverviewTab() {
  return (
    <>
      <Section id="gap" title="هل يموّل الادخار الاستثمار؟" lead="فائض الادخار على الاستثمار يعني فائضًا يُوظَّف في الخارج؛ وعجزه يعني حاجة إلى تمويل خارجي. مشتق من حسابات البنك الدولي.">
        <SavingInvestmentGap />
      </Section>
      <Section title="تكوين رأس المال والادخار" lead="الاستثمار المحلي يحتاج ادخارًا؛ والفرق بينهما يُموَّل من الخارج أو يتراكم فائضًا.">
        <SeriesChart wide title="تكوين رأس المال الثابت (% من الناتج)" lines={peerLines('wb.NE.GDI.FTOT.ZS')} from="1990" />
        <SeriesChart wide title="الادخار المحلي الإجمالي (% من الناتج)" lines={peerLines('wb.NY.GDS.TOTL.ZS')} from="1990" />
        <SeriesChart wide title="الاستثمار والادخار في عُمان" subtitle="% من الناتج" lines={[{ indicator: 'wb.NE.GDI.FTOT.ZS', entity: 'OMN', label: 'الاستثمار' }, { indicator: 'wb.NY.GDS.TOTL.ZS', entity: 'OMN', label: 'الادخار' }]} from="1990" />
        <SeriesChart wide title="الاستثمار الخاص (% من الناتج)" lines={gccLines('wb.NE.GDI.FPRV.ZS')} from="1990" />
      </Section>
    </>
  );
}

/** الاستثمار الأجنبي: الداخل والصادر والصافي بالنسبة للناتج وبالدولار. */
export function InvestmentFdiTab() {
  return (
    <>
      <Section title="الاستثمار الأجنبي المباشر" lead="الداخل يعكس ثقة المستثمرين الأجانب، والصادر يعكس توظيف الفوائض في الخارج. الأرقام السالبة في صافي التدفق تعني خروجًا يفوق الدخول.">
        <SeriesChart wide title="الاستثمار الأجنبي المباشر الداخل (% من الناتج)" lines={gccLines('wb.BX.KLT.DINV.WD.GD.ZS')} from="2000" />
        <SeriesChart wide title="الاستثمار الأجنبي المباشر الداخل (بالدولار)" lines={gccLines('wb.BX.KLT.DINV.CD.WD')} from="2000" />
        <SeriesChart wide title="الاستثمار الأجنبي المباشر الصادر (% من الناتج)" lines={gccLines('wb.BM.KLT.DINV.WD.GD.ZS')} from="2000" />
        <SeriesChart wide title="صافي الاستثمار الأجنبي المباشر (ميزان المدفوعات)" lines={gccLines('wb.BN.KLT.DINV.CD')} from="2000" />
      </Section>
    </>
  );
}

/** الأسواق والتمويل: سوق الأسهم وكلفة التمويل والائتمان. */
export function InvestmentMarketsTab() {
  return (
    <>
      <Section title="سوق الأسهم" lead="عمق السوق المالية يحدد قدرة الشركات على التمويل بالأسهم وسهولة خروج المستثمر.">
        <SeriesChart wide title="القيمة السوقية للشركات المدرجة (% من الناتج)" lines={gccLines('wb.CM.MKT.LCAP.GD.ZS')} from="2000" />
        <SeriesChart wide title="القيمة السوقية للشركات المدرجة (بالدولار)" lines={gccLines('wb.CM.MKT.LCAP.CD')} from="2000" />
        <SeriesChart wide title="الشركات المحلية المدرجة" lines={gccLines('wb.CM.MKT.LDOM.NO')} from="2000" />
        <SeriesChart wide title="معدل دوران الأسهم" subtitle="% — مقياس السيولة" lines={gccLines('wb.CM.MKT.TRNR')} from="2000" />
        <SeriesChart wide title="تدفقات المحافظ في الأسهم (صافي)" lines={gccLines('wb.BX.PEF.TOTL.CD.WD')} from="2005" />
      </Section>
      <Section title="التمويل وكلفته">
        <SeriesChart wide title="الائتمان للقطاع الخاص (% من الناتج)" lines={gccLines('wb.FS.AST.PRVT.GD.ZS')} from="1990" />
        <SeriesChart wide title="سعر الفائدة الحقيقي" subtitle="% — بعد استبعاد التضخم" lines={gccLines('wb.FR.INR.RINR')} from="2000" />
        <SeriesChart wide title="المطالبات على القطاع الخاص (شهري)" lines={[{ indicator: 'ncsi.MON_CREDIT_PRIVATE', entity: 'OMN', label: 'الائتمان' }]} />
        <SeriesChart wide title="هامش صافي الفائدة للبنوك" lines={gccLines('wb.GFDD.EI.01')} from="2000" />
      </Section>
    </>
  );
}

/** مناخ الأعمال: الحوكمة وسيادة القانون والفساد وكثافة الأعمال الجديدة واللوجستيات. */
export function InvestmentClimateTab() {
  return (
    <>
      <Section title="مناخ الأعمال" lead="مؤشرات الحوكمة (البنك الدولي) والابتكار والبنية الأساسية: ما يراه المستثمر قبل أن يقرر.">
        <SeriesChart wide title="جودة التنظيم" subtitle="مؤشر الحوكمة العالمي (0–100)" lines={gccLines('wb.GOV_WGI_RQ.SC')} from="2000" />
        <SeriesChart wide title="سيادة القانون" lines={gccLines('wb.GOV_WGI_RL.SC')} from="2000" />
        <SeriesChart wide title="السيطرة على الفساد" lines={gccLines('wb.GOV_WGI_CC.SC')} from="2000" />
        <SeriesChart wide title="فعالية الحكومة" lines={gccLines('wb.GOV_WGI_GE.SC')} from="2000" />
        <SeriesChart wide title="كثافة الأعمال الجديدة" subtitle="لكل 1000 نسمة" lines={gccLines('wb.IC.BUS.NDNS.ZS')} from="2005" />
        <SeriesChart wide title="مؤشر الأداء اللوجستي" lines={gccLines('wb.LP.LPI.OVRL.XQ')} />
      </Section>
    </>
  );
}

/** الاستثمارات الدولية: أصول عُمان في الخارج والتزاماتها وصافي مركزها واحتياطياتها. */
export function InvestmentAbroadTab() {
  return (
    <>
      <Section title="استثمارات عُمان في الخارج" lead="الأصول التي تملكها عُمان خارج حدودها مقابل ما يملكه الأجانب فيها.">
        <SeriesChart wide title="الأصول الأجنبية" subtitle="مليار دولار" lines={gccLines('imf.iip_assets')} from="2005" />
        <SeriesChart wide title="الالتزامات تجاه الخارج" subtitle="مليار دولار" lines={gccLines('imf.iip_liabilities')} from="2005" />
        <SeriesChart wide title="صافي مركز الاستثمار الدولي" subtitle="مليار دولار" lines={gccLines('imf.iip_net')} from="2005" />
        <SeriesChart wide title="احتياطيات النقد الأجنبي (شهري)" lines={[{ indicator: 'ncsi.MON_RESERVES_FX', entity: 'OMN', label: 'احتياطيات النقد الأجنبي' }]} />
      </Section>
    </>
  );
}

const ROWS: ScoreRow[] = [
  { key: 'wb.BX.KLT.DINV.WD.GD.ZS', label: 'الاستثمار الأجنبي الداخل (% من الناتج)', better: 'high' },
  { key: 'wb.NE.GDI.FTOT.ZS', label: 'تكوين رأس المال الثابت (% من الناتج)', better: 'high' },
  { key: 'wb.NY.GNS.ICTR.ZS', label: 'الادخار القومي (% من الناتج)', better: 'high' },
  { key: 'wb.CM.MKT.LCAP.GD.ZS', label: 'القيمة السوقية للأسهم (% من الناتج)', better: 'high', digits: 0 },
  { key: 'wb.FS.AST.PRVT.GD.ZS', label: 'الائتمان للقطاع الخاص (% من الناتج)', better: 'high', digits: 0 },
  { key: 'wb.GOV_WGI_RQ.SC', label: 'جودة التنظيم (0–100)', better: 'high', digits: 0 },
  { key: 'wb.GOV_WGI_RL.SC', label: 'سيادة القانون (0–100)', better: 'high', digits: 0 },
  { key: 'wb.GOV_WGI_CC.SC', label: 'السيطرة على الفساد (0–100)', better: 'high', digits: 0 },
  { key: 'imf.iip_net', label: 'صافي مركز الاستثمار الدولي (مليار $)', better: 'high', digits: 0 },
];

/** الخليج: بطاقة أداء الاستثمار ومناخ الأعمال للدول الست. */
export function InvestmentGulfTab() {
  return (
    <Section id="scorecard" title="أين نقف بين الجيران؟" lead="آخر قيمة متاحة لكل دولة؛ الأخضر الأفضل بين الدول الست.">
      <ScoreTable rows={ROWS} title="بطاقة أداء الاستثمار الخليجية" subtitle="آخر قيمة فعلية — البنك الدولي وصندوق النقد" note="الأخضر الأفضل بين الدول الست في كل صف. عمق الأسواق والائتمان الأعلى قد يعكس مخاطر أعلى؛ ويُقرأ مع جودة الحوكمة." />
    </Section>
  );
}
