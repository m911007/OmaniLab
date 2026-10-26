import { ReadingGrid } from '@/core/components/ReadingGrid';
import { Link } from 'react-router-dom';
import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { OmanisationCard } from '../labour/OmanisationCard';

const on = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'OMN', label, color });
const H = 340;

const ACTIVITIES: [string, string][] = [
  ['MANUF', 'الصناعات التحويلية'], ['MINING', 'التعدين'], ['CONSTR', 'التشييد'], ['TRADE', 'التجارة'], ['HOSP', 'الإقامة والمطاعم'], ['TRANSP', 'النقل والتخزين'],
  ['ICT', 'الاتصالات والمعلومات'], ['FIN', 'المال والتأمين'], ['REALEST', 'العقارات'], ['EDU', 'التعليم'], ['PROF', 'الأنشطة المهنية'], ['ADMIN', 'الخدمات الإدارية'], ['UTIL', 'الكهرباء والغاز'], ['AGRI', 'الزراعة والأسماك'],
];

/** برامج وأرقام القيمة المحلية المضافة (ICV) كما أعلنتها الجهات أو نقلتها الصحافة. ليست إحصاءات وطنية موحَّدة؛ المصدر والتاريخ في كل صف. */
const PROGRAMMES: { who: string; fact: string; period: string; source: string; url: string }[] = [
  { who: 'تنمية نفط عُمان (PDO)', fact: 'تحتفظ بنحو 42% من إنفاق سلسلة الإمداد محليًا (18% عام 2012) وتستهدف 52% بحلول 2030؛ نحو 2.5 مليار دولار موجّهة للقيمة المحلية في سنة حديثة منها نحو 900 مليون دولار للمنشآت الصغيرة والمتوسطة.', period: 'مقال رأي، يونيو 2026', source: 'Oman Observer — «The ICV dividend»', url: 'https://www.omanobserver.om/article/1191634/opinion/business/the-icv-dividend' },
  { who: 'تنمية نفط عُمان (PDO)', fact: 'برنامج القيمة المحلية يُنسب إليه 83 منشأة تصنيع وأكثر من 17,500 وظيفة للعُمانيين وأثر اقتصادي تراكمي يُقدَّر بـ4.3 مليار دولار، و15 برنامجًا لتطوير الموردين.', period: 'مقال رأي، يونيو 2026', source: 'Oman Observer — «The ICV dividend»', url: 'https://www.omanobserver.om/article/1191634/opinion/business/the-icv-dividend' },
  { who: 'أوكيو (OQ) — الدقم', fact: 'القيمة المحلية المضافة لأوكيو وشركائها في الدقم تجاوزت 1.57 مليار دولار بين يناير 2024 ويونيو 2025، والإنفاق المحلي أكثر من مليار دولار (64% من الإنفاق الكلي)، منها أكثر من 36 مليون دولار للمنشآت الصغيرة والمتوسطة.', period: 'يناير 2024 – يونيو 2025', source: 'Oman Observer — «The ICV dividend»', url: 'https://www.omanobserver.om/article/1191634/opinion/business/the-icv-dividend' },
  { who: 'مجموعة أوكيو', fact: 'ارتفعت القيمة المحلية المحتفظ بها 24% في 2025 مقارنة بـ2024، وإنفاقها على المنشآت الصغيرة والمتوسطة نحو 117.7 مليون ريال، ونسبة التعمين 88%، وأكثر من 13 ألف مورد مسجّل في بوابة «تَوريد».', period: '2025 (تقرير الاستدامة، مايو 2026)', source: 'الرؤية — «المحتوى المحلي في سلطنة عُمان»', url: 'https://alroya.om/post/394612/' },
  { who: 'جهاز الاستثمار العُماني', fact: 'بلغ إنفاق شركاته التابعة على المنشآت الصغيرة والمتوسطة نحو 285.4 مليون ريال عُماني، وللجهاز نظام لتتبع القيمة المحلية المضافة والتوظيف والتدريب والابتكار في شركاته.', period: '2025', source: 'الرؤية — «المحتوى المحلي في سلطنة عُمان»', url: 'https://alroya.om/post/394612/' },
  { who: 'فولتامب للطاقة', fact: 'مبادرة «وطنها» (مايو 2026) لتوطين عشرة مكوّنات مستوردة تستهدف 30–50 مليون ريال قيمة محلية وعشرة مصانع جديدة ونحو 50 مليون ريال استثمارًا ومئة وظيفة متخصصة؛ سجّلت فيها 84 شركة من ست دول في أسابيع.', period: 'مايو 2026', source: 'Oman Observer — «The ICV dividend»', url: 'https://www.omanobserver.om/article/1191634/opinion/business/the-icv-dividend' },
];

export default function LocalContentPage() {
  return (
    <>
      <PageHead
        title="المحتوى المحلي (القيمة المحلية المضافة)"
        lead="كم يبقى من النشاط الاقتصادي داخل عُمان؟ المحتوى المحلي (ICV) يقيس حصة الوظائف الوطنية والمشتريات من الموردين المحليين والتصنيع والمنشآت الصغيرة والمتوسطة والتوطين التقني في المشاريع، ويشكّل ركنًا في رؤية 2040. لا يوجد مؤشر وطني موحّد منشور لنسبة المحتوى المحلي، لذلك تجمع هذه الصفحة أدق ما هو متاح: التعمين حسب النشاط والتصنيع والمنشآت والغذاء والطاقة من إحصاءات رسمية، ثم أرقام برامج الشركات الكبرى كما أعلنتها (بمصدرها وتاريخها)."
      />

      <ReadingGrid>
        <KeyStat entity="OMN" indicator="ncsi.OMNS_ALL_PCT" label="نسبة العُمانيين في القطاع الخاص (%)" goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.OMNS_MANUF_PCT" label="نسبة العُمانيين في الصناعات التحويلية (%)" goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.EST_MANUFACTURING" label="منشآت الصناعة التحويلية" compactValue />
        <KeyStat entity="OMN" indicator="ncsi.EST_MANUF_WORKERS" label="العاملون في الصناعة التحويلية" compactValue />
        <KeyStat entity="OMN" indicator="ncsi.BOP_EXPORTS_NON_OIL" label="الصادرات غير النفطية (مليون ر.ع)" compactValue goodWhen="up" />
        <KeyStat entity="OMN" indicator="wb.NV.IND.MANF.ZS" label="القيمة المضافة للتصنيع (% من الناتج)" goodWhen="up" />
        <KeyStat entity="OMN" indicator="startups.platform_count" label="الشركات الناشئة في منصة «عِش»" />
        <KeyStat entity="OMN" indicator="ncsi.EST_TOTAL" label="المنشآت الاقتصادية" compactValue />
      </ReadingGrid>

      <Section id="workforce" title="التعمين: الوظائف الوطنية" lead="حصة العُمانيين من العاملين في القطاع الخاص حسب النشاط الاقتصادي، ومن أين تأتي الفجوة (المركز الوطني للإحصاء — سوق العمل).">
        <SwitchChart wide height={H} title="التعمين في القطاع الخاص حسب النشاط" subtitle="% العُمانيين من العاملين في النشاط" variants={[
          { value: 'all', label: 'الإجمالي', lines: [on('ncsi.OMNS_ALL_PCT', 'كل الأنشطة')] },
          { value: 'core', label: 'الصناعة والبناء والتعدين', lines: ACTIVITIES.slice(0, 3).map(([c, l]) => on(`ncsi.OMNS_${c}_PCT`, l)) },
          { value: 'services', label: 'التجارة والسياحة والنقل', lines: ACTIVITIES.slice(3, 6).map(([c, l]) => on(`ncsi.OMNS_${c}_PCT`, l)) },
          { value: 'knowledge', label: 'الاتصالات والمال والمهني', lines: [on('ncsi.OMNS_ICT_PCT', 'الاتصالات'), on('ncsi.OMNS_FIN_PCT', 'المال'), on('ncsi.OMNS_PROF_PCT', 'المهني')] },
          { value: 'social', label: 'التعليم والخدمات', lines: [on('ncsi.OMNS_EDU_PCT', 'التعليم'), on('ncsi.OMNS_ADMIN_PCT', 'الخدمات الإدارية'), on('ncsi.OMNS_UTIL_PCT', 'الكهرباء والغاز')] },
        ]} />
        <SwitchChart wide height={H} title="العاملون في القطاع الخاص: عُمانيون ووافدون" variants={[
          { value: 'all', label: 'الإجمالي', subtitle: 'عامل', kind: 'columns', last: 12, lines: [on('ncsi.OMNS_ALL_OMANI', 'عُمانيون', 'var(--series-3)'), on('ncsi.OMNS_ALL_EXPAT', 'وافدون', 'var(--series-2)')] },
          { value: 'manuf', label: 'الصناعة', subtitle: 'عامل', kind: 'columns', last: 12, lines: [on('ncsi.OMNS_MANUF_OMANI', 'عُمانيون', 'var(--series-3)'), on('ncsi.OMNS_MANUF_EXPAT', 'وافدون', 'var(--series-2)')] },
          { value: 'constr', label: 'التشييد', subtitle: 'عامل', kind: 'columns', last: 12, lines: [on('ncsi.OMNS_CONSTR_OMANI', 'عُمانيون', 'var(--series-3)'), on('ncsi.OMNS_CONSTR_EXPAT', 'وافدون', 'var(--series-2)')] },
          { value: 'trade', label: 'التجارة', subtitle: 'عامل', kind: 'columns', last: 12, lines: [on('ncsi.OMNS_TRADE_OMANI', 'عُمانيون', 'var(--series-3)'), on('ncsi.OMNS_TRADE_EXPAT', 'وافدون', 'var(--series-2)')] },
          { value: 'ict', label: 'الاتصالات', subtitle: 'عامل', kind: 'columns', last: 12, lines: [on('ncsi.OMNS_ICT_OMANI', 'عُمانيون', 'var(--series-3)'), on('ncsi.OMNS_ICT_EXPAT', 'وافدون', 'var(--series-2)')] },
        ]} />
        <OmanisationCard title="التعمين الإجمالي (كل القطاعات)" />
        <SwitchChart wide height={H} title="سوق العمل للعُمانيين" variants={[
          { value: 'unemp', label: 'بطالة العُمانيين', subtitle: '%', lines: [on('ilo.unemployment_citizen', 'العُمانيون'), on('ilo.unemployment', 'الإجمالي'), on('ilo.unemployment_youth', 'الشباب')] },
          { value: 'lfp', label: 'المشاركة', subtitle: '%', lines: [on('ilo.lfp_citizen', 'العُمانيون'), on('ilo.lfp_noncitizen', 'الوافدون'), on('ilo.lfp_female', 'المرأة')] },
          { value: 'women', label: 'العُمانيات العاملات', subtitle: 'عامل', kind: 'columns', last: 12, lines: [on('ncsi.LAB_FEMALE_OMANI', 'العُمانيات العاملات')] },
          { value: 'wage', label: 'الحد الأدنى للأجر', subtitle: 'ريال شهريًا', lines: [on('ilo.min_wage', 'الحد الأدنى')] },
        ]} />
      </Section>

      <Section id="industry" title="الإنتاج المحلي والتصنيع" lead="هل يتحول النفط والغاز إلى صناعة وصادرات محلية؟ قيمة التصنيع المضافة والصادرات غير النفطية وحجم المنشآت.">
        <SwitchChart wide height={H} title="التصنيع والتنويع" variants={[
          { value: 'manuf-va', label: 'القيمة المضافة للتصنيع', subtitle: '% من الناتج — مقارنة بالخليج', from: '1995', lines: gccLines('wb.NV.IND.MANF.ZS') },
          { value: 'ind-emp', label: 'التوظيف في الصناعة', subtitle: '% من العاملين', from: '1995', lines: gccLines('wb.SL.IND.EMPL.ZS') },
          { value: 'manuf-exp', label: 'صادرات السلع الصناعية', subtitle: '% من صادرات السلع', from: '1995', lines: gccLines('wb.TX.VAL.MANF.ZS.UN') },
          { value: 'hightech', label: 'الصادرات عالية التقنية', subtitle: '% من صادرات المصنوعات', from: '1995', lines: gccLines('wb.TX.VAL.TECH.MF.ZS') },
          { value: 'nonoil', label: 'الصادرات غير النفطية', subtitle: 'مليون ريال — مع إعادة التصدير', kind: 'columns', last: 14, lines: [on('ncsi.BOP_EXPORTS_NON_OIL', 'غير نفطية', 'var(--series-3)'), on('ncsi.BOP_REEXPORTS', 'إعادة التصدير', 'var(--series-1)')] },
          { value: 'mining', label: 'التعدين', subtitle: 'ريع المعادن % من الناتج', from: '1995', lines: gccLines('wb.NY.GDP.MINR.RT.ZS') },
        ]} />
        <SwitchChart wide height={H} title="المنشآت الاقتصادية" variants={[
          { value: 'activity', label: 'حسب النشاط', subtitle: 'منشأة', kind: 'columns', lines: [on('ncsi.EST_MANUFACTURING', 'صناعة تحويلية'), on('ncsi.EST_CONSTRUCTION', 'تشييد'), on('ncsi.EST_TRADE', 'تجارة'), on('ncsi.EST_MINING', 'تعدين'), on('ncsi.EST_AGRI', 'زراعة وأسماك')] },
          { value: 'size', label: 'حسب الحجم', subtitle: 'منشأة', kind: 'columns', lines: [on('ncsi.EST_SIZE_MICRO', 'صغرى'), on('ncsi.EST_SIZE_SMALL', 'صغيرة'), on('ncsi.EST_SIZE_MEDIUM', 'متوسطة'), on('ncsi.EST_SIZE_LARGE', 'كبيرة')] },
          { value: 'size-w', label: 'العاملون حسب حجم المنشأة', subtitle: 'عامل', kind: 'columns', lines: [on('ncsi.EST_SIZE_MICRO_WORKERS', 'صغرى'), on('ncsi.EST_SIZE_SMALL_WORKERS', 'صغيرة'), on('ncsi.EST_SIZE_MEDIUM_WORKERS', 'متوسطة'), on('ncsi.EST_SIZE_LARGE_WORKERS', 'كبيرة')] },
          { value: 'workers', label: 'العاملون حسب النشاط', subtitle: 'عامل', kind: 'columns', lines: [on('ncsi.EST_MANUF_WORKERS', 'صناعة تحويلية'), on('ncsi.EST_MINING_WORKERS', 'تعدين'), on('ncsi.EST_AGRI_WORKERS', 'زراعة وأسماك')] },
        ]} />
        <div className="card" style={{ gridColumn: '1 / -1' }}>
          <h3 className="card-title">الموردون المحليون والمصانع على الخريطة</h3>
          <p className="muted" style={{ margin: '4px 0 10px' }}>المصانع والمناطق الصناعية ومزارع الاستزراع والصناعات الثقيلة المرسومة في الخريطة المفتوحة (تغطية تطوعية)، والشركات الناشئة الوطنية، والشركات المسجّلة.</p>
          <div className="controls">
            <Link to="/places?group=industry" className="chip neutral">المصانع والمناطق الصناعية</Link>
            <Link to="/companies#startups" className="chip neutral">الشركات الناشئة الوطنية</Link>
            <Link to="/companies" className="chip neutral">الشركات العُمانية</Link>
            <Link to="/investment" className="chip neutral">الاستثمار</Link>
            <Link to="/labour" className="chip neutral">سوق العمل والتعمين</Link>
            <Link to="/minerals" className="chip neutral">المعادن والخامات</Link>
          </div>
        </div>
      </Section>

      <Section id="food-energy" title="الغذاء والطاقة المحلية" lead="كم تنتج عُمان مما تستهلك؟ الاعتماد على استيراد الغذاء وحجم الإنتاج الزراعي والسمكي، وحصة الطاقة المتجددة.">
        <SwitchChart wide height={H} title="الاكتفاء الغذائي والإنتاج المحلي" variants={[
          { value: 'cereal', label: 'اعتماد الحبوب على الاستيراد', subtitle: '% — مقارنة بالخليج', from: '2000', lines: gccLines('fao.cereal_import_dependency') },
          { value: 'foodtrade', label: 'واردات الغذاء إلى الصادرات', subtitle: '%', from: '2000', lines: gccLines('fao.food_imports_to_exports') },
          { value: 'crops', label: 'إنتاج المحاصيل', subtitle: 'طن', kind: 'columns', lines: [on('ncsi.AGR_CROP_PROD', 'إنتاج المحاصيل')] },
          { value: 'fish', label: 'الأسماك المنزلة', subtitle: 'طن — شهري', kind: 'columns', last: 60, lines: [on('ncsi.FISH_LANDED', 'الأسماك المنزلة')] },
          { value: 'foodsec', label: 'مؤشر الأمن الغذائي', subtitle: 'مؤشر مركّب 0–1', lines: [on('ncsi.FOOD_SECURITY_INDEX', 'المؤشر'), on('ncsi.FOOD_AVAILABILITY', 'التوافر'), on('ncsi.FOOD_AFFORDABILITY', 'القدرة على التحمّل')] },
        ]} />
        <SwitchChart wide height={H} title="الطاقة المحلية" variants={[
          { value: 'ren', label: 'حصة الطاقة الشمسية والرياح', subtitle: '% من توليد الكهرباء', from: '2010', lines: gccLines('ember.share_wind_solar') },
          { value: 'cap', label: 'القدرة الشمسية المركّبة', subtitle: 'جيجاواط', from: '2010', lines: gccLines('ember.capacity_solar') },
          { value: 'irena', label: 'الطاقة المتجددة (عُمان)', subtitle: 'المركز الوطني للإحصاء', lines: [on('ncsi.RE_TOTAL', 'الإجمالي'), on('ncsi.RE_SOLAR', 'شمسية'), on('ncsi.RE_WIND', 'رياح')] },
        ]} />
      </Section>

      <Section id="programmes" title="برامج القيمة المحلية في الشركات الكبرى" lead="أرقام أعلنتها الشركات أو نقلتها الصحافة والمقالات؛ ليست إحصاءات وطنية موحَّدة ولا يُجمع بينها (تعريفات وسنوات مختلفة) ولم نتحقق منها من التقارير الأصلية. المصدر والفترة في كل بند.">
        <div className="card" style={{ gridColumn: '1 / -1' }}>
          <div className="table-scroll">
            <table className="wide" aria-label="برامج القيمة المحلية">
              <thead>
                <tr><th>الجهة</th><th>ما أُعلن</th><th>الفترة</th><th>المصدر</th></tr>
              </thead>
              <tbody>
                {PROGRAMMES.map((p, i) => (
                  <tr key={i}>
                    <td style={{ fontWeight: 700 }}>{p.who}</td>
                    <td style={{ maxWidth: 560 }}>{p.fact}</td>
                    <td>{p.period}</td>
                    <td><a href={p.url} target="_blank" rel="noreferrer noopener">{p.source}</a></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="muted" style={{ marginTop: 10, fontSize: 13 }}>
            السياق الوطني: نمت الصناعة التحويلية 7.2% في 2025 وأسهمت بنحو 3.9 مليار ريال في الناتج، وبلغت الصادرات غير النفطية 6.885 مليار ريال بزيادة 10.5%، وتستهدف رؤية 2040 رفع حصة التصنيع من نحو 5% نحو 14% من الناتج (مقال Oman Observer، يونيو 2026). ويضع جهاز الاستثمار العُماني وهيئة المشاريع والمناقصات والمحتوى المحلي سياسات وأطر قياس القيمة المحلية؛ وتوصي منتديات القيمة المحلية بمؤشر وطني موحّد لم يصدر بعد.
          </p>
        </div>
      </Section>

      <Section id="how" title="كيف تُقرأ هذه الصفحة؟">
        <div className="card" style={{ gridColumn: '1 / -1' }}>
          <ul style={{ margin: 0, paddingInlineStart: 20, lineHeight: 2 }}>
            <li><b>التعمين</b> نسبة العُمانيين من العاملين المسجّلين في القطاع الخاص (تأمينات اجتماعية) حسب النشاط؛ مشتقة من أعداد العُمانيين والوافدين لكل نشاط.</li>
            <li><b>الصناعة والمنشآت</b> إحصاءات رسمية للمركز الوطني للإحصاء والبنك الدولي، تتجدد آليًا مع مصادرها.</li>
            <li><b>أرقام الشركات الكبرى</b> إعلانات منشورة تُحدَّث يدويًا؛ تعامَل مؤشرًا على الاتجاه لا قياسًا وطنيًا قابلًا للمقارنة.</li>
            <li><b>فجوة معلومات:</b> لا تنشر الجهات بيانات وطنية موحّدة عن نسبة المشتريات المحلية (ICV) أو أعداد شهادات القيمة المحلية؛ ستضاف هنا فور نشرها.</li>
          </ul>
        </div>
      </Section>
    </>
  );
}
