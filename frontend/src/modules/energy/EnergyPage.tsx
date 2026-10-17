import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { WorldRankCard } from '@/core/components/WorldRankCard';
import { gccLines, peerLines } from '@/core/groups';

export default function EnergyPage() {
  return (
    <>
      <PageHead title="الطاقة والبيئة" lead="النفط والغاز والكهرباء والطاقة المتجددة والانبعاثات: عُمان مقابل دول الخليج والعالم." />
      <Section title="الإنتاج والاستهلاك">
        <SeriesChart title="إنتاج النفط الخام" lines={gccLines('gccstat.CRUDE_PRODUCTION')} />
        <SeriesChart title="إنتاج الغاز الطبيعي" lines={gccLines('gccstat.GAS_PRODUCTION')} />
        <SeriesChart title="استهلاك الكهرباء" lines={gccLines('gccstat.ELECTRICITY_CONSUMPTION')} />
        <SeriesChart title="استهلاك الكهرباء للفرد" lines={peerLines('wb.EG.USE.ELEC.KH.PC')} from="1995" />
      </Section>
      <Section title="النفط والغاز في عُمان">
        <SeriesChart title="سعر النفط العُماني" subtitle="متوسط شهري" lines={[{ indicator: 'ncsi.OIL_PRICE', entity: 'OMN', label: 'دولار/برميل' }]} from="2010" />
        <SeriesChart title="ريع النفط" lines={peerLines('wb.NY.GDP.PETR.RT.ZS')} from="1995" />
      </Section>
      <Section title="البيئة والتحول">
        <SeriesChart title="انبعاثات CO₂ للفرد" lines={peerLines('wb.EN.GHG.CO2.PC.CE.AR5')} from="1990" />
        <SeriesChart title="الطاقة المتجددة من الاستهلاك النهائي" lines={peerLines('wb.EG.FEC.RNEW.ZS')} from="2000" />
        <SeriesChart title="تلوث الهواء PM2.5" lines={peerLines('wb.EN.ATM.PM25.MC.M3')} from="2000" />
      </Section>
      <Section title="الكهرباء والكفاءة" lead="مزيج توليد الكهرباء وكثافة استخدام الطاقة: أين تقف عُمان من التحول؟">
        <SeriesChart title="الكهرباء من الوقود الأحفوري" lines={peerLines('wb.EG.ELC.FOSL.ZS')} from="1990" />
        <SeriesChart title="الكهرباء المتجددة" lines={peerLines('wb.EG.ELC.RNEW.ZS')} from="1990" />
        <SeriesChart title="الكهرباء من الغاز الطبيعي" lines={peerLines('wb.EG.ELC.NGAS.ZS')} from="1990" />
        <SeriesChart title="كثافة استخدام الطاقة (لكل 1000 دولار من الناتج)" lines={peerLines('wb.EG.USE.COMM.GD.PP.KD')} from="1990" />
      </Section>
      <Section title="الكهرباء في عُمان (هيئة تنظيم الخدمات العامة عبر NCSI)" lead="الإنتاج الشهري، والاستهلاك حسب القطاع، والقدرة المركّبة واشتراكات الكهرباء.">
        <SeriesChart title="إنتاج الكهرباء" subtitle="جيجاواط ساعة، شهري" lines={[{ indicator: 'ncsi.ELEC_PRODUCTION', entity: 'OMN', label: 'الإنتاج' }]} />
        <SeriesChart
          title="الاستهلاك حسب القطاع"
          subtitle="جيجاواط ساعة"
          kind="stacked"
          lines={[
            { indicator: 'ncsi.ELEC_CONSUMPTION_RESIDENTIAL', entity: 'OMN', label: 'سكني', color: 'var(--series-1)' },
            { indicator: 'ncsi.ELEC_CONSUMPTION_COMMERCIAL', entity: 'OMN', label: 'تجاري', color: 'var(--series-2)' },
            { indicator: 'ncsi.ELEC_CONSUMPTION_INDUSTRIAL', entity: 'OMN', label: 'صناعي', color: 'var(--series-3)' },
            { indicator: 'ncsi.ELEC_CONSUMPTION_GOVERNMENT', entity: 'OMN', label: 'حكومي', color: 'var(--series-4)' },
          ]}
        />
        <SeriesChart
          title="إنتاج الطاقة المتجددة (شهري)"
          subtitle="جيجاواط ساعة"
          kind="stacked"
          lines={[
            { indicator: 'ncsi.RE_SOLAR', entity: 'OMN', label: 'الشمس', color: 'var(--series-4)' },
            { indicator: 'ncsi.RE_WIND', entity: 'OMN', label: 'الرياح', color: 'var(--series-1)' },
          ]}
          last={30}
        />
        <SeriesChart title="القدرة المركّبة" subtitle="ميجاواط" lines={[{ indicator: 'ncsi.ELEC_CAPACITY', entity: 'OMN', label: 'القدرة المركّبة' }]} />
        <SeriesChart title="اشتراكات الكهرباء" lines={[{ indicator: 'ncsi.ELEC_CONNECTIONS', entity: 'OMN', label: 'الاشتراكات' }]} />
      </Section>
      <Section title="التحول في الكهرباء حتى 2025" lead="بيانات Ember عبر Our World in Data: أحدث من مصادر البنك الدولي بسنوات.">
        <SeriesChart title="حصة المتجددة من الكهرباء" lines={peerLines('owid.renewable_electricity_share')} from="2005" />
        <SeriesChart title="حصة الشمس من الكهرباء" lines={peerLines('owid.solar_electricity_share')} from="2010" />
        <SeriesChart title="كثافة الكربون في الكهرباء" lines={peerLines('owid.electricity_carbon_intensity')} from="2005" />
        <SeriesChart title="انبعاثات CO₂ للفرد (Global Carbon Project)" subtitle="طن" lines={peerLines('owid.co2_per_capita')} from="1990" />
        <SeriesChart title="إمداد الطاقة للفرد" subtitle="كيلوواط ساعة" lines={peerLines('owid.energy_supply_per_capita')} from="1990" />
        <SeriesChart title="توليد الكهرباء للفرد" lines={peerLines('owid.electricity_generation_per_capita')} from="2005" />
      </Section>
      <Section title="الاستدامة والموارد" lead="المدخرات الصافية المعدَّلة تطرح استنزاف الموارد الطبيعية من الادخار: قيمة سالبة تعني أن الثروة الكلية تتآكل رغم الادخار المحاسبي.">
        <SeriesChart title="المدخرات الصافية المعدَّلة" subtitle="% من الدخل القومي" lines={peerLines('wb.NY.ADJ.SVNG.GN.ZS')} from="1995" />
        <SeriesChart title="استنزاف الموارد الطبيعية" subtitle="% من الدخل القومي" lines={peerLines('wb.NY.ADJ.DRES.GN.ZS')} from="1995" />
        <SeriesChart title="الإجهاد المائي (أكثر من 100% = سحب يفوق المتجدد)" lines={peerLines('wb.ER.H2O.FWST.ZS')} from="1995" />
        <SeriesChart title="الموارد المائية المتجددة للفرد" lines={peerLines('wb.ER.H2O.INTR.PC')} from="1995" />
        <SeriesChart title="مؤشر الإنتاج الغذائي" lines={peerLines('wb.AG.PRD.FOOD.XD')} from="1995" />
        <SeriesChart title="سوء التغذية" lines={peerLines('wb.SN.ITK.DEFC.ZS')} from="2000" />
      </Section>
      <Section title="الجاهزية المناخية" lead="مؤشر نوتردام للتكيف (ND-GAIN) بنسخة صندوق النقد: الهشاشة أمام المناخ والجاهزية للتكيف، بين 0 و1.">
        <SeriesChart title="مؤشر ND-GAIN (الأعلى أفضل)" lines={peerLines('imf.ndgain_index')} from="2000" />
        <SeriesChart title="الهشاشة المناخية (الأعلى أسوأ)" lines={peerLines('imf.ndgain_vulnerability')} from="2000" />
        <SeriesChart title="الجاهزية للتكيف المناخي" lines={peerLines('imf.ndgain_readiness')} from="2000" />
      </Section>
      <Section title="عُمان في العالم">
        <WorldRankCard title="انبعاثات CO₂ للفرد" indicator="wb.EN.GHG.CO2.PC.CE.AR5" />
        <WorldRankCard title="استهلاك الكهرباء للفرد" indicator="wb.EG.USE.ELEC.KH.PC" />
      </Section>
      <Section title="المناخ وأثره على الكهرباء والسياحة" lead="الحرارة تدفع الطلب على التبريد وتقيّد العمل الميداني، وخريف صلالة يصنع موسمها السياحي. مناخ شهري منذ 1991 من إعادة تحليل ERA5 عبر Open-Meteo.">
        <SeriesChart
          title="متوسط درجة الحرارة الشهري"
          subtitle="°م"
          lines={[
            { indicator: 'climate.temp_mean_muscat', entity: 'OMN', label: 'مسقط' },
            { indicator: 'climate.temp_mean_nizwa', entity: 'OMN', label: 'نزوى' },
            { indicator: 'climate.temp_mean_salalah', entity: 'OMN', label: 'صلالة' },
            { indicator: 'climate.temp_mean_duqm', entity: 'OMN', label: 'الدقم' },
          ]}
          from="2015"
        />
        <SeriesChart title="أيام الحرارة 40°م فأكثر في مسقط" subtitle="أيام في الشهر" kind="columns" lines={[{ indicator: 'climate.hot_days_muscat', entity: 'OMN', label: 'أيام ≥ 40°م' }]} last={60} />
        <SeriesChart title="أمطار صلالة (موسم الخريف)" subtitle="ملم في الشهر" kind="columns" lines={[{ indicator: 'climate.precip_salalah', entity: 'OMN', label: 'أمطار صلالة' }]} last={60} />
        <SeriesChart title="أمطار مسقط" subtitle="ملم في الشهر" kind="columns" lines={[{ indicator: 'climate.precip_muscat', entity: 'OMN', label: 'أمطار مسقط' }]} last={60} />
      </Section>
    </>
  );
}
