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
          ['bop', 'ميزان المدفوعات'],
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
        <SeriesChart oil title="نمو الناتج المحلي الإجمالي الحقيقي" lines={oman('imf.NGDP_RPCH', 'عُمان')} from="2005" imfForecast />
        <SeriesChart oil title="الناتج المحلي الإجمالي بالأسعار الجارية" lines={oman('imf.NGDPD', 'صندوق النقد')} from="2005" imfForecast />
        <SeriesChart oil title="نصيب الفرد من الناتج المحلي" lines={oman('imf.NGDPDPC', 'عُمان')} from="2005" imfForecast />
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
          title="أي بنود الإنفاق تقود الغلاء في عُمان؟"
          subtitle="التضخم السنوي حسب البند، % — صندوق النقد"
          lines={[
            { indicator: 'imf.cpi_yoy', entity: 'OMN', label: 'العام' },
            { indicator: 'imf.cpi_yoy_food', entity: 'OMN', label: 'الغذاء' },
            { indicator: 'imf.cpi_yoy_housing', entity: 'OMN', label: 'السكن والمرافق' },
            { indicator: 'imf.cpi_yoy_transport', entity: 'OMN', label: 'النقل' },
          ]}
          from="2021"
        />
        <SeriesChart title="الرقم القياسي لأسعار المستهلك (2018=100)" lines={[{ indicator: 'ncsi.CPI_INDEX', entity: 'OMN', label: 'الرقم القياسي' }]} />
        <SeriesChart title="تضخم أسعار المنتج" subtitle="% سنويًا، فصلي" lines={[{ indicator: 'ncsi.PPI_INFLATION', entity: 'OMN', label: 'تضخم أسعار المنتج' }]} />
        <SeriesChart title="الرقم القياسي لأسعار المنتج (2018=100)" lines={[{ indicator: 'ncsi.PPI_INDEX', entity: 'OMN', label: 'أسعار المنتج' }]} />
        <SeriesChart title="التضخم السنوي (متوسط)" lines={oman('imf.PCPIPCH', 'عُمان')} from="2005" imfForecast />
      </Section>

      <Section id="fiscal" title="المالية العامة">
        <SeriesChart oil title="إجمالي الدين الحكومي" lines={oman('imf.GGXWDG_NGDP', 'عُمان')} from="2005" imfForecast />
        <SeriesChart oil title="رصيد الميزانية الحكومية" lines={oman('imf.GGXCNL_NGDP', 'عُمان')} from="2005" imfForecast />
        <SeriesChart title="إيرادات الضرائب" lines={oman('wb.GC.TAX.TOTL.GD.ZS', 'عُمان')} from="2000" />
      </Section>

      <Section id="bop" title="ميزان المدفوعات (البنك المركزي عبر NCSI)" lead="الأرقام الرسمية بالمليون ريال عُماني: من أين تأتي صادرات عُمان (الخام والمشتقات والغاز وغير النفطي)، وما بنود الحساب الجاري؟ المدفوعات بإشارة سالبة.">
        <SeriesChart
          title="الصادرات السلعية حسب النوع"
          subtitle="مليون ريال عُماني"
          kind="stacked"
          lines={[
            { indicator: 'ncsi.BOP_EXPORTS_CRUDE', entity: 'OMN', label: 'النفط الخام', color: 'var(--series-4)' },
            { indicator: 'ncsi.BOP_EXPORTS_REFINED', entity: 'OMN', label: 'المشتقات النفطية', color: 'var(--series-2)' },
            { indicator: 'ncsi.BOP_EXPORTS_GAS', entity: 'OMN', label: 'الغاز', color: 'var(--series-7)' },
            { indicator: 'ncsi.BOP_EXPORTS_NON_OIL', entity: 'OMN', label: 'غير نفطية', color: 'var(--series-3)' },
            { indicator: 'ncsi.BOP_REEXPORTS', entity: 'OMN', label: 'إعادة التصدير', color: 'var(--series-1)' },
          ]}
        />
        <SeriesChart
          title="أرصدة الحساب الجاري"
          subtitle="مليون ريال عُماني"
          kind="columns"
          lines={[
            { indicator: 'ncsi.BOP_GOODS', entity: 'OMN', label: 'السلع', color: 'var(--series-3)' },
            { indicator: 'ncsi.BOP_SERVICES', entity: 'OMN', label: 'الخدمات', color: 'var(--series-4)' },
            { indicator: 'ncsi.BOP_PRIMARY_INCOME', entity: 'OMN', label: 'الدخل الأولي', color: 'var(--series-7)' },
            { indicator: 'ncsi.BOP_SECONDARY_INCOME', entity: 'OMN', label: 'الدخل الثانوي', color: 'var(--series-8)' },
          ]}
        />
        <SeriesChart oil title="رصيد الحساب الجاري" subtitle="مليون ريال عُماني" kind="columns" lines={[{ indicator: 'ncsi.BOP_CURRENT_ACCOUNT', entity: 'OMN', label: 'الحساب الجاري' }]} />
        <SeriesChart title="تحويلات العاملين الوافدين (سالب = خارج)" subtitle="مليون ريال عُماني" kind="columns" lines={[{ indicator: 'ncsi.BOP_WORKERS_REMITTANCES', entity: 'OMN', label: 'تحويلات العاملين' }]} />
      </Section>

      <Section id="external" title="القطاع الخارجي">
        <SeriesChart oil title="رصيد الحساب الجاري" lines={oman('imf.BCA_NGDPD', 'عُمان')} from="2005" imfForecast />
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
        <SeriesChart oil title="الاحتياطيات الدولية" lines={oman('wb.FI.RES.TOTL.CD', 'عُمان')} from="2000" />
      </Section>

      <Section id="energy" title="النفط والغاز">
        <SeriesChart title="سعر النفط: دبي وبرنت" subtitle="دولار/برميل، متوسط شهري — صندوق النقد (ويقابله البنك الدولي وEIA)" lines={[{ indicator: 'imf.pcps.poildub', entity: 'WLD', label: 'خام دبي (مرجع الخام العُماني)' }, { indicator: 'imf.pcps.poilbre', entity: 'WLD', label: 'خام برنت' }]} from="2010" />
        <SeriesChart title="إنتاج وصادرات النفط الخام" subtitle="متوسط يومي" lines={[
          { indicator: 'ncsi.OIL_PRODUCTION', entity: 'OMN', label: 'الإنتاج', color: 'var(--series-1)' },
          { indicator: 'ncsi.OIL_EXPORTS', entity: 'OMN', label: 'الصادرات', color: 'var(--series-2)' },
        ]} />
        <SeriesChart title="إنتاج الغاز الطبيعي" subtitle="شهري" lines={[{ indicator: 'ncsi.GAS_PRODUCTION', entity: 'OMN', label: 'الإنتاج' }]} />
        <SeriesChart title="ريع النفط" lines={oman('wb.NY.GDP.PETR.RT.ZS', 'عُمان')} from="1990" />
      </Section>

      <Section id="people" title="السكان والعمل">
        <SeriesChart
          title="السكان حسب الجنسية (عدّاد المركز الوطني)"
          subtitle="نسمة، شهري"
          lines={[
            { indicator: 'ncsi.POP_OMANI', entity: 'OMN', label: 'العُمانيون', color: 'var(--series-3)' },
            { indicator: 'ncsi.POP_EXPAT', entity: 'OMN', label: 'الوافدون', color: 'var(--series-2)' },
          ]}
          from="2018"
        />
        <SeriesChart title="المواليد (شهري)" lines={[{ indicator: 'ncsi.BIRTHS', entity: 'OMN', label: 'المواليد' }]} from="2018" />
        <SeriesChart title="الوفيات (شهري)" lines={[{ indicator: 'ncsi.DEATHS', entity: 'OMN', label: 'الوفيات' }]} from="2018" />
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
