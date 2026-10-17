import { useMemo, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SourceNote } from '@/core/components/SourceNote';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { gccLines, peerLines } from '@/core/groups';
import { oman } from '@/core/lines';
import { usePlaceStats, usePlaces } from '@/core/hooks';

const GROUPS = [
  { id: 'government', label: 'المؤسسات الحكومية', lead: 'الدوائر والمقار والمحاكم والشرطة والدفاع المدني والبريد والسفارات، كما هي مسجّلة في الخريطة المفتوحة، مع مؤشرات جودة الحوكمة والإنفاق الحكومي.' },
  { id: 'education', label: 'المؤسسات التعليمية', lead: 'الجامعات والكليات والمدارس ورياض الأطفال والمكتبات ومراكز البحث، ومعها مؤشرات الالتحاق والإنفاق والمخرجات.' },
  { id: 'health', label: 'المؤسسات الصحية', lead: 'المستشفيات والمراكز الصحية، ومعها مؤشرات القدرة الصحية والإنفاق.' },
  { id: 'resources', label: 'الموارد والبنية التحتية', lead: 'مرافق النفط والغاز والمحاجر ومحطات الطاقة والتحلية والسدود والموانئ والمطارات والمحميات، ومعها ريوع الموارد والمياه والزراعة والأسماك.' },
] as const;

type GroupId = (typeof GROUPS)[number]['id'];

export default function PlacesPage() {
  const [params, setParams] = useSearchParams();
  const group = (GROUPS.find((g) => g.id === params.get('group'))?.id ?? 'government') as GroupId;
  const [category, setCategory] = useState<string>('');
  const [governorate, setGovernorate] = useState('');
  const [q, setQ] = useState('');
  const [page, setPage] = useState(1);
  const stats = usePlaceStats();
  const meta = GROUPS.find((g) => g.id === group)!;

  const categories = useMemo(() => (stats.data?.categories ?? []).filter((c) => c.group === group), [stats.data, group]);
  const active = categories.find((c) => c.key === category);
  const governorates = useMemo(() => {
    const totals: Record<string, number> = {};
    (active ? [active] : categories).forEach((c) => Object.entries(c.by_governorate).forEach(([g, n]) => (totals[g] = (totals[g] ?? 0) + n)));
    return Object.entries(totals).sort((a, b) => b[1] - a[1]);
  }, [active, categories]);
  const maxGov = Math.max(1, ...governorates.map(([, n]) => n));
  const places = usePlaces({ group: active ? undefined : group, category: category || undefined, governorate: governorate || undefined, q: q || undefined, page });

  const pick = (g: GroupId) => {
    setParams({ group: g });
    setCategory('');
    setGovernorate('');
    setQ('');
    setPage(1);
  };
  const pages = Math.max(1, Math.ceil((places.data?.total ?? 0) / (places.data?.per_page ?? 25)));

  return (
    <>
      <PageHead title="المؤسسات والموارد" lead="أين تقع مؤسسات الدولة التعليمية والحكومية والصحية، وما موارد عُمان وبنيتها التحتية؟ دليل حي من الخريطة المفتوحة (OpenStreetMap) يُحدَّث أسبوعيًا، مدعومًا بالمؤشرات الرسمية من البنك الدولي والمركز الوطني للإحصاء." />

      <div className="toggle" role="tablist" aria-label="نوع المؤسسات" style={{ marginBottom: 14, flexWrap: 'wrap' }}>
        {GROUPS.map((g) => (
          <button key={g.id} role="tab" aria-selected={g.id === group} aria-pressed={g.id === group} onClick={() => pick(g.id)}>
            {g.label}
          </button>
        ))}
      </div>
      <p className="lead">{meta.lead}</p>

      {stats.isLoading && <Loading height={200} />}
      {stats.error && <ErrorState error={stats.error} />}

      {stats.data && (
        <>
          <div className="grid cols-4 insight-tiles">
            {categories.map((c) => (
              <button key={c.key} className="card tile tile-strength" aria-pressed={category === c.key} onClick={() => { setCategory(category === c.key ? '' : c.key); setPage(1); setGovernorate(''); }}>
                <span className="stat-label">{c.ar}</span>
                <span className="stat-value num">{formatNumber(c.count, 0)}</span>
                <span className="stat-meta">مسجَّل في الخريطة · اضغط للتصفية</span>
              </button>
            ))}
          </div>

          <section className="section card">
            <h2>التوزيع حسب المحافظة{active ? ` — ${active.ar}` : ''}</h2>
            {governorates.length === 0 ? (
              <Empty />
            ) : (
              <ul className="gov-bars">
                {governorates.map(([name, n]) => (
                  <li key={name}>
                    <button className={`gov-row${governorate === name ? ' on' : ''}`} onClick={() => { setGovernorate(governorate === name ? '' : name); setPage(1); }} aria-pressed={governorate === name}>
                      <span>{name}</span>
                      <span className="gov-track"><span style={{ width: `${(n / maxGov) * 100}%` }} /></span>
                      <span className="num">{formatNumber(n, 0)}</span>
                    </button>
                  </li>
                ))}
              </ul>
            )}
          </section>

          <section className="section card">
            <div className="card-head" style={{ flexWrap: 'wrap', gap: 10 }}>
              <h2>الدليل{active ? ` — ${active.ar}` : ''}{governorate ? ` — ${governorate}` : ''}</h2>
              <input type="search" placeholder="ابحث بالاسم…" value={q} onChange={(e) => { setQ(e.target.value); setPage(1); }} aria-label="بحث" />
            </div>
            {places.isLoading ? (
              <Loading height={200} />
            ) : (places.data?.data.length ?? 0) === 0 ? (
              <Empty />
            ) : (
              <>
                <div className="table-scroll">
                  <table className="wide">
                    <thead>
                      <tr><th>الاسم</th><th>النوع</th><th>المحافظة</th><th>الجهة</th><th>روابط</th></tr>
                    </thead>
                    <tbody>
                      {places.data!.data.map((p) => (
                        <tr key={p.code}>
                          <td>{p.name_ar ?? p.name_en}{p.name_ar && p.name_en && p.name_en !== p.name_ar && <div className="muted" dir="ltr" style={{ textAlign: 'start' }}>{p.name_en}</div>}</td>
                          <td>{stats.data!.categories.find((c) => c.key === p.category)?.ar ?? p.category}</td>
                          <td>{p.governorate ?? '—'}</td>
                          <td>{p.operator ?? '—'}</td>
                          <td>
                            {p.website && <a href={p.website} target="_blank" rel="noreferrer noopener">الموقع ↗</a>}{' '}
                            {p.osm_url && <a href={p.osm_url} target="_blank" rel="noreferrer noopener">الخريطة ↗</a>}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
                <div className="pager no-print">
                  <button className="btn" disabled={page <= 1} onClick={() => setPage(page - 1)}>السابق</button>
                  <span className="muted">
                    <span className="num">{formatNumber(places.data!.total, 0)}</span> موقعًا · صفحة <span className="num">{page}</span> من <span className="num">{pages}</span>
                  </span>
                  <button className="btn" disabled={page >= pages} onClick={() => setPage(page + 1)}>التالي</button>
                </div>
              </>
            )}
            {stats.data.dataset && <SourceNote datasets={[stats.data.dataset]} note="مصدر تطوعي: تغطيته تتفاوت بين الفئات، فالأعداد هي المسجَّلة في الخريطة المفتوحة وليست إحصاءً رسميًا." />}
          </section>
        </>
      )}

      <Indicators group={group} />
    </>
  );
}

function Indicators({ group }: { group: GroupId }) {
  if (group === 'education') {
    return (
      <Section title="مؤشرات التعليم الرسمية" lead="الالتحاق والإنفاق والمخرجات من البنك الدولي واليونسكو، مقارنةً بدول الخليج.">
        <SeriesChart title="الالتحاق بالتعليم العالي" lines={peerLines('wb.SE.TER.ENRR')} from="1995" />
        <SeriesChart title="الالتحاق بالتعليم الثانوي" lines={peerLines('wb.SE.SEC.ENRR')} from="1995" />
        <SeriesChart title="الإنفاق على التعليم (% من الناتج)" lines={peerLines('wb.SE.XPD.TOTL.GD.ZS')} from="1995" />
        <SeriesChart title="حصة التعليم من الإنفاق الحكومي" lines={peerLines('wb.SE.XPD.TOTL.GB.ZS')} from="1995" />
        <SeriesChart title="العمر المتوقع للتعليم المدرسي" lines={peerLines('wb.SE.SCH.LIFE')} from="2000" />
        <SeriesChart title="مؤشر رأس المال البشري" lines={peerLines('wb.HD.HCI.OVRL')} />
        <SeriesChart title="الحاصلون على بكالوريوس (25+)" lines={peerLines('wb.SE.TER.CUAT.BA.ZS')} from="2000" />
        <SeriesChart title="الإنفاق على البحث والتطوير" lines={peerLines('wb.GB.XPD.RSDV.GD.ZS')} from="2000" />
      </Section>
    );
  }
  if (group === 'government') {
    return (
      <Section title="جودة الحوكمة والإنفاق الحكومي" lead="مؤشرات الحوكمة العالمية (0–100) والحجم النسبي للحكومة.">
        <SeriesChart title="فعالية الحكومة" lines={gccLines('wb.GOV_WGI_GE.SC')} from="2000" />
        <SeriesChart title="جودة التنظيم" lines={gccLines('wb.GOV_WGI_RQ.SC')} from="2000" />
        <SeriesChart title="سيادة القانون" lines={gccLines('wb.GOV_WGI_RL.SC')} from="2000" />
        <SeriesChart title="السيطرة على الفساد" lines={gccLines('wb.GOV_WGI_CC.SC')} from="2000" />
        <SeriesChart title="الاستهلاك الحكومي (% من الناتج)" lines={peerLines('wb.NE.CON.GOVT.ZS')} from="1990" />
        <SeriesChart title="إيرادات الحكومة العامة (% من الناتج)" lines={peerLines('imf.GGR_G01_GDP_PT')} from="2000" imfForecast />
      </Section>
    );
  }
  if (group === 'health') {
    return (
      <Section title="مؤشرات الصحة الرسمية">
        <SeriesChart title="الأطباء لكل 10 آلاف نسمة" lines={peerLines('who.doctors')} from="2000" />
        <SeriesChart title="التمريض والقبالة لكل 10 آلاف نسمة" lines={peerLines('who.nurses')} from="2000" />
        <SeriesChart title="أسرّة المستشفيات" lines={peerLines('who.hospital_beds')} from="2000" />
        <SeriesChart title="مؤشر تغطية الخدمات الصحية الشاملة" lines={peerLines('who.uhc_index')} from="2000" />
        <SeriesChart title="إنفاق وزارة الصحة" lines={oman('ncsi.HEALTH_MOH_SPENDING', 'إجمالي الإنفاق')} kind="columns" />
        <SeriesChart title="العمر الصحي المتوقع" lines={peerLines('who.hale')} from="2000" />
        <SeriesChart title="وفيات الطرق لكل 100 ألف" lines={peerLines('who.road_deaths')} from="2000" />
        <SeriesChart title="التحصين بالجرعة الثالثة من اللقاح الثلاثي (%)" lines={peerLines('who.dtp3')} from="2000" />
        <SeriesChart title="انتشار ارتفاع ضغط الدم (%)" lines={peerLines('who.hypertension')} from="1995" />
        <SeriesChart title="انتشار التدخين الحالي (%)" lines={peerLines('who.tobacco')} from="2000" />
      </Section>
    );
  }
  return (
    <>
      <Section title="ريوع الموارد الطبيعية" lead="حصة الموارد الطبيعية من الناتج: كلما ارتفعت زاد الاعتماد على المورد الناضب.">
        <SeriesChart title="إجمالي ريوع الموارد الطبيعية (% من الناتج)" lines={peerLines('wb.NY.GDP.TOTL.RT.ZS')} from="1990" />
        <SeriesChart title="ريع النفط (% من الناتج)" lines={peerLines('wb.NY.GDP.PETR.RT.ZS')} from="1990" />
        <SeriesChart title="ريع الغاز الطبيعي (% من الناتج)" lines={peerLines('wb.NY.GDP.NGAS.RT.ZS')} from="1990" />
        <SeriesChart title="ريع المعادن (% من الناتج)" lines={peerLines('wb.NY.GDP.MINR.RT.ZS')} from="1990" />
      </Section>
      <Section title="النفط والغاز: الاحتياطي والإنتاج">
        <SeriesChart title="احتياطي النفط الخام" lines={gccLines('gccstat.CRUDE_RESERVES')} />
        <SeriesChart title="احتياطي الغاز الطبيعي" lines={gccLines('gccstat.GAS_RESERVES')} />
        <SeriesChart title="إنتاج النفط الخام" lines={gccLines('gccstat.CRUDE_PRODUCTION')} />
        <SeriesChart title="إنتاج الغاز الطبيعي" lines={gccLines('gccstat.GAS_PRODUCTION')} />
      </Section>
      <Section title="المياه والزراعة والأسماك" lead="شحّ المياه من أكبر قيود النمو؛ التحلية والزراعة والأسماك مفاتيح الأمن الغذائي والمائي.">
        <SeriesChart title="المياه المحلاة" lines={gccLines('gccstat.DESALINATED_WATER')} />
        <SeriesChart title="الإجهاد المائي (السحب إلى الموارد المتجددة)" lines={peerLines('wb.ER.H2O.FWST.ZS')} from="2000" />
        <SeriesChart title="سحب المياه العذبة حسب القطاع (عُمان)" subtitle="% من إجمالي السحب" lines={[{ indicator: 'wb.ER.H2O.FWAG.ZS', entity: 'OMN', label: 'زراعة' }, { indicator: 'wb.ER.H2O.FWDM.ZS', entity: 'OMN', label: 'منزلي' }, { indicator: 'wb.ER.H2O.FWIN.ZS', entity: 'OMN', label: 'صناعة' }]} from="2000" />
        <SeriesChart title="الأراضي الزراعية (% من مساحة الأرض)" lines={gccLines('wb.AG.LND.AGRI.ZS')} from="1990" />
        <SeriesChart title="إنتاج الأسماك (طن)" lines={gccLines('wb.ER.FSH.PROD.MT')} from="1990" />
        <SeriesChart title="إنتاج الحبوب (طن)" lines={gccLines('wb.AG.PRD.CREL.MT')} from="1990" />
        <SeriesChart title="المناطق المحمية (% من المساحة)" lines={peerLines('wb.ER.PTD.TOTL.ZS')} from="1990" />
      </Section>
    </>
  );
}
