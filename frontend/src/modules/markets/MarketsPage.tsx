import { useMemo } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { CountrySelect } from '@/core/components/CountrySelect';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { Loading } from '@/core/components/States';
import { entityName } from '@/core/format';
import { useCountryDirectory, useEntities, useSeries } from '@/core/hooks';
import { Link } from 'react-router-dom';
import { flagOf, incomeLabel, regionLabel } from '@/modules/countries/labels';
import type { LineSpec } from '@/core/hooks';
import { lastActualYear } from '@/core/lines';
import { FirmTable } from '@/modules/firms/WorldFirmsPage';
import { useFirms } from '@/core/hooks';
import { AllIndicators } from './AllIndicators';
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
  const directory = useCountryDirectory();
  const profile = directory.data?.data.find((r) => r.code === code);

  const versus = (indicator: string): LineSpec[] =>
    code === 'OMN'
      ? [{ indicator, entity: 'OMN', label: 'عُمان', color: 'var(--series-1)' }]
      : [
          { indicator, entity: code, label: name, color: 'var(--series-2)' },
          { indicator, entity: 'OMN', label: 'عُمان', color: 'var(--series-1)' },
        ];

  return (
    <>
      <PageHead title={`${flagOf(profile?.iso2 ?? null)} ${name}`} lead="ملف اقتصادي شامل مقارنةً بعُمان، مع ترتيب الدولة بين دول العالم وتجارتها مع عُمان. المصادر: البنك الدولي وصندوق النقد ومنافذ." />
      <p className="no-print"><Link to="/countries">← كل دول العالم</Link></p>
      {profile && (
        <div className="country-meta" aria-label="بطاقة تعريف">
          <span className="chip neutral">{regionLabel(profile.region)}</span>
          <span className="chip neutral">{incomeLabel(profile.income_level)}</span>
          {profile.capital && <span className="chip neutral">العاصمة: {profile.capital}</span>}
          <span className="chip neutral" dir="ltr">{profile.code}{profile.iso2 ? ` · ${profile.iso2}` : ''}</span>
        </div>
      )}

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

      <Section title="المالية العامة والقطاع الخارجي">
        <div className="grid cols-4" style={{ gridColumn: '1 / -1' }}>
          <KeyStat entity={code} indicator="imf.GGXCNL_NGDP" label="رصيد الميزانية" maxPeriod={A} goodWhen="up" />
          <KeyStat entity={code} indicator="imf.BCA_NGDPD" label="الحساب الجاري" maxPeriod={A} goodWhen="up" />
          <KeyStat entity={code} indicator="wb.NE.EXP.GNFS.ZS" label="صادرات السلع والخدمات (% من الناتج)" />
          <KeyStat entity={code} indicator="wb.BX.KLT.DINV.WD.GD.ZS" label="الاستثمار الأجنبي الداخل (% من الناتج)" />
        </div>
      </Section>

      <Section title="السكان والتنمية والرقمنة">
        <div className="grid cols-4" style={{ gridColumn: '1 / -1' }}>
          <KeyStat entity={code} indicator="wb.SP.DYN.LE00.IN" label="متوسط العمر المتوقع" goodWhen="up" />
          <KeyStat entity={code} indicator="undp.hdi" label="مؤشر التنمية البشرية" goodWhen="up" />
          <KeyStat entity={code} indicator="wb.SE.TER.ENRR" label="الالتحاق بالتعليم العالي" goodWhen="up" />
          <KeyStat entity={code} indicator="wb.SP.URB.TOTL.IN.ZS" label="سكان الحضر" />
        </div>
      </Section>

      <Section title={code === 'OMN' ? 'عُمان' : `${name} مقابل عُمان`}>
        <SeriesChart primary={code} title="نمو الناتج المحلي الإجمالي الحقيقي" lines={versus('imf.NGDP_RPCH')} from="2005" imfForecast />
        <SeriesChart primary={code} title="نصيب الفرد من الناتج المحلي" lines={versus('wb.NY.GDP.PCAP.CD')} from="1990" />
        <SeriesChart primary={code} title="التضخم" lines={versus('wb.FP.CPI.TOTL.ZG')} from="1995" />
        <SeriesChart primary={code} title="الدين الحكومي" lines={versus('imf.GGXWDG_NGDP')} from="2000" imfForecast />
        <SeriesChart primary={code} title="معدل البطالة" lines={versus('wb.SL.UEM.TOTL.ZS')} from="1995" />
        <SeriesChart primary={code} title="صادرات السلع والخدمات" lines={versus('wb.NE.EXP.GNFS.ZS')} from="1990" />
        <SeriesChart primary={code} title="رصيد الميزانية" lines={versus('imf.GGXCNL_NGDP')} from="2000" imfForecast />
        <SeriesChart primary={code} title="الحساب الجاري" lines={versus('imf.BCA_NGDPD')} from="2000" imfForecast />
        <SeriesChart primary={code} title="مستخدمو الإنترنت" lines={versus('wb.IT.NET.USER.ZS')} from="2000" />
        <SeriesChart primary={code} title="متوسط العمر المتوقع" lines={versus('wb.SP.DYN.LE00.IN')} from="1990" />
      </Section>

      <Section title={`شركاء ${name} التجاريون`} lead="التجارة السلعية بين الدول (WITS / UN Comtrade). التفاصيل الجمركية الرسمية لعُمان في صفحة «التجارة الخارجية».">
        <TradePartnersCard code={code} name={name} />
      </Section>

      {code !== 'OMN' && <TradeWithOman code={code} name={name} />}

      <TopFirms code={code} name={name} />

      <AllIndicators code={code} name={name} />
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

/** أكبر شركات الدولة (بعدد العاملين) من الشركات العالمية؛ لا يظهر القسم إن لم تُجلب شركاتها بعد. */
function TopFirms({ code, name }: { code: string; name: string }) {
  const { data } = useFirms({ country: code, sort: 'employees', page: 1, per_page: 8 });
  if (!data || data.total === 0) return null;
  return (
    <section className="section card">
      <div className="card-head">
        <h2>أكبر شركات {name}</h2>
        <Link to="/world-companies">كل الشركات العالمية ←</Link>
      </div>
      <FirmTable rows={data.data} />
      <p className="source-note">{data.total} شركة موثّقة في ويكيداتا لهذه الدولة، مرتبة بعدد العاملين.</p>
    </section>
  );
}
