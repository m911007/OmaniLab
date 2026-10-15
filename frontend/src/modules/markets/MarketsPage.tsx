import { useMemo } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { CountrySelect } from '@/core/components/CountrySelect';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { Loading } from '@/core/components/States';
import { entityName } from '@/core/format';
import { useEntities, useSeries } from '@/core/hooks';
import type { LineSpec } from '@/core/hooks';
import { lastActualYear } from '@/core/lines';
import { TradePartnersCard } from './TradePartnersCard';

const A = lastActualYear();
const DEFAULT = 'CHN';

export default function MarketsPage() {
  const { code: param } = useParams();
  const code = (param ?? DEFAULT).toUpperCase();
  const navigate = useNavigate();
  const { data: entities, isLoading } = useEntities('country', 'wb.NY.GDP.MKTP.CD');
  const entity = entities?.find((e) => e.code === code);
  const name = entity ? entityName(entity) : code;

  const versus = (indicator: string): LineSpec[] =>
    code === 'OMN'
      ? [{ indicator, entity: 'OMN', label: 'عُمان', color: 'var(--series-1)' }]
      : [
          { indicator, entity: code, label: name, color: 'var(--series-2)' },
          { indicator, entity: 'OMN', label: 'عُمان', color: 'var(--series-1)' },
        ];

  return (
    <>
      <PageHead title={`الأسواق العالمية: ${name}`} lead="ملف اقتصادي لأي دولة مقارنةً بعُمان، مع ترتيبها بين دول العالم وتجارتها مع عُمان. المصادر: البنك الدولي وصندوق النقد ومنافذ." />

      <div className="controls">
        {isLoading || !entities ? <Loading height={36} /> : <CountrySelect entities={entities} value={code} onChange={(c) => navigate(`/markets/${c}`)} label="الدولة" />}
      </div>

      <div className="grid cols-4">
        <KeyStat entity={code} indicator="wb.NY.GDP.MKTP.CD" label="الناتج المحلي الإجمالي" compactValue />
        <KeyStat entity={code} indicator="wb.NY.GDP.PCAP.CD" label="نصيب الفرد من الناتج" />
        <KeyStat entity={code} indicator="wb.SP.POP.TOTL" label="عدد السكان" compactValue />
        <KeyStat entity={code} indicator="imf.NGDP_RPCH" label="نمو الناتج الحقيقي" maxPeriod={A} goodWhen="up" />
        <KeyStat entity={code} indicator="wb.FP.CPI.TOTL.ZG" label="التضخم" goodWhen="down" />
        <KeyStat entity={code} indicator="wb.SL.UEM.TOTL.ZS" label="معدل البطالة" goodWhen="down" />
        <KeyStat entity={code} indicator="imf.GGXWDG_NGDP" label="الدين الحكومي" maxPeriod={A} goodWhen="down" />
        <KeyStat entity={code} indicator="wb.IT.NET.USER.ZS" label="مستخدمو الإنترنت" goodWhen="up" />
      </div>

      <Section title={code === 'OMN' ? 'عُمان' : `${name} مقابل عُمان`}>
        <SeriesChart title="نمو الناتج المحلي الإجمالي الحقيقي" lines={versus('imf.NGDP_RPCH')} from="2005" imfForecast />
        <SeriesChart title="نصيب الفرد من الناتج المحلي" lines={versus('wb.NY.GDP.PCAP.CD')} from="1990" />
        <SeriesChart title="التضخم" lines={versus('wb.FP.CPI.TOTL.ZG')} from="1995" />
        <SeriesChart title="الدين الحكومي" lines={versus('imf.GGXWDG_NGDP')} from="2000" imfForecast />
        <SeriesChart title="معدل البطالة" lines={versus('wb.SL.UEM.TOTL.ZS')} from="1995" />
        <SeriesChart title="صادرات السلع والخدمات" lines={versus('wb.NE.EXP.GNFS.ZS')} from="1990" />
      </Section>

      <Section title={`شركاء ${name} التجاريون`} lead="التجارة السلعية بين الدول (WITS / UN Comtrade). التفاصيل الجمركية الرسمية لعُمان في صفحة «التجارة الخارجية».">
        <TradePartnersCard code={code} name={name} />
      </Section>

      {code !== 'OMN' && <TradeWithOman code={code} name={name} />}
    </>
  );
}

/** تجارة الدولة مع عُمان من منافذ؛ لا يظهر القسم إن لم تُسجَّل تجارة. */
function TradeWithOman({ code, name }: { code: string; name: string }) {
  const exp = useSeries('manafeth.EXPORTS_TO', [code]);
  const imp = useSeries('manafeth.IMPORTS_FROM', [code]);
  const re = useSeries('manafeth.REEXPORTS_TO', [code]);
  const loading = exp.isLoading || imp.isLoading || re.isLoading;
  const has = useMemo(() => [exp, imp, re].some((q) => (q.data?.series[0]?.points.length ?? 0) > 0), [exp, imp, re]);

  if (loading || !has) return null;

  return (
    <Section title={`التجارة بين عُمان و${name}`} lead="من الإحصاءات الجمركية (منافذ)، سنوات كاملة، بالريال العُماني.">
      <SeriesChart
        title={`تجارة عُمان مع ${name}`}
        kind="columns"
        lines={[
          { indicator: 'manafeth.EXPORTS_TO', entity: code, label: 'صادرات عُمان إليها', color: 'var(--series-1)' },
          { indicator: 'manafeth.IMPORTS_FROM', entity: code, label: 'واردات عُمان منها', color: 'var(--series-2)' },
          { indicator: 'manafeth.REEXPORTS_TO', entity: code, label: 'إعادة التصدير إليها', color: 'var(--series-3)' },
        ]}
        last={4}
      />
    </Section>
  );
}
