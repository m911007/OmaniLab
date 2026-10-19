import { KeyStat } from '@/core/components/KeyStat';
import { PageHead, Section } from '@/core/components/PageHead';
import { CreditRatings } from './CreditRatings';
import { Link } from 'react-router-dom';
import { SwitchChart } from '@/core/components/SwitchChart';
import { gccLines } from '@/core/groups';
import { lastActualYear, oman } from '@/core/lines';

const A = lastActualYear();
const on = (indicator: string, label: string, color?: string) => ({ indicator, entity: 'OMN', label, color });
const H = 340;

export default function OmanEconomyPage() {
  return (
    <>
      <PageHead title="الاقتصاد العُماني" lead="الناتج والأسعار والمالية العامة والقطاع الخارجي والطاقة والسكان والاتصالات، من صندوق النقد والبنك الدولي والمركز الوطني للإحصاء ومنافذ." />

      <nav aria-label="أقسام الصفحة" className="controls">
        {[
          ['growth', 'النمو والناتج'],
          ['structure', 'الهيكل والاستثمار'],
          ['prices', 'الأسعار'],
          ['fiscal', 'المالية العامة'],
          ['money', 'النقد والمصارف'],
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
        <KeyStat entity="OMN" indicator="imf.NGDP_RPCH" label="نمو الناتج الحقيقي (%)" maxPeriod={A} goodWhen="up" />
        <KeyStat entity="OMN" indicator="imf.NGDPD" label="الناتج المحلي الإجمالي" maxPeriod={A} />
        <KeyStat entity="OMN" indicator="imf.NGDPDPC" label="نصيب الفرد من الناتج" maxPeriod={A} />
        <KeyStat entity="OMN" indicator="ncsi.CPI_INFLATION" label="التضخم السنوي (%)" goodWhen="down" />
        <KeyStat entity="OMN" indicator="imf.GGXCNL_NGDP" label="رصيد الميزانية (% من الناتج)" maxPeriod={A} goodWhen="up" />
        <KeyStat entity="OMN" indicator="imf.GGXWDG_NGDP" label="الدين الحكومي (% من الناتج)" maxPeriod={A} goodWhen="down" />
        <KeyStat entity="OMN" indicator="imf.BCA_NGDPD" label="رصيد الحساب الجاري (% من الناتج)" maxPeriod={A} goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.MON_RESERVES_FX" label="احتياطيات النقد الأجنبي (مليون ر.ع)" compactValue goodWhen="up" />
        <KeyStat entity="OMN" indicator="ncsi.MON_M2" label="عرض النقد M2 (مليون ر.ع)" compactValue />
        <KeyStat entity="OMN" indicator="ncsi.MON_CREDIT_PRIVATE" label="ائتمان القطاع الخاص (مليون ر.ع)" compactValue />
        <KeyStat entity="OMN" indicator="ncsi.MON_RATE_LENDING_RO" label="سعر الإقراض بالريال (%)" />
        <KeyStat entity="OMN" indicator="gccstat.UNEMPLOYMENT" label="معدل البطالة (%)" goodWhen="down" />
      </div>

      <section className="card" aria-label="أين أبحث أعمق؟" style={{ marginTop: 14 }}>
        <h3 className="card-title">أين أبحث أعمق؟</h3>
        <p className="muted" style={{ margin: '4px 0 10px' }}>صفحات مكمِّلة تفصّل ما يختصره هذا الملخص:</p>
        <div className="controls">
          {[
            ['/income-sources', 'مصادر الدخل: إيرادات الحكومة بالبند'],
            ['/fiscal', 'الميزانية والدين مقارنةً بالخليج'],
            ['/commodities', 'حساسية الميزانية لسعر النفط'],
            ['/oman-prices', 'الأسعار حسب المجموعة والمحافظة'],
            ['/trade', 'التجارة الخارجية والشركاء'],
            ['/finance', 'القطاع المالي والبنوك'],
            ['/labour', 'سوق العمل والتعمين'],
            ['/investment', 'الاستثمار'],
          ].map(([to, label]) => (
            <Link key={to} to={to} className="chip neutral">
              {label}
            </Link>
          ))}
        </div>
      </section>

      <Section id="growth" title="النمو والناتج">
        <SwitchChart wide height={H} title="النمو والناتج المحلي" variants={[
          { value: 'growth', label: 'نمو الناتج الحقيقي', subtitle: '% — صندوق النقد (الخط المتقطع توقعات)', lines: oman('imf.NGDP_RPCH', 'عُمان'), from: '2005', oil: true, imfForecast: true },
          { value: 'gdp', label: 'الناتج الجاري', subtitle: 'مليار دولار — صندوق النقد', lines: oman('imf.NGDPD', 'صندوق النقد'), from: '2005', oil: true, imfForecast: true },
          { value: 'pc', label: 'نصيب الفرد', subtitle: 'دولار — صندوق النقد', lines: oman('imf.NGDPDPC', 'عُمان'), from: '2005', oil: true, imfForecast: true },
          { value: 'quarterly', label: 'الناتج الفصلي', subtitle: 'مليون ريال — المركز الإحصائي الخليجي', kind: 'columns', last: 24, lines: [on('gccstat.GDP_CUR_Q', 'الناتج الفصلي (جارٍ)')] },
          { value: 'ppp', label: 'بتعادل القوة الشرائية', subtitle: 'مليار دولار دولي — صندوق النقد', from: '2005', imfForecast: true, lines: oman('imf.PPPGDP', 'عُمان') },
          { value: 'world', label: 'حصتها من الناتج العالمي', subtitle: '% بتعادل القوة الشرائية', from: '2005', imfForecast: true, lines: oman('imf.PPPSH', 'عُمان') },
          { value: 'ncsi', label: 'الجارية والثابتة', subtitle: 'الحسابات القومية الرسمية — مليون ريال', kind: 'columns', last: 14, lines: [on('ncsi.GDP_CURRENT', 'الأسعار الجارية', 'var(--series-1)'), on('ncsi.GDP_CONSTANT', 'الأسعار الثابتة', 'var(--series-2)')] },
        ]} />
      </Section>

      <Section id="structure" title="الهيكل الاقتصادي والاستثمار" lead="كم تعتمد عُمان على النفط؟ وكيف تتوزع القيمة المضافة بين القطاعات؟ وهل يكفي الادخار لتمويل الاستثمار؟">
        <SwitchChart wide height={H} title="هيكل الاقتصاد والتنويع" variants={[
          { value: 'sectors', label: 'القطاعات', subtitle: '% من الناتج (القيمة المضافة)', kind: 'columns', from: '2000', lines: [on('wb.NV.SRV.TOTL.ZS', 'الخدمات', 'var(--series-1)'), on('wb.NV.IND.TOTL.ZS', 'الصناعة (تشمل النفط)', 'var(--series-2)'), on('wb.NV.AGR.TOTL.ZS', 'الزراعة والأسماك', 'var(--series-3)')] },
          { value: 'manuf', label: 'التصنيع', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NV.IND.MANF.ZS') },
          { value: 'rent', label: 'ريع النفط', subtitle: '% من الناتج', from: '1990', lines: gccLines('wb.NY.GDP.PETR.RT.ZS') },
          { value: 'invest', label: 'الاستثمار والادخار', subtitle: '% من الناتج', from: '2000', lines: [on('wb.NE.GDI.TOTL.ZS', 'تكوين رأس المال', 'var(--series-1)'), on('wb.NY.GNS.ICTR.ZS', 'الادخار الإجمالي', 'var(--series-2)')] },
          { value: 'cons', label: 'الاستهلاك الحكومي', subtitle: '% من الناتج', from: '2000', lines: gccLines('wb.NE.CON.GOVT.ZS') },
        ]} />
      </Section>

      <Section id="prices" title="الأسعار">
        <SwitchChart wide height={H} title="التضخم" variants={[
          { value: 'monthly', label: 'شهري', subtitle: '% سنويًا — المركز الوطني', lines: [on('ncsi.CPI_INFLATION', 'التضخم السنوي')] },
          { value: 'gcc', label: 'مع الخليج', subtitle: '% سنويًا — صندوق النقد', from: '2021', lines: [{ indicator: 'imf.cpi_yoy', entity: 'OMN', label: 'عُمان' }, { indicator: 'imf.cpi_yoy', entity: 'SAU', label: 'السعودية' }, { indicator: 'imf.cpi_yoy', entity: 'ARE', label: 'الإمارات' }, { indicator: 'imf.cpi_yoy', entity: 'QAT', label: 'قطر' }, { indicator: 'imf.cpi_yoy', entity: 'KWT', label: 'الكويت' }, { indicator: 'imf.cpi_yoy', entity: 'BHR', label: 'البحرين' }] },
          { value: 'items', label: 'حسب البند', subtitle: 'أي بنود الإنفاق تقود الغلاء؟ % — صندوق النقد', from: '2021', lines: [{ indicator: 'imf.cpi_yoy', entity: 'OMN', label: 'العام' }, { indicator: 'imf.cpi_yoy_food', entity: 'OMN', label: 'الغذاء' }, { indicator: 'imf.cpi_yoy_housing', entity: 'OMN', label: 'السكن والمرافق' }, { indicator: 'imf.cpi_yoy_transport', entity: 'OMN', label: 'النقل' }] },
          { value: 'avg', label: 'المتوسط السنوي', subtitle: '% — صندوق النقد', from: '2005', imfForecast: true, lines: oman('imf.PCPIPCH', 'عُمان') },
        ]} />
        <SwitchChart wide height={H} title="الأرقام القياسية للأسعار" variants={[
          { value: 'cpi', label: 'المستهلك', subtitle: '2018 = 100', lines: [on('ncsi.CPI_INDEX', 'الرقم القياسي')] },
          { value: 'ppi', label: 'المنتج', subtitle: '2018 = 100', lines: [on('ncsi.PPI_INDEX', 'أسعار المنتج')] },
          { value: 'ppi-inf', label: 'تضخم المنتج', subtitle: '% سنويًا، فصلي', lines: [on('ncsi.PPI_INFLATION', 'تضخم أسعار المنتج')] },
        ]} />
      </Section>

      <CreditRatings />

      <Section id="fiscal" title="المالية العامة">
        <SwitchChart wide height={H} title="المالية العامة" variants={[
          { value: 'debt', label: 'الدين الحكومي', subtitle: '% من الناتج — الخط المتقطع توقعات', lines: oman('imf.GGXWDG_NGDP', 'عُمان'), from: '2005', oil: true, imfForecast: true },
          { value: 'balance', label: 'رصيد الميزانية', subtitle: '% من الناتج', lines: oman('imf.GGXCNL_NGDP', 'عُمان'), from: '2005', oil: true, imfForecast: true },
          { value: 'rev-exp', label: 'الإيرادات والمصروفات', subtitle: '% من الناتج — مرصد المالية العامة (الخط المتقطع توقعات)', from: '2000', imfForecast: true, oil: true, lines: [on('imf.GGR_G01_GDP_PT', 'الإيرادات', 'var(--series-3)'), on('imf.G_X_G01_GDP_PT', 'المصروفات', 'var(--series-2)')] },
          { value: 'primary', label: 'الرصيد الأولي', subtitle: '% من الناتج — قبل الفوائد', from: '2000', imfForecast: true, oil: true, lines: [on('imf.GGXONLB_G01_GDP_PT', 'الرصيد الأولي'), on('imf.GGXCNL_G01_GDP_PT', 'الرصيد الكلي')] },
          { value: 'net-debt', label: 'صافي الدين', subtitle: '% من الناتج — الدين مطروحًا منه الأصول السائلة', from: '2000', imfForecast: true, oil: true, lines: [on('imf.G_XWDG_G01_GDP_PT', 'إجمالي الدين', 'var(--series-2)'), on('imf.GGXWDN_G01_GDP_PT', 'صافي الدين', 'var(--series-1)')] },
          { value: 'interest', label: 'الفوائد والمصروف الأولي', subtitle: '% من الناتج — سلسلة صندوق النقد الطويلة', from: '1990', lines: [on('imf.ie', 'الفوائد المدفوعة', 'var(--series-4)'), on('imf.prim_exp', 'المصروفات الأولية', 'var(--series-1)')] },
          { value: 'peers-debt', label: 'الدين: الخليج', subtitle: '% من الناتج', from: '2005', imfForecast: true, lines: gccLines('imf.GGXWDG_NGDP') },
          { value: 'peers-bal', label: 'الميزانية: الخليج', subtitle: '% من الناتج', from: '2005', imfForecast: true, lines: gccLines('imf.GGXCNL_NGDP') },
          { value: 'private-debt', label: 'دين القطاع الخاص', subtitle: '% من الناتج — قروض وسندات', from: '2000', lines: [on('imf.PVD_LS', 'القطاع الخاص'), on('imf.CG_DEBT_GDP', 'الحكومة المركزية')] },
          { value: 'tax', label: 'إيرادات الضرائب', subtitle: '% من الناتج', lines: oman('wb.GC.TAX.TOTL.GD.ZS', 'عُمان'), from: '2000' },
        ]} />
      </Section>

      <Section id="money" title="النقد والمصارف والاحتياطيات" lead="السيولة والائتمان وأسعار الفائدة المحلية والاحتياطيات الأجنبية للبنك المركزي (بالمليون ريال عُماني، شهري، المركز الوطني للإحصاء) وسلامة القطاع المصرفي.">
        <SwitchChart wide height={H} title="النقد والائتمان" variants={[
          { value: 'm2', label: 'عرض النقد', subtitle: 'مليون ريال', from: '2005', lines: [on('ncsi.MON_M2', 'M2 الواسع', 'var(--series-1)'), on('ncsi.MON_M1', 'M1 الضيق', 'var(--series-2)'), on('ncsi.MON_QUASI_MONEY', 'شبه النقد', 'var(--series-3)')] },
          { value: 'credit', label: 'الائتمان والودائع', subtitle: 'مليون ريال', from: '2005', lines: [on('ncsi.MON_CREDIT_PRIVATE', 'ائتمان القطاع الخاص', 'var(--series-1)'), on('gccstat.BANK_DEPOSITS', 'ودائع البنوك', 'var(--series-2)'), on('gccstat.DOMESTIC_CREDIT', 'الائتمان المحلي', 'var(--series-3)')] },
          { value: 'rates', label: 'أسعار الفائدة', subtitle: '% — الإقراض والإيداع المحلية', from: '2008', lines: [on('ncsi.MON_RATE_LENDING_RO', 'الإقراض بالريال', 'var(--series-1)'), on('ncsi.MON_RATE_DEPOSIT_TIME_RO', 'الودائع الزمنية', 'var(--series-2)'), on('ncsi.MON_RATE_DEPOSIT_TOTAL_RO', 'إجمالي الودائع', 'var(--series-3)')] },
          { value: 'interbank', label: 'بين البنوك والريبو', subtitle: '% — أدوات السياسة النقدية', from: '2008', lines: [on('ncsi.MON_RATE_OVERNIGHT', 'بين البنوك لليلة', 'var(--series-1)'), on('ncsi.MON_RATE_REPO', 'الريبو مع المركزي', 'var(--series-2)')] },
          { value: 'reserves', label: 'الاحتياطيات', subtitle: 'مليون ريال — البنك المركزي', from: '2005', oil: true, lines: [on('ncsi.MON_RESERVES_FX', 'احتياطيات النقد الأجنبي', 'var(--series-1)'), on('ncsi.MON_RESERVES_EX_GOLD', 'الرسمية دون الذهب', 'var(--series-2)'), on('ncsi.MON_CBO_FOREIGN_ASSETS', 'الأصول الأجنبية للمركزي', 'var(--series-3)')] },
          { value: 'nfa', label: 'الأصول الأجنبية والحكومة', subtitle: 'مليون ريال — الجهاز المصرفي', from: '2005', oil: true, lines: [on('ncsi.MON_NET_FOREIGN_ASSETS', 'صافي الأصول الأجنبية', 'var(--series-1)'), on('ncsi.MON_NET_CLAIMS_GOV', 'صافي المطالبات على الحكومة', 'var(--series-2)')] },
          { value: 'neer', label: 'سعر الصرف الفعلي', subtitle: 'مؤشر 1999 = 100 — الريال مربوط بالدولار', from: '2005', lines: [on('ncsi.MON_NEER', 'الفعلي الاسمي')] },
          { value: 'credit-gdp', label: 'الائتمان للقطاع الخاص (% ناتج)', subtitle: '% من الناتج — مقارنة بالخليج', from: '2000', lines: gccLines('wb.FS.AST.PRVT.GD.ZS') },
        ]} />
        <SwitchChart wide height={H} title="سلامة القطاع المصرفي والسوق" variants={[
          { value: 'npl', label: 'القروض المتعثرة', subtitle: '% من القروض', lines: gccLines('wb.GFDD.SI.02') },
          { value: 'cap', label: 'رأس المال', subtitle: '% من الأصول المرجّحة بالمخاطر', lines: gccLines('wb.GFDD.SI.05') },
          { value: 'ldr', label: 'القروض إلى الودائع', subtitle: '%', lines: gccLines('wb.GFDD.SI.04') },
          { value: 'nim', label: 'هامش الفائدة', subtitle: '% — صافي هامش الفائدة', lines: gccLines('wb.GFDD.EI.01') },
          { value: 'z', label: 'مؤشر Z', subtitle: 'بُعد الإعسار — الأعلى أمتن', lines: gccLines('wb.GFDD.SI.01') },
          { value: 'mcap', label: 'القيمة السوقية المدرجة', subtitle: '% من الناتج — بورصة مسقط وغيرها', from: '2000', lines: gccLines('wb.CM.MKT.LCAP.GD.ZS') },
        ]} />
      </Section>

      <Section id="bop" title="ميزان المدفوعات (البنك المركزي عبر NCSI)" lead="الأرقام الرسمية بالمليون ريال عُماني: من أين تأتي صادرات عُمان (الخام والمشتقات والغاز وغير النفطي)، وما بنود الحساب الجاري.">
        <SwitchChart wide height={H} title="ميزان المدفوعات" subtitle="مليون ريال عُماني" variants={[
          { value: 'exports', label: 'الصادرات حسب النوع', kind: 'stacked', lines: [on('ncsi.BOP_EXPORTS_CRUDE', 'النفط الخام', 'var(--series-4)'), on('ncsi.BOP_EXPORTS_REFINED', 'المشتقات النفطية', 'var(--series-2)'), on('ncsi.BOP_EXPORTS_GAS', 'الغاز', 'var(--series-7)'), on('ncsi.BOP_EXPORTS_NON_OIL', 'غير نفطية', 'var(--series-3)'), on('ncsi.BOP_REEXPORTS', 'إعادة التصدير', 'var(--series-1)')] },
          { value: 'items', label: 'بنود الحساب الجاري', kind: 'columns', lines: [on('ncsi.BOP_GOODS', 'السلع', 'var(--series-3)'), on('ncsi.BOP_SERVICES', 'الخدمات', 'var(--series-4)'), on('ncsi.BOP_PRIMARY_INCOME', 'الدخل الأولي', 'var(--series-7)'), on('ncsi.BOP_SECONDARY_INCOME', 'الدخل الثانوي', 'var(--series-8)')] },
          { value: 'ca', label: 'رصيد الحساب الجاري', kind: 'columns', oil: true, lines: [on('ncsi.BOP_CURRENT_ACCOUNT', 'الحساب الجاري')] },
          { value: 'remit', label: 'تحويلات العاملين', subtitle: 'مليون ريال — سالب = خارج', kind: 'columns', lines: [on('ncsi.BOP_WORKERS_REMITTANCES', 'تحويلات العاملين')] },
        ]} />
      </Section>

      <Section id="external" title="القطاع الخارجي">
        <SwitchChart wide height={H} title="القطاع الخارجي" variants={[
          { value: 'ca', label: 'الحساب الجاري', subtitle: '% من الناتج — الخط المتقطع توقعات', lines: oman('imf.BCA_NGDPD', 'عُمان'), from: '2005', oil: true, imfForecast: true },
          { value: 'fdi', label: 'الاستثمار الأجنبي', subtitle: 'الداخل المباشر، % من الناتج', lines: oman('wb.BX.KLT.DINV.WD.GD.ZS', 'عُمان'), from: '2000' },
          { value: 'trade', label: 'الصادرات والواردات', subtitle: 'سلع وخدمات — مليون ريال', kind: 'columns', last: 14, lines: [on('ncsi.EXPORTS_GS', 'الصادرات', 'var(--series-1)'), on('ncsi.IMPORTS_GS', 'الواردات', 'var(--series-2)')] },
          { value: 'reserves', label: 'الاحتياطيات الدولية', subtitle: 'دولار', lines: oman('wb.FI.RES.TOTL.CD', 'عُمان'), from: '2000', oil: true },
          { value: 'iip', label: 'مركز الاستثمار الدولي', subtitle: 'دولار — الأصول مقابل الالتزامات', lines: [on('imf.iip_assets', 'الأصول الأجنبية', 'var(--series-3)'), on('imf.iip_liabilities', 'الالتزامات', 'var(--series-2)'), on('imf.iip_net', 'الصافي', 'var(--series-1)')] },
          { value: 'imts', label: 'التجارة السلعية الشهرية', subtitle: 'دولار — صندوق النقد', from: '2019', lines: [on('imf.imts_exports', 'الصادرات', 'var(--series-3)'), on('imf.imts_imports', 'الواردات', 'var(--series-2)'), on('imf.imts_balance', 'الرصيد', 'var(--series-1)')] },
          { value: 'tot', label: 'معدل التبادل التجاري', subtitle: 'مؤشر — صافي صادرات السلع', lines: oman('imf.ctot_net_exports', 'عُمان') },
          { value: 'goods', label: 'السلع: صادرات وواردات وإعادة تصدير', subtitle: 'المركز الإحصائي الخليجي', kind: 'columns', last: 13, lines: [on('gccstat.GOODS_EXPORTS', 'الصادرات', 'var(--series-3)'), on('gccstat.GOODS_IMPORTS', 'الواردات', 'var(--series-2)'), on('gccstat.RE_EXPORTS', 'إعادة التصدير', 'var(--series-1)')] },
          { value: 'remit', label: 'تحويلات العاملين الواردة', subtitle: 'دولار', from: '2000', lines: gccLines('wb.BX.TRF.PWKR.CD.DT') },
        ]} />
      </Section>

      <Section id="energy" title="النفط والغاز">
        <SwitchChart wide height={H} title="النفط والغاز" variants={[
          { value: 'price', label: 'سعر النفط', subtitle: 'دولار/برميل، متوسط شهري — صندوق النقد (ويقابله البنك الدولي وEIA)', from: '2010', lines: [{ indicator: 'imf.pcps.poildub', entity: 'WLD', label: 'خام دبي (مرجع الخام العُماني)' }, { indicator: 'imf.pcps.poilbre', entity: 'WLD', label: 'خام برنت' }] },
          { value: 'oil', label: 'إنتاج وصادرات الخام', subtitle: 'متوسط يومي', lines: [on('ncsi.OIL_PRODUCTION', 'الإنتاج', 'var(--series-1)'), on('ncsi.OIL_EXPORTS', 'الصادرات', 'var(--series-2)')] },
          { value: 'gas', label: 'إنتاج الغاز', subtitle: 'شهري', lines: [on('ncsi.GAS_PRODUCTION', 'الإنتاج')] },
          { value: 'rent', label: 'ريع النفط', subtitle: '% من الناتج', from: '1990', lines: oman('wb.NY.GDP.PETR.RT.ZS', 'عُمان') },
        ]} />
      </Section>

      <Section id="people" title="السكان والعمل">
        <SwitchChart wide height={H} title="السكان" variants={[
          { value: 'nat', label: 'حسب الجنسية', subtitle: 'نسمة، شهري — عدّاد المركز الوطني', from: '2018', lines: [on('ncsi.POP_OMANI', 'العُمانيون', 'var(--series-3)'), on('ncsi.POP_EXPAT', 'الوافدون', 'var(--series-2)')] },
          { value: 'total', label: 'عدد السكان', subtitle: 'نسمة — المركز الإحصائي الخليجي', lines: [on('gccstat.POP', 'إجمالي السكان', 'var(--series-1)'), on('gccstat.POP_CITIZEN', 'المواطنون', 'var(--series-2)'), on('gccstat.POP_NON_CITIZEN', 'غير المواطنين', 'var(--series-3)')] },
          { value: 'births', label: 'المواليد', subtitle: 'شهري', from: '2018', lines: [on('ncsi.BIRTHS', 'المواليد')] },
          { value: 'deaths', label: 'الوفيات', subtitle: 'شهري', from: '2018', lines: [on('ncsi.DEATHS', 'الوفيات')] },
        ]} />
        <SwitchChart wide height={H} title="العمل والتعليم" variants={[
          { value: 'unemp', label: 'البطالة', subtitle: '%', lines: [on('gccstat.UNEMPLOYMENT', 'عُمان')] },
          { value: 'emp', label: 'العاملون', lines: [on('gccstat.EMPLOYED', 'إجمالي العاملين', 'var(--series-1)'), on('gccstat.EMPLOYED_CITIZEN', 'المواطنون', 'var(--series-2)')] },
          { value: 'uni', label: 'طلبة التعليم العالي', lines: [on('gccstat.STUDENTS_UNI', 'الطلبة')] },
        ]} />
      </Section>

      <Section id="digital" title="الاتصالات">
        <SwitchChart wide height={H} title="الاتصالات" variants={[
          { value: 'mobile', label: 'الهاتف المتنقل', subtitle: 'مشتركون، شهري', lines: [on('ncsi.MOBILE_SUBSCRIBERS', 'الإجمالي', 'var(--series-1)'), on('ncsi.MOBILE_POSTPAID', 'دفع آجل', 'var(--series-2)')] },
          { value: 'internet', label: 'مستخدمو الإنترنت', subtitle: '% من السكان', lines: oman('wb.IT.NET.USER.ZS', 'عُمان'), from: '2005' },
        ]} />
      </Section>
    </>
  );
}
