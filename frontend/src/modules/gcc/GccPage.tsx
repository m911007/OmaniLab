import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gcc, lastActualYear } from '@/core/lines';
import { GccSnapshot } from './GccSnapshot';

const A = lastActualYear();

export default function GccPage() {
  return (
    <>
      <PageHead title="دول الخليج" lead="مقارنة عُمان بدول مجلس التعاون: الناتج والأسعار والمالية العامة والعمل والطاقة والسياحة. لون كل دولة ثابت في كل الرسوم." />

      <div className="section" style={{ marginTop: 0 }}>
        <GccSnapshot
          rows={[
            { indicator: 'imf.NGDPD', label: 'الناتج المحلي الإجمالي', maxPeriod: A, better: 'high' },
            { indicator: 'imf.NGDPDPC', label: 'نصيب الفرد من الناتج', maxPeriod: A, better: 'high' },
            { indicator: 'imf.NGDP_RPCH', label: 'نمو الناتج الحقيقي', maxPeriod: A, better: 'high' },
            { indicator: 'imf.PCPIPCH', label: 'التضخم', maxPeriod: A, better: 'low' },
            { indicator: 'imf.GGXWDG_NGDP', label: 'الدين الحكومي', maxPeriod: A, better: 'low' },
            { indicator: 'gccstat.UNEMPLOYMENT', label: 'معدل البطالة', better: 'low' },
            { indicator: 'gccstat.POP', label: 'عدد السكان' },
          ]}
        />
      </div>

      <Section title="الناتج والنمو">
        <SeriesChart title="نمو الناتج المحلي الإجمالي الحقيقي" lines={gcc('imf.NGDP_RPCH')} from="2008" imfForecast />
        <SeriesChart title="نصيب الفرد من الناتج المحلي" lines={gcc('imf.NGDPDPC')} from="2008" imfForecast />
        <SeriesChart title="الناتج المحلي الإجمالي بالأسعار الجارية" lines={gcc('gccstat.GDP_CUR')} />
        <SeriesChart title="التضخم (متوسط سنوي)" lines={gcc('imf.PCPIPCH')} from="2008" imfForecast />
      </Section>

      <Section title="المالية العامة والقطاع الخارجي">
        <SeriesChart title="إجمالي الدين الحكومي" lines={gcc('imf.GGXWDG_NGDP')} from="2008" imfForecast />
        <SeriesChart title="رصيد الميزانية" lines={gcc('imf.GGXCNL_NGDP')} from="2008" imfForecast />
        <SeriesChart title="رصيد الحساب الجاري" lines={gcc('imf.BCA_NGDPD')} from="2008" imfForecast />
        <SeriesChart title="إجمالي صادرات السلع" lines={gcc('gccstat.GOODS_EXPORTS')} />
      </Section>

      <Section title="النقد والائتمان" lead="بيانات شهرية من المركز الإحصائي لدول مجلس التعاون.">
        <SeriesChart title="عرض النقد (M2)" lines={gcc('gccstat.M2')} />
        <SeriesChart title="إجمالي الائتمان المحلي" lines={gcc('gccstat.DOMESTIC_CREDIT')} />
        <SeriesChart title="التضخم الشهري (سنويًا)" lines={gcc('gccstat.INFLATION_M')} />
      </Section>

      <Section title="السكان والعمل">
        <SeriesChart title="عدد السكان" lines={gcc('gccstat.POP')} />
        <SeriesChart title="معدل البطالة" lines={gcc('gccstat.UNEMPLOYMENT')} />
      </Section>

      <Section title="الطاقة والسياحة">
        <SeriesChart title="إنتاج النفط الخام" lines={gcc('gccstat.CRUDE_PRODUCTION')} />
        <SeriesChart title="استهلاك الكهرباء" lines={gcc('gccstat.ELECTRICITY_CONSUMPTION')} />
        <SeriesChart title="عدد السياح القادمين" lines={gcc('gccstat.TOURISTS')} note="توقفت سلسلة السياحة في المصدر عند آخر سنة منشورة." />
        <SeriesChart title="المنشآت الفندقية" lines={gcc('gccstat.HOTELS')} />
      </Section>
    </>
  );
}
