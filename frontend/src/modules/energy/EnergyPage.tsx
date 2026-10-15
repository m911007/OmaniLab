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
      <Section title="عُمان في العالم">
        <WorldRankCard title="انبعاثات CO₂ للفرد" indicator="wb.EN.GHG.CO2.PC.CE.AR5" />
        <WorldRankCard title="استهلاك الكهرباء للفرد" indicator="wb.EG.USE.ELEC.KH.PC" />
      </Section>
    </>
  );
}
