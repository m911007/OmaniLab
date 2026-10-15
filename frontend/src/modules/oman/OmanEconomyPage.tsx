import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { lastActualYear, oman } from '@/core/lines';

const A = lastActualYear();

export default function OmanEconomyPage() {
  return (
    <>
      <PageHead title="الاقتصاد العُماني" lead="الناتج والأسعار والمالية العامة والقطاع الخارجي والطاقة والسكان والاتصالات، من صندوق النقد والبنك الدولي والمركز الوطني للإحصاء ومنافذ." />

      <nav aria-label="أقسام الصفحة" className="controls">
        {[
          ['growth', 'النمو والناتج'],
          ['prices', 'الأسعار'],
          ['fiscal', 'المالية العامة'],
          ['external', 'القطاع الخارجي'],
          ['energy', 'النفط والغاز'],
          ['people', 'السكان والعمل'],
          ['digital', 'الاتصالات'],
        ].map(([id, label]) => (
          <a key={id} href={`#${id}`} className="chip neutral">
            {label}
          </a>
        ))}
      </nav>

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="imf.NGDPD" label="الناتج المحلي الإجمالي" maxPeriod={A} />
        <KeyStat entity="OMN" indicator="imf.NGDPDPC" label="نصيب الفرد من الناتج" maxPeriod={A} />
        <KeyStat entity="OMN" indicator="imf.GGXCNL_NGDP" label="رصيد الميزانية" maxPeriod={A} goodWhen="up" />
        <KeyStat entity="OMN" indicator="imf.BCA_NGDPD" label="رصيد الحساب الجاري" maxPeriod={A} goodWhen="up" />
      </div>

      <Section id="growth" title="النمو والناتج">
        <SeriesChart title="نمو الناتج المحلي الإجمالي الحقيقي" lines={oman('imf.NGDP_RPCH', 'عُمان')} from="2005" imfForecast />
        <SeriesChart title="الناتج المحلي الإجمالي بالأسعار الجارية" lines={oman('imf.NGDPD', 'صندوق النقد')} from="2005" imfForecast />
        <SeriesChart title="نصيب الفرد من الناتج المحلي" lines={oman('imf.NGDPDPC', 'عُمان')} from="2005" imfForecast />
        <SeriesChart
          title="الناتج المحلي: الأسعار الجارية والثابتة"
          subtitle="الحسابات القومية الرسمية"
          kind="columns"
          lines={[
            { indicator: 'ncsi.GDP_CURRENT', entity: 'OMN', label: 'الأسعار الجارية', color: 'var(--series-1)' },
            { indicator: 'ncsi.GDP_CONSTANT', entity: 'OMN', label: 'الأسعار الثابتة', color: 'var(--series-2)' },
          ]}
          last={14}
        />
      </Section>

      <Section id="prices" title="الأسعار">
        <SeriesChart title="التضخم السنوي (شهري)" lines={[{ indicator: 'ncsi.CPI_INFLATION', entity: 'OMN', label: 'التضخم السنوي' }]} />
        <SeriesChart title="الرقم القياسي لأسعار المستهلك (2018=100)" lines={[{ indicator: 'ncsi.CPI_INDEX', entity: 'OMN', label: 'الرقم القياسي' }]} />
        <SeriesChart title="التضخم السنوي (متوسط)" lines={oman('imf.PCPIPCH', 'عُمان')} from="2005" imfForecast />
      </Section>

      <Section id="fiscal" title="المالية العامة">
        <SeriesChart title="إجمالي الدين الحكومي" lines={oman('imf.GGXWDG_NGDP', 'عُمان')} from="2005" imfForecast />
        <SeriesChart title="رصيد الميزانية الحكومية" lines={oman('imf.GGXCNL_NGDP', 'عُمان')} from="2005" imfForecast />
        <SeriesChart title="إيرادات الضرائب" lines={oman('wb.GC.TAX.TOTL.GD.ZS', 'عُمان')} from="2000" />
      </Section>

      <Section id="external" title="القطاع الخارجي">
        <SeriesChart title="رصيد الحساب الجاري" lines={oman('imf.BCA_NGDPD', 'عُمان')} from="2005" imfForecast />
        <SeriesChart title="الاستثمار الأجنبي المباشر الداخل" lines={oman('wb.BX.KLT.DINV.WD.GD.ZS', 'عُمان')} from="2000" />
        <SeriesChart
          title="الصادرات والواردات من السلع والخدمات"
          kind="columns"
          lines={[
            { indicator: 'ncsi.EXPORTS_GS', entity: 'OMN', label: 'الصادرات', color: 'var(--series-1)' },
            { indicator: 'ncsi.IMPORTS_GS', entity: 'OMN', label: 'الواردات', color: 'var(--series-2)' },
          ]}
          last={14}
        />
        <SeriesChart title="الاحتياطيات الدولية" lines={oman('wb.FI.RES.TOTL.CD', 'عُمان')} from="2000" />
      </Section>

      <Section id="energy" title="النفط والغاز">
        <SeriesChart title="سعر النفط العُماني" subtitle="متوسط شهري" lines={[{ indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'دولار/برميل' }]} from="2010" />
        <SeriesChart title="إنتاج وصادرات النفط الخام" subtitle="متوسط يومي" lines={[
          { indicator: 'ncsi.OIL_PRODUCTION', entity: 'OMN', label: 'الإنتاج', color: 'var(--series-1)' },
          { indicator: 'ncsi.OIL_EXPORTS', entity: 'OMN', label: 'الصادرات', color: 'var(--series-2)' },
        ]} />
        <SeriesChart title="إنتاج الغاز الطبيعي" subtitle="شهري" lines={[{ indicator: 'ncsi.GAS_PRODUCTION', entity: 'OMN', label: 'الإنتاج' }]} />
        <SeriesChart title="ريع النفط" lines={oman('wb.NY.GDP.PETR.RT.ZS', 'عُمان')} from="1990" />
      </Section>

      <Section id="people" title="السكان والعمل">
        <SeriesChart title="عدد السكان" lines={[
          { indicator: 'gccstat.POP', entity: 'OMN', label: 'إجمالي السكان', color: 'var(--series-1)' },
          { indicator: 'gccstat.POP_CITIZEN', entity: 'OMN', label: 'المواطنون', color: 'var(--series-2)' },
          { indicator: 'gccstat.POP_NON_CITIZEN', entity: 'OMN', label: 'غير المواطنين', color: 'var(--series-3)' },
        ]} />
        <SeriesChart title="معدل البطالة" lines={[{ indicator: 'gccstat.UNEMPLOYMENT', entity: 'OMN', label: 'عُمان' }]} />
        <SeriesChart title="العاملون" lines={[
          { indicator: 'gccstat.EMPLOYED', entity: 'OMN', label: 'إجمالي العاملين', color: 'var(--series-1)' },
          { indicator: 'gccstat.EMPLOYED_CITIZEN', entity: 'OMN', label: 'المواطنون', color: 'var(--series-2)' },
        ]} />
        <SeriesChart title="طلبة التعليم العالي" lines={[{ indicator: 'gccstat.STUDENTS_UNI', entity: 'OMN', label: 'الطلبة' }]} />
      </Section>

      <Section id="digital" title="الاتصالات">
        <SeriesChart title="مشتركو الهاتف المتنقل" subtitle="شهري" lines={[
          { indicator: 'ncsi.MOBILE_SUBSCRIBERS', entity: 'OMN', label: 'الإجمالي', color: 'var(--series-1)' },
          { indicator: 'ncsi.MOBILE_POSTPAID', entity: 'OMN', label: 'دفع آجل', color: 'var(--series-2)' },
        ]} />
        <SeriesChart title="مستخدمو الإنترنت" lines={oman('wb.IT.NET.USER.ZS', 'عُمان')} from="2005" />
      </Section>
    </>
  );
}
