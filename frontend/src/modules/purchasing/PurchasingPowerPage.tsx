import { ReadingGrid } from '@/core/components/ReadingGrid';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SwitchChart } from '@/core/components/SwitchChart';
import { formatNumber, formatPeriod } from '@/core/format';
import { useLatest } from '@/core/hooks';
import { gccLines } from '@/core/groups';
import { oman } from '@/core/lines';

const H = 340;

/** ماذا يعني التضخم المتراكم؟ ما كان يُشترى بـ100 ريال عام 2018 يكلّف اليوم كذا. */
function RialValue() {
  const { data } = useLatest('OMN', ['ncsi.CPI_INDEX']);
  const item = data?.items.find((i) => i.indicator.key === 'ncsi.CPI_INDEX');
  if (!item) {
    return null;
  }
  const cost = item.value;
  const power = (100 / cost) * 100;
  return (
    <section className="card narrative" aria-label="قيمة الريال">
      <p>
        ما كان يُشترى بـ <strong className="num">100</strong> ريال في 2018 يكلّف اليوم (<span className="num">{formatPeriod(item.period)}</span>) نحو <strong className="num">{formatNumber(cost, 1)}</strong> ريالًا؛ أي أن القوة الشرائية للريال تساوي <strong className="num">{formatNumber(power, 1)}%</strong> مما كانت عليه.
      </p>
    </section>
  );
}

export default function PurchasingPowerPage() {
  return (
    <>
      <PageHead
        title="القوة الشرائية"
        lead="كم يشتري الدخل فعلًا؟ الدولار الاسمي يخدع عند مقارنة الدول لأن الأسعار تختلف؛ لذا نقيس الدخل بتعادل القوة الشرائية، ونقارن مستوى الأسعار بأمريكا، ونتتبع غلاء المعيشة وأثره على قيمة الريال."
      />

      <ReadingGrid>
        <KeyStat entity="OMN" indicator="wb.NY.GDP.PCAP.PP.CD" label="نصيب الفرد بتعادل القوة الشرائية" compactValue />
        <KeyStat entity="OMN" indicator="wb.PRICE_LEVEL_PRIVATE" label="مستوى الأسعار (أمريكا = 100)" goodWhen="down" />
        <KeyStat entity="OMN" indicator="wb.NE.CON.PRVT.PC.KD.ZG" label="نمو استهلاك الفرد الحقيقي" />
        <KeyStat entity="OMN" indicator="wb.NY.GNP.PCAP.PP.CD" label="نصيب الفرد من الدخل القومي (تعادل القوة الشرائية)" goodWhen="up" />
      </ReadingGrid>

      <RialValue />

      <Section id="ppp" title="الدخل بتعادل القوة الشرائية" lead="يُعدَّل الدخل بفروق الأسعار بين الدول، فيعكس ما يشتريه الفرد حقًا؛ والمقارنة بالخليج هنا أدق من الدولار الاسمي.">
        <SwitchChart wide height={H} title="الدخل بتعادل القوة الشرائية" variants={[
          { value: 'gdp', label: 'نصيب الفرد من الناتج', subtitle: 'دولار دولي — البنك الدولي', from: '1995', lines: gccLines('wb.NY.GDP.PCAP.PP.CD') },
          { value: 'gni', label: 'نصيب الفرد من الدخل القومي', subtitle: 'دولار دولي — البنك الدولي', from: '1995', lines: gccLines('wb.NY.GNP.PCAP.PP.CD') },
          { value: 'nominal', label: 'الاسمي مقابل المعدَّل', subtitle: 'دولار — عُمان: الفرق بين الخطين أثر رخص الأسعار المحلية', from: '1995', lines: [{ indicator: 'wb.NY.GDP.PCAP.CD', entity: 'OMN', label: 'بالدولار الاسمي' }, { indicator: 'wb.NY.GDP.PCAP.PP.CD', entity: 'OMN', label: 'بتعادل القوة الشرائية' }] },
          { value: 'imf', label: 'مع توقعات صندوق النقد', subtitle: 'دولار دولي — الخط المتقطع توقعات', from: '2000', imfForecast: true, lines: gccLines('imf.PPPPC') },
        ]} />
      </Section>

      <Section id="level" title="مستوى الأسعار: هل المعيشة أغلى أم أرخص؟" lead="كم تكلّف سلة استهلاك الأسرة قياسًا بأمريكا (أمريكا = 100): أقل من 100 أرخص، وأعلى أغلى.">
        <SwitchChart wide height={H} title="مستوى الأسعار" variants={[
          { value: 'level', label: 'مستوى أسعار الاستهلاك', subtitle: 'أمريكا = 100', from: '1995', lines: gccLines('wb.PRICE_LEVEL_PRIVATE') },
          { value: 'factor', label: 'معامل تعادل القوة الشرائية', subtitle: 'ريال لكل دولار دولي', from: '1995', lines: oman('wb.PA.NUS.PRVT.PP', 'معامل الاستهلاك الخاص') },
        ]} />
      </Section>

      <Section id="real" title="هل ينمو الدخل أسرع من الأسعار؟" lead="نمو الدخل والاستهلاك بعد استبعاد التضخم: فوق الصفر تحسّن حقيقي في المعيشة، وتحته تراجع. التضخم وبنوده في تبويب «التضخم والأسعار».">
        <SwitchChart wide height={H} title="النمو الحقيقي للفرد" variants={[
          { value: 'gdp', label: 'نصيب الفرد من الناتج', subtitle: '% سنويًا بالأسعار الثابتة', kind: 'columns', last: 15, from: '2005', lines: gccLines('wb.NY.GDP.PCAP.KD.ZG') },
          { value: 'cons', label: 'استهلاك الفرد', subtitle: '% سنويًا بالأسعار الثابتة', kind: 'columns', last: 15, from: '2005', lines: gccLines('wb.NE.CON.PRVT.PC.KD.ZG') },
        ]} />
      </Section>
    </>
  );
}
