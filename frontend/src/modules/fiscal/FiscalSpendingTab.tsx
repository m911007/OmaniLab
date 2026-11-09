import { SeriesChart } from '@/core/components/SeriesChart';
import { Section } from '@/core/components/PageHead';
import { gccLines } from '@/core/groups';
import { ByEntity } from './ByEntity';
import { GovSpending } from './GovSpending';
import { SpendingGrowthHeat, SpendingNature } from './SpendingInsights';

const om = (code: string, label: string, color?: string) => ({ indicator: `ncsi.${code}`, entity: 'OMN', label, color });

/** الإنفاق: أين تُنفق الحكومة؟ كيف يتغيّر كل وجه؟ ما الملزم وما المرن؟ ثم الدعم. */
export default function FiscalSpendingTab() {
  return (
    <>
      <Section id="uses" title="أين تُنفق الحكومة؟" lead="أوجه الصرف الخمسة من الحساب الختامي لوزارة المالية: مكدّسة مع الإجمالي، أو حصصًا من الإجمالي، أو خطوطًا، أو دائريًا لسنة تختارها.">
        <GovSpending />
        <SpendingGrowthHeat />
        <SpendingNature />
      </Section>
      <Section id="by-entity" title="المصروفات حسب الجهة" lead="أي الوزارات والجهات تستهلك أكبر حصة من الإنفاق؟ الجاري للوزارات المدنية والإنمائي، من جداول الحساب الختامي لوزارة المالية.">
        <ByEntity indicator="mof.unit_exp_current" title="المصروفات الجارية للوزارات المدنية حسب الجهة" subtitle="وزارة المالية" note="المصروفات الجارية للوزارات المدنية والوحدات الحكومية (الجدول 4). تُعرض السنوات التي يطابق مجموع جهاتها الإجمالي المنشور بفارق ≤ 1.5%. لا تشمل الدفاع والأمن وخدمة الدين." />
        <ByEntity indicator="mof.unit_exp_dev" title="المصروفات الإنمائية حسب الجهة" subtitle="وزارة المالية" note="المصروفات الإنمائية للوزارات المدنية والوحدات الحكومية والهيئات العامة (الجدول 5)، للسنوات التي تطابق إجمالي الاستثمار المنشور بفارق ≤ 1.5%." />
      </Section>
      <Section title="الدعم والمساهمات" lead="دعم الوقود والكهرباء والغذاء والحماية الاجتماعية (المركز الوطني للإحصاء)، ودعم الوقود الأحفوري بتقدير صندوق النقد.">
                <SeriesChart wide title="الدعم والمساهمات" subtitle="مليون ريال عُماني" kind="stacked" last={20} lines={[om('SUPPORT_FUEL', 'دعم الوقود', 'var(--series-2)'), om('SUPPORT_ELECTRICITY', 'دعم الكهرباء', 'var(--series-4)'), om('SUPPORT_FOOD', 'دعم الغذاء', 'var(--series-3)')]} from="2002" />
      </Section>
      <Section id="fossil" title="دعم الوقود الأحفوري" lead="الدعم الصريح هو الفرق بين تكلفة الوقود وسعر بيعه؛ والضمني يضيف التكاليف البيئية والصحية غير المُسعَّرة (تقديرات صندوق النقد).">
        <SeriesChart wide title="الدعم الصريح للوقود الأحفوري" subtitle="% من الناتج" lines={gccLines('imf.ffs_explicit')} from="2010" />
        <SeriesChart wide title="الدعم الضمني للوقود الأحفوري" subtitle="% من الناتج" lines={gccLines('imf.ffs_implicit')} from="2010" />
      </Section>
    </>
  );
}
