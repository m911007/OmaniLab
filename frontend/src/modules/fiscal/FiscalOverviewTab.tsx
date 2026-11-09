import { Section } from '@/core/components/PageHead';
import { BreakevenChart } from './BreakevenChart';
import { BudgetActual } from './BudgetActual';
import { BudgetVsActual } from './BudgetVsActual';
import { FiscalBridge } from './FiscalBridge';
import { FiscalHeadline } from './FiscalHeadline';

/** الصورة العامة: هل الوضع المالي صحي؟ الخلاصة ثم من الإيراد إلى الرصيد ثم الرصيد وسعر التعادل ثم الالتزام بالميزانية. */
export default function FiscalOverviewTab() {
  return (
    <>
      <FiscalHeadline />
      <Section id="bridge" title="من أين جاء الإيراد وأين ذهب؟" lead="اختر سنة بشريط التمرير لترى جسر الإيراد إلى الرصيد، وقارن الإيرادات والمصروفات والفائض أو العجز عبر السنوات.">
        <FiscalBridge />
        <BudgetActual />
      </Section>
      <Section id="breakeven" title="هل يكفي سعر النفط؟" lead="الفائض يتحقق حين يعلو سعر النفط سعر التعادل، والعجز حين يهبط دونه.">
        <BreakevenChart />
      </Section>
      <Section id="execution" title="هل التزمت الحكومة بميزانيتها؟" lead="الميزانية المعتمدة تُقر قبل السنة، والحساب الختامي يبيّن ما تحقق فعلًا.">
        <BudgetVsActual />
      </Section>
    </>
  );
}
