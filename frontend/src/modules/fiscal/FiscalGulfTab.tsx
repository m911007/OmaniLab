import { Section } from '@/core/components/PageHead';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { GulfScorecard } from './GulfScorecard';

/** الخليج: أين تقف عُمان بين الدول الست؟ بطاقة أداء تلوّن الأفضل والأسوأ، ثم المسار التاريخي لكل مؤشر. */
export default function FiscalGulfTab() {
  return (
    <>
      <Section id="scorecard" title="أين نقف بين الجيران؟" lead="مقارنة سريعة بآخر سنة فعلية: كل صف مؤشر، والأخضر الأفضل بين الدول الست.">
        <GulfScorecard />
      </Section>
      <Section id="history" title="المسار التاريخي" lead="اختر المؤشر لترى الدول الست معًا (الخط المتقطع توقعات صندوق النقد)؛ وفي «مقارنة» تضيف دولًا أخرى أو تعرض الترتيب بآخر قيمة.">
        <SwitchChart wide height={340} title="المالية العامة: عُمان والخليج" imfForecast variants={[
          { value: 'bal', label: 'رصيد الميزانية', subtitle: '% من الناتج', from: '2005', imfForecast: true, oil: true, lines: gccLines('imf.GGXCNL_NGDP') },
          { value: 'debt', label: 'الدين الحكومي', subtitle: '% من الناتج', from: '2005', imfForecast: true, lines: gccLines('imf.GGXWDG_NGDP') },
          { value: 'rev', label: 'الإيرادات', subtitle: '% من الناتج', from: '2005', imfForecast: true, oil: true, lines: gccLines('imf.GGR_G01_GDP_PT') },
          { value: 'exp', label: 'المصروفات', subtitle: '% من الناتج', from: '2005', imfForecast: true, oil: true, lines: gccLines('imf.G_X_G01_GDP_PT') },
          { value: 'primary', label: 'الرصيد الأولي', subtitle: '% من الناتج — قبل الفوائد', from: '2005', imfForecast: true, oil: true, lines: gccLines('imf.GGXONLB_G01_GDP_PT') },
          { value: 'net-debt', label: 'صافي الدين', subtitle: '% من الناتج — القيم السالبة أصول حكومية صافية', from: '2005', imfForecast: true, lines: gccLines('imf.GGXWDN_G01_GDP_PT') },
          { value: 'interest', label: 'الفوائد المدفوعة', subtitle: '% من الناتج', from: '1995', lines: gccLines('imf.ie') },
        ]} />
      </Section>
    </>
  );
}
