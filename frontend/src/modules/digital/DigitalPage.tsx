import { Link } from 'react-router-dom';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SeriesChart } from '@/core/components/SeriesChart';
import { gccLines, peerLines } from '@/core/groups';
import { oman } from '@/core/lines';
import { IndexCard } from '@/modules/indices/IndicesPage';
import { INDICES } from '@/modules/indices/data';

const DIGITAL = INDICES.filter((i) => i.group === 'digital' || i.group === 'innovation');

export default function DigitalPage() {
  return (
    <>
      <PageHead title="الاقتصاد الرقمي" lead="هل عُمان جاهزة رقميًا؟ الاتصال والإنترنت، والخدمات الحكومية الإلكترونية، والأمن السيبراني، والابتكار والذكاء الاصطناعي، وتجارة خدمات التقنية: مؤشرات رسمية ودولية في مكان واحد." />

      <div className="grid cols-4">
        <KeyStat entity="OMN" indicator="wb.IT.NET.USER.ZS" label="مستخدمو الإنترنت" goodWhen="up" />
        <KeyStat entity="OMN" indicator="wb.IT.CEL.SETS.P2" label="اشتراكات الجوال (لكل 100)" />
        <KeyStat entity="OMN" indicator="wb.IT.NET.BBND.P2" label="النطاق العريض الثابت (لكل 100)" goodWhen="up" />
        <KeyStat entity="OMN" indicator="wb.BX.GSR.CCIS.ZS" label="صادرات خدمات التقنية (% من الخدمات)" goodWhen="up" />
      </div>

      <Section title="الاتصال والإنترنت" lead="القاعدة التي يقوم عليها كل شيء رقمي: من يتصل، وبأي سرعة وجودة، مقارنةً بالخليج.">
        <SeriesChart title="مستخدمو الإنترنت (% من السكان)" lines={peerLines('wb.IT.NET.USER.ZS')} from="2000" />
        <SeriesChart title="اشتراكات النطاق العريض الثابت (لكل 100)" lines={peerLines('wb.IT.NET.BBND.P2')} from="2005" />
        <SeriesChart title="اشتراكات الهاتف المحمول (لكل 100)" lines={peerLines('wb.IT.CEL.SETS.P2')} from="2000" />
        <SeriesChart title="خوادم الإنترنت الآمنة (لكل مليون نسمة)" lines={peerLines('wb.IT.NET.SECR.P6')} from="2010" />
      </Section>

      <Section title="الاتصالات في عُمان (شهري)" lead="مشتركو الهاتف والإنترنت الثابت من هيئة تنظيم الاتصالات عبر المركز الوطني للإحصاء.">
        <SeriesChart title="مشتركو الهاتف المتنقل" lines={[{ indicator: 'ncsi.MOBILE_POSTPAID', entity: 'OMN', label: 'دفع آجل', color: 'var(--series-1)' }, { indicator: 'ncsi.MOBILE_PREPAID', entity: 'OMN', label: 'مسبق الدفع', color: 'var(--series-2)' }]} kind="stacked" last={48} />
        <SeriesChart title="إجمالي مشتركي الهاتف المتنقل" lines={oman('ncsi.MOBILE_SUBSCRIBERS', 'الإجمالي')} />
        <SeriesChart title="مشتركو الإنترنت الثابت" lines={oman('ncsi.FIXED_INTERNET', 'إنترنت ثابت')} />
        <SeriesChart title="خطوط الهاتف الثابت" lines={oman('ncsi.FIXED_LINES', 'هاتف ثابت')} />
      </Section>

      <Section title="تجارة وصناعة التقنية" lead="هل تتحول عُمان من مستهلك للتقنية إلى مصدّر لها؟">
        <SeriesChart title="صادرات خدمات الاتصالات والحاسوب والمعلومات (% من صادرات الخدمات)" lines={peerLines('wb.BX.GSR.CCIS.ZS')} from="2005" />
        <SeriesChart title="صادرات خدمات التقنية (بالدولار)" lines={gccLines('wb.BX.GSR.CCIS.CD')} from="2005" />
        <SeriesChart title="صادرات سلع تقنية المعلومات (% من صادرات السلع)" lines={gccLines('wb.TX.VAL.ICTG.ZS.UN')} from="2000" />
        <SeriesChart title="واردات سلع تقنية المعلومات (% من واردات السلع)" lines={gccLines('wb.TM.VAL.ICTG.ZS.UN')} from="2000" />
        <SeriesChart title="صادرات التقنية العالية (% من الصادرات الصناعية)" lines={peerLines('wb.TX.VAL.TECH.MF.ZS')} from="2000" />
      </Section>

      <Section title="الابتكار والبحث" lead="القدرة على إنتاج المعرفة: باحثون وبراءات ومنشورات وإنفاق بحثي.">
        <SeriesChart title="الإنفاق على البحث والتطوير (% من الناتج)" lines={peerLines('wb.GB.XPD.RSDV.GD.ZS')} from="2000" />
        <SeriesChart title="الباحثون (لكل مليون نسمة)" lines={peerLines('wb.SP.POP.SCIE.RD.P6')} from="2000" />
        <SeriesChart title="طلبات البراءات (المقيمون)" lines={gccLines('wb.IP.PAT.RESD')} from="2000" />
        <SeriesChart title="المقالات العلمية والتقنية" lines={gccLines('wb.IP.JRN.ARTC.SC')} from="2000" />
      </Section>

      <section className="section" aria-labelledby="dig-idx">
        <div className="card-head">
          <h2 id="dig-idx">الجاهزية الرقمية في المؤشرات الدولية</h2>
          <Link to="/indices">كل المؤشرات العالمية ←</Link>
        </div>
        <p className="lead">ترتيب عُمان في مؤشرات الحكومة الإلكترونية والبنية الاتصالية والأمن السيبراني والذكاء الاصطناعي والابتكار.</p>
        <div className="grid cols-2 gidx-grid">
          {DIGITAL.map((i) => (
            <IndexCard key={i.slug} i={i} />
          ))}
        </div>
      </section>
    </>
  );
}
