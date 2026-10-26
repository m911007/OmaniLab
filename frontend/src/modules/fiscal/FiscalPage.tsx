import { ReadingGrid } from '@/core/components/ReadingGrid';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SwitchChart } from '@/core/components/SwitchChart';
import { KeyStat } from '@/core/components/KeyStat';
import { CreditRatings } from '../oman/CreditRatings';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { gccLines } from '@/core/groups';
import { lastActualYear } from '@/core/lines';

const A = lastActualYear();
const on = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'OMN', label, color });

export default function FiscalPage() {
  return (
    <>
      <PageHead title="الميزانية العامة والدين" lead="الإيرادات والمصروفات والأرصدة والدين لعُمان ودول الخليج، من مرصد المالية العامة وقاعدة الدين العالمية لصندوق النقد. الخط المتقطع توقعات." />
      <ReadingGrid>
        <KeyStat entity="OMN" indicator="imf.GGXWDG_NGDP" label="الدين الحكومي (% من الناتج)" maxPeriod={A} goodWhen="down" />
        <KeyStat entity="OMN" indicator="imf.GGXCNL_NGDP" label="رصيد الميزانية (% من الناتج)" maxPeriod={A} goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.BUDGET_BALANCE" label="الفائض/العجز الفعلي (مليون ر.ع)" compactValue goodWhen="up" />
        <KeyStat entity="OMN" indicator="mof.exp_total" label="الإنفاق العام الفعلي (مليار ر.ع)" />
      </ReadingGrid>

      <Section title="عُمان: الميزانية الفعلية وأوجه الصرف" lead="الأرقام الفعلية بالريال العُماني (لا نسب من الناتج فقط): الإيرادات والمصروفات والفائض أو العجز، وعلى ماذا تنفق الحكومة (الدفاع، الوزارات المدنية، الاستثمار، الدعم)، وكلفة الاقتراض الدولي. سعر النفط خط متقطع للسياق.">
        <SwitchChart wide height={340} title="الميزانية الفعلية" variants={[
          { value: 'flows', label: 'الإيرادات والمصروفات', subtitle: 'مليون ريال عُماني — المركز الوطني للإحصاء', oil: true, from: '2003', lines: [on('ncsi.REV_TOTAL', 'الإيرادات', 'var(--series-3)'), on('ncsi.EXP_TOTAL', 'المصروفات', 'var(--series-2)')] },
          { value: 'balance', label: 'الفائض/العجز', subtitle: 'مليون ريال — الإيرادات − المصروفات (الموجب فائض)', kind: 'columns', oil: true, last: 22, lines: [on('ncsi.BUDGET_BALANCE', 'الفائض/العجز')] },
          { value: 'rev-src', label: 'مصدر الإيراد', subtitle: 'نفط وغاز مقابل غير نفطي — مليون ريال', kind: 'stacked', last: 22, oil: true, lines: [on('ncsi.REV_OIL', 'نفط', 'var(--series-2)'), on('ncsi.REV_GAS', 'غاز', 'var(--series-4)'), on('ncsi.REV_NON_OIL', 'غير نفطي', 'var(--series-3)')] },
        ]} />
        <SwitchChart wide height={340} title="أوجه صرف الحكومة (الحساب الختامي)" variants={[
          { value: 'lines', label: 'حسب الوجه', subtitle: 'مليار ريال عُماني — وزارة المالية', from: '2012', lines: [on('mof.exp_defense', 'الدفاع والأمن', 'var(--series-1)'), on('mof.exp_civil', 'الوزارات المدنية (جارية)', 'var(--series-3)'), on('mof.exp_other_current', 'بقية الجارية (خدمة الدين وغيرها)', 'var(--series-5)'), on('mof.exp_investment', 'الاستثمارية والإنمائية', 'var(--series-7)'), on('mof.exp_contributions', 'المساهمات والدعم', 'var(--series-4)')] },
          { value: 'stack', label: 'مكدّس', subtitle: 'مليار ريال — تركيب الإنفاق', kind: 'stacked', last: 14, lines: [on('mof.exp_defense', 'الدفاع والأمن', 'var(--series-1)'), on('mof.exp_civil', 'الوزارات المدنية', 'var(--series-3)'), on('mof.exp_other_current', 'بقية الجارية', 'var(--series-5)'), on('mof.exp_investment', 'الاستثمارية', 'var(--series-7)'), on('mof.exp_contributions', 'المساهمات والدعم', 'var(--series-4)')] },
          { value: 'total', label: 'الإجمالي', subtitle: 'مليار ريال — إجمالي الإنفاق العام الفعلي', kind: 'columns', last: 14, oil: true, lines: [on('mof.exp_total', 'إجمالي الإنفاق')] },
        ]} note="لقطة يدوية من الحساب الختامي السنوي لوزارة المالية؛ 2021 غير منشور. بقية الجارية مشتقة (جملة الجارية − الدفاع − المدنية) وتشمل خدمة الدين وبنودًا أخرى." />
        <SeriesChart wide height={300} title="كلفة الاقتراض الدولي" subtitle="قسيمة السند الدولي المُصدَر، % — لا إصدار في 2022–2023" kind="columns" last={12} lines={[on('oman.eurobond_coupon', 'قسيمة السند')]} note="قسيمة أقرب سند دولاري دولي أصدرته عُمان في كل سنة (مجمّع يدويًا من إصدارات السندات والصكوك السيادية)." />
      </Section>

      <CreditRatings />

      <Section title="الإيرادات والمصروفات">
        <SeriesChart oil title="الإيرادات الحكومية" lines={gccLines('imf.GGR_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="المصروفات الحكومية" lines={gccLines('imf.G_X_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="الرصيد الكلي للميزانية" lines={gccLines('imf.GGXCNL_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="الرصيد الأولي (قبل الفوائد)" lines={gccLines('imf.GGXONLB_G01_GDP_PT')} from="2005" imfForecast />
      </Section>
      <Section title="الدين">
        <SeriesChart oil title="إجمالي الدين الحكومي" lines={gccLines('imf.G_XWDG_G01_GDP_PT')} from="2005" imfForecast />
        <SeriesChart oil title="صافي الدين الحكومي" lines={gccLines('imf.GGXWDN_G01_GDP_PT')} from="2005" imfForecast note="القيم السالبة تعني أصولًا حكومية صافية." />
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
