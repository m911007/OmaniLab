import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { gccLines, peerLines } from '@/core/groups';
import { lastActualYear } from '@/core/lines';

const A = lastActualYear();

export default function FiscalPage() {
  return (
    <>
      <PageHead title="الميزانية العامة والدين" lead="الإيرادات والمصروفات والأرصدة والدين لعُمان ودول الخليج، من مرصد المالية العامة وقاعدة الدين العالمية لصندوق النقد. الخط المتقطع توقعات." />
      <Section title="الإيرادات والمصروفات">
        <SeriesChart oil title="الإيرادات الحكومية" lines={gccLines('imf.GGR_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="المصروفات الحكومية" lines={gccLines('imf.G_X_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="الرصيد الكلي للميزانية" lines={gccLines('imf.GGXCNL_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="الرصيد الأولي (قبل الفوائد)" lines={gccLines('imf.GGXONLB_G01_GDP_PT')} from="2005" imfForecast />
      </Section>
      <Section title="الدين">
        <SeriesChart oil title="إجمالي الدين الحكومي" lines={gccLines('imf.G_XWDG_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="صافي الدين الحكومي" lines={gccLines('imf.GGXWDN_G01_GDP_PT')} from="2005" imfForecast note="القيم السالبة تعني أصولًا حكومية صافية." />
        <SeriesChart title="الرصيد المعدَّل دوريًا" lines={gccLines('imf.GGCB_G01_PGDP_PT')} from="2005" imfForecast />
        <SeriesChart title="دين القطاع الخاص (للدول المتوفرة)" lines={peerLines('imf.Privatedebt_all')} />
      </Section>
      <Section title="عبء خدمة الدين والسلاسل الطويلة" lead="الفوائد المدفوعة على الدين تقيس ما تقتطعه خدمة الدين من الإيرادات؛ والسلاسل الطويلة (صندوق النقد FPP) تتيح قراءة الدورة عبر عقود.">
        <SeriesChart oil title="الفوائد المدفوعة على الدين العام" subtitle="% من الناتج" lines={gccLines('imf.ie')} from="1995" />
        <SeriesChart oil title="الرصيد الأولي (سلسلة طويلة)" subtitle="% من الناتج" lines={gccLines('imf.pb')} from="1990" />
        <SeriesChart oil title="الإيرادات الحكومية (سلسلة طويلة)" subtitle="% من الناتج" lines={gccLines('imf.rev')} from="1990" />
        <SeriesChart oil title="المصروفات الحكومية (سلسلة طويلة)" subtitle="% من الناتج" lines={gccLines('imf.exp')} from="1990" />
      </Section>
      <Section title="دعم الوقود الأحفوري" lead="الدعم الصريح هو الفرق بين تكلفة الوقود وسعر بيعه؛ والضمني يضيف التكاليف البيئية والصحية والازدحام غير المُسعَّرة. تقديرات صندوق النقد حتى السنة الجارية.">
        <SeriesChart title="الدعم الصريح للوقود الأحفوري" subtitle="% من الناتج" lines={gccLines('imf.ffs_explicit')} from="2010" />
        <SeriesChart title="الدعم الضمني للوقود الأحفوري" subtitle="% من الناتج" lines={gccLines('imf.ffs_implicit')} from="2010" />
      </Section>
      <Section title="عُمان في العالم" lead="ترتيب الدول بحسب آخر سنة فعلية.">
        <WorldRankCard title="الدين الحكومي الإجمالي" indicator="imf.GGXWDG_NGDP" maxPeriod={A} />
        <WorldRankCard title="الإيرادات الحكومية من الناتج" indicator="imf.GGR_G01_GDP_PT" maxPeriod={A} />
      </Section>
    </>
  );
}
