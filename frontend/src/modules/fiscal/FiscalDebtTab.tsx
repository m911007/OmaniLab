import { Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SwitchChart } from '@/core/components/SwitchChart';
import { CreditRatings } from '@/modules/oman/CreditRatings';
import { oman } from '@/core/lines';
import { FinancingMix } from './GovSpending';
import { InterestBurden } from './InterestBurden';

const on = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'OMN', label, color });

/** الدين والتمويل: كيف يُموَّل العجز؟ كم الدين وهل هو مستدام؟ كم تقتطع الفوائد؟ وماذا تقول وكالات التصنيف؟ */
export default function FiscalDebtTab() {
  return (
    <>
      <Section id="financing" title="كيف يُموَّل العجز؟" lead="من الاقتراض الخارجي والمحلي والاحتياطيات: مجموع وسائل التمويل يساوي العجز، وفي سنوات الفائض يظهر سدادًا للديون أو ادخارًا.">
        <FinancingMix />
      </Section>
      <Section id="debt" title="حجم الدين واستدامته" lead="الدين نسبةً من الناتج، وما يتبقى منه بعد الأصول، وعبء فوائده على الإيراد. الخط المتقطع توقعات صندوق النقد.">
        <SwitchChart wide height={340} title="الدين الحكومي" variants={[
          { value: 'debt', label: 'إجمالي الدين', subtitle: '% من الناتج — الخط المتقطع توقعات', lines: oman('imf.GGXWDG_NGDP', 'عُمان'), from: '2005', oil: true, imfForecast: true },
          { value: 'net-debt', label: 'إجمالي وصافي الدين', subtitle: '% من الناتج — الدين مطروحًا منه الأصول السائلة', from: '2000', imfForecast: true, oil: true, lines: [on('imf.G_XWDG_G01_GDP_PT', 'إجمالي الدين', 'var(--series-2)'), on('imf.GGXWDN_G01_GDP_PT', 'صافي الدين', 'var(--series-1)')] },
          { value: 'private-debt', label: 'مع دين القطاع الخاص', subtitle: '% من الناتج — قروض وسندات', from: '2000', lines: [on('imf.PVD_LS', 'القطاع الخاص'), on('imf.CG_DEBT_GDP', 'الحكومة المركزية')] },
        ]} />
        <InterestBurden />
      </Section>
      <CreditRatings />
      <Section id="borrowing" title="كلفة الاقتراض الدولي">
        <SeriesChart wide height={300} title="كلفة الاقتراض الدولي" subtitle="قسيمة السند الدولي المُصدَر، % — لا إصدار في 2022–2023" kind="columns" last={12} lines={[on('oman.eurobond_coupon', 'قسيمة السند')]} note="قسيمة أقرب سند دولاري دولي أصدرته عُمان في كل سنة (مجمّع يدويًا من إصدارات السندات والصكوك السيادية)." />
      </Section>
    </>
  );
}
