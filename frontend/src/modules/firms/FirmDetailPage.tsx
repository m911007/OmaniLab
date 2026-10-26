import { Link, useParams } from 'react-router-dom';
import { PageHead } from '@/core/components/PageHead';
import { SourceNote } from '@/core/components/SourceNote';
import { ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { useFirm } from '@/core/hooks';
import { moneyText } from './WorldFirmsPage';

export default function FirmDetailPage() {
  const { code = '' } = useParams();
  const { data, isLoading, error } = useFirm(code);
  const f = data?.data;

  if (isLoading) return <Loading height={300} />;
  if (error || !f) return <ErrorState error={error ?? new Error('غير موجودة')} />;

  const rows: [string, React.ReactNode][] = [
    ['الدولة', f.country ? <Link to={`/world/markets/${f.country}`}>{f.country}</Link> : '—'],
    ['المقر', f.hq_ar ?? f.hq ?? '—'],
    ['القطاع', (f.industry_ar?.length ? f.industry_ar : f.industry)?.join('، ') ?? '—'],
    ['سنة التأسيس', f.inception ?? '—'],
    ['عدد العاملين', f.employees != null ? formatNumber(f.employees, 0) : '—'],
    ['الإيرادات', moneyText(f.revenue)],
    ['الأصول', moneyText(f.assets)],
    ['القيمة السوقية', moneyText(f.market_cap)],
    ['الشكل القانوني', f.legal_form ?? '—'],
    ['الرئيس التنفيذي', f.ceo ?? '—'],
    ['الشركة الأم', f.parent_ar ?? f.parent ?? '—'],
    ['البورصة والرمز', f.ticker || f.exchange?.length ? `${f.exchange?.join('، ') ?? ''} ${f.ticker ?? ''}`.trim() : '—'],
    ['رمز LEI', f.lei ?? '—'],
  ];

  return (
    <>
      <PageHead title={f.name_ar ?? f.name_en} lead={f.name_ar && f.name_en !== f.name_ar ? f.name_en : undefined} />
      <p className="no-print"><Link to="/world-companies">← كل الشركات العالمية</Link></p>
      <section className="card">
        {f.logo && <img className="firm-logo-lg" src={f.logo} alt="" referrerPolicy="no-referrer" />}
        <dl className="firm-facts">
          {rows.map(([k, v]) => (
            <div key={k}><dt>{k}</dt><dd className="num-inline">{v}</dd></div>
          ))}
        </dl>
        <p className="source-note">
          {f.website && <><a href={f.website} target="_blank" rel="noreferrer noopener">الموقع الرسمي ↗</a>{' · '}</>}
          {f.wikipedia_ar && <><a href={f.wikipedia_ar} target="_blank" rel="noreferrer noopener">ويكيبيديا العربية ↗</a>{' · '}</>}
          {f.wikipedia_en && <><a href={f.wikipedia_en} target="_blank" rel="noreferrer noopener">Wikipedia ↗</a>{' · '}</>}
          {f.wikidata_url && <a href={f.wikidata_url} target="_blank" rel="noreferrer noopener">ويكيداتا ↗</a>}
        </p>
        {data?.dataset && <SourceNote datasets={[data.dataset]} />}
      </section>
    </>
  );
}
