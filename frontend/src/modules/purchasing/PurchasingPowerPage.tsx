import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { formatNumber, formatPeriod } from '@/core/format';
import { useLatest } from '@/core/hooks';
import { gccLines } from '@/core/groups';
import { oman } from '@/core/lines';

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

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="wb.NY.GDP.PCAP.PP.CD" label="نصيب الفرد بتعادل القوة الشرائية" compactValue />
        <KeyStat entity="OMN" indicator="wb.PRICE_LEVEL_PRIVATE" label="مستوى الأسعار (أمريكا = 100)" goodWhen="down" />
        <KeyStat entity="OMN" indicator="wb.NE.CON.PRVT.PC.KD.ZG" label="نمو استهلاك الفرد الحقيقي" />
        <KeyStat entity="OMN" indicator="imf.cpi_yoy" label="التضخم السنوي" goodWhen="down" />
      </div>

      <RialValue />

      <Section title="الدخل بتعادل القوة الشرائية" lead="يُعدَّل الدخل بفروق الأسعار بين الدول، فيعكس ما يشتريه الفرد حقًا. المقارنة بين عُمان والخليج هنا أدق من الدولار الاسمي.">
        <SeriesChart title="نصيب الفرد من الناتج (تعادل القوة الشرائية)" lines={gccLines('wb.NY.GDP.PCAP.PP.CD')} from="1995" />
        <SeriesChart title="نصيب الفرد من الدخل القومي (تعادل القوة الشرائية)" lines={gccLines('wb.NY.GNP.PCAP.PP.CD')} from="1995" />
        <SeriesChart title="نصيب الفرد من الناتج: الاسمي مقابل المعدَّل بالأسعار" subtitle="دولار — عُمان" lines={[{ indicator: 'wb.NY.GDP.PCAP.CD', entity: 'OMN', label: 'بالدولار الاسمي' }, { indicator: 'wb.NY.GDP.PCAP.PP.CD', entity: 'OMN', label: 'بتعادل القوة الشرائية' }]} from="1995" />
        <SeriesChart title="نصيب الفرد (تعادل القوة الشرائية) — صندوق النقد مع التوقعات" lines={gccLines('imf.PPPPC')} from="2000" imfForecast />
      </Section>

      <Section title="مستوى الأسعار: هل المعيشة أغلى أم أرخص؟" lead="مستوى الأسعار = كم تكلّف سلة استهلاك الأسرة قياسًا بأمريكا (أمريكا = 100). أقل من 100 تعني أن الأسعار أرخص من أمريكا، وأعلى تعني أغلى.">
        <SeriesChart title="مستوى أسعار الاستهلاك (أمريكا = 100)" lines={gccLines('wb.PRICE_LEVEL_PRIVATE')} from="1995" />
        <SeriesChart title="معامل تعادل القوة الشرائية لعُمان" subtitle="ريال لكل دولار دولي" lines={oman('wb.PA.NUS.PRVT.PP', 'معامل الاستهلاك الخاص')} from="1995" />
      </Section>

      <Section title="غلاء المعيشة" lead="التضخم الشهري يأكل القوة الشرائية؛ أي البنود تقود الغلاء وكيف تقارن عُمان بجيرانها؟">
        <SeriesChart
          title="التضخم السنوي: عُمان والخليج (شهري)"
          subtitle="% سنويًا — صندوق النقد"
          lines={[
            { indicator: 'imf.cpi_yoy', entity: 'OMN', label: 'عُمان' },
            { indicator: 'imf.cpi_yoy', entity: 'SAU', label: 'السعودية' },
            { indicator: 'imf.cpi_yoy', entity: 'ARE', label: 'الإمارات' },
            { indicator: 'imf.cpi_yoy', entity: 'QAT', label: 'قطر' },
            { indicator: 'imf.cpi_yoy', entity: 'KWT', label: 'الكويت' },
            { indicator: 'imf.cpi_yoy', entity: 'BHR', label: 'البحرين' },
          ]}
          from="2021"
        />
        <SeriesChart
          title="التضخم حسب بند الإنفاق (عُمان)"
          subtitle="% سنويًا — صندوق النقد"
          lines={[
            { indicator: 'imf.cpi_yoy', entity: 'OMN', label: 'العام' },
            { indicator: 'imf.cpi_yoy_food', entity: 'OMN', label: 'الغذاء' },
            { indicator: 'imf.cpi_yoy_housing', entity: 'OMN', label: 'السكن والمرافق' },
            { indicator: 'imf.cpi_yoy_transport', entity: 'OMN', label: 'النقل' },
          ]}
          from="2021"
        />
        <SeriesChart title="الرقم القياسي لأسعار المستهلك (2018=100)" lines={[{ indicator: 'ncsi.CPI_INDEX', entity: 'OMN', label: 'الرقم القياسي' }]} />
        <SeriesChart title="الرقم القياسي لأسعار المستهلك (2010=100) — سنوي" lines={gccLines('wb.FP.CPI.TOTL')} from="2000" />
      </Section>

      <Section title="هل ينمو الدخل أسرع من الأسعار؟" lead="نمو الدخل والاستهلاك بعد استبعاد التضخم (بالأسعار الثابتة): فوق الصفر تحسّن حقيقي في المعيشة، وتحته تراجع.">
        <SeriesChart title="نمو نصيب الفرد من الناتج الحقيقي" lines={gccLines('wb.NY.GDP.PCAP.KD.ZG')} from="2005" kind="columns" last={15} />
        <SeriesChart title="نمو استهلاك الفرد الحقيقي" lines={gccLines('wb.NE.CON.PRVT.PC.KD.ZG')} from="2005" kind="columns" last={15} />
      </Section>
    </>
  );
}
