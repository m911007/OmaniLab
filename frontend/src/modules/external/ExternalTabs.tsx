import { Section } from '@/core/components/PageHead';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { oman } from '@/core/lines';
import { BopSection, ExternalSection } from '@/modules/oman/sections';
import { ScoreTable, type ScoreRow } from '@/modules/fiscal/GulfScorecard';
import { ExportDependence, ReserveCover } from './ExternalDerived';

const H = 340;
const onr = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'OMN', label, color, omr: true });

/** الصورة العامة: من أين تأتي الصادرات وما بنود الحساب الجاري، ثم اعتماد الصادرات على النفط وتغطية الاحتياطيات للواردات. */
export function ExternalOverviewTab() {
  return (
    <>
      <BopSection />
      <Section id="dependence" title="الاعتماد والمتانة" lead="هل تعتمد الصادرات على النفط؟ وهل تكفي الاحتياطيات لتغطية الواردات؟ (مشتق من بيانات البنك المركزي).">
        <ExportDependence />
        <ReserveCover />
      </Section>
    </>
  );
}

/** الاستثمار الدولي والاحتياطيات: مركز عُمان تجاه العالم (أصول والتزامات)، والاستثمار الأجنبي والتحويلات. */
export function ExternalPositionTab() {
  return (
    <>
      <Section id="iip" title="مركز الاستثمار الدولي" lead="الأصول الأجنبية لعُمان مقابل التزاماتها تجاه العالم، والاحتياطيات الدولية (صندوق النقد والبنك الدولي).">
        <SwitchChart wide height={H} title="الأصول والالتزامات الأجنبية" imfForecast variants={[
          { value: 'iip', label: 'أصول مقابل التزامات', subtitle: 'مليون ريال — محوَّل من الدولار', from: '2005', lines: [onr('imf.iip_assets', 'الأصول الأجنبية', 'var(--series-3)'), onr('imf.iip_liabilities', 'الالتزامات', 'var(--series-2)')] },
          { value: 'net', label: 'صافي المركز', subtitle: 'مليار دولار — الموجب صافي دائن', kind: 'columns', last: 20, from: '2005', lines: oman('imf.iip_net', 'صافي المركز') },
          { value: 'reserves', label: 'الاحتياطيات الدولية', subtitle: 'مليون ريال — البنك الدولي', oil: true, from: '2000', lines: [onr('wb.FI.RES.TOTL.CD', 'الاحتياطيات')] },
        ]} />
      </Section>
      <ExternalSection />
    </>
  );
}

const ROWS: ScoreRow[] = [
  { key: 'imf.BCA_NGDPD', label: 'الحساب الجاري (% من الناتج)', better: 'high' },
  { key: 'wb.NE.EXP.GNFS.ZS', label: 'الصادرات (% من الناتج)', better: 'high' },
  { key: 'wb.NE.IMP.GNFS.ZS', label: 'الواردات (% من الناتج)', better: 'low' },
  { key: 'wb.NE.TRD.GNFS.ZS', label: 'الانفتاح التجاري (% من الناتج)', better: 'high' },
  { key: 'wb.BX.KLT.DINV.WD.GD.ZS', label: 'الاستثمار الأجنبي الداخل (% من الناتج)', better: 'high' },
  { key: 'imf.iip_net', label: 'صافي مركز الاستثمار الدولي (مليار $)', better: 'high', digits: 0 },
];

/** الخليج: عُمان مقابل الدول الست في الحساب الجاري والانفتاح والاستثمار الأجنبي. */
export function ExternalGulfTab() {
  return (
    <>
      <Section id="scorecard" title="أين نقف بين الجيران؟" lead="آخر سنة فعلية متاحة لكل دولة؛ الأخضر الأفضل بين الدول الست.">
        <ScoreTable rows={ROWS} title="بطاقة أداء القطاع الخارجي الخليجية" subtitle="آخر سنة فعلية — صندوق النقد والبنك الدولي" note="الأخضر الأفضل بين الدول الست في كل صف (الواردات الأقل أفضل في ميزان السلع لكنها تعكس أيضًا حجم الاقتصاد؛ تُقرأ مع الانفتاح). الترتيب نسبي لا مطلق." />
      </Section>
      <Section id="history" title="المسار التاريخي" lead="الدول الست معًا (الخط المتقطع توقعات صندوق النقد).">
        <SwitchChart wide height={H} title="القطاع الخارجي: عُمان والخليج" imfForecast variants={[
          { value: 'ca', label: 'الحساب الجاري', subtitle: '% من الناتج', from: '2005', imfForecast: true, oil: true, lines: gccLines('imf.BCA_NGDPD') },
          { value: 'fdi', label: 'الاستثمار الأجنبي', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.BX.KLT.DINV.WD.GD.ZS') },
          { value: 'open', label: 'الانفتاح التجاري', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NE.TRD.GNFS.ZS') },
          { value: 'remit', label: 'تحويلات العاملين', subtitle: 'مليون ريال — محوَّل من الدولار', from: '2000', lines: gccLines('wb.BX.TRF.PWKR.CD.DT').map((l) => ({ ...l, omr: true })) },
        ]} />
      </Section>
    </>
  );
}
