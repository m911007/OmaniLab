import { lazy } from 'react';
import { HubPage } from '@/core/components/HubPage';
import { BopSection, ExternalSection, FiscalSection, GrowthSection, MoneySection, OmanOilSection, PeopleSection, StructureSection } from '@/modules/oman/sections';

/*
 * الصفحات الموضوعية الجديدة (المرحلة 2 من خطة «من الأرقام إلى القرار»): كل موضوع في صفحة واحدة بتبويبات،
 * تجمع ما كان مبعثرًا بين /oman وصفحات متداخلة. الروابط القديمة تُحوَّل إليها في App.tsx.
 */

const FiscalPage = lazy(() => import('@/modules/fiscal/FiscalPage'));
const IncomeSourcesPage = lazy(() => import('@/modules/income/IncomeSourcesPage'));
const OmanPricesPage = lazy(() => import('@/modules/omanprices/OmanPricesPage'));
const PurchasingPowerPage = lazy(() => import('@/modules/purchasing/PurchasingPowerPage'));
const FinancePage = lazy(() => import('@/modules/finance/FinancePage'));
const TradePage = lazy(() => import('@/modules/trade/TradePage'));
const SocietyPage = lazy(() => import('@/modules/society/SocietyPage'));
const DevelopmentPage = lazy(() => import('@/modules/development/DevelopmentPage'));
const EnergyPage = lazy(() => import('@/modules/energy/EnergyPage'));
const CountriesPage = lazy(() => import('@/modules/countries/CountriesPage'));
const MarketsPage = lazy(() => import('@/modules/markets/MarketsPage'));
const PricesNowPage = lazy(() => import('@/modules/prices/PricesNowPage'));
const UsMarketsPage = lazy(() => import('@/modules/usmarkets/UsMarketsPage'));
const EuroAreaPage = lazy(() => import('@/modules/euro/EuroAreaPage'));
const LeadingPage = lazy(() => import('@/modules/leading/LeadingPage'));

const MACRO = 'الاقتصاد الكلي';

export function GrowthHub() {
  return (
    <HubPage section={MACRO} title="النمو والناتج" base="/growth" related={[['/impact#oil-budget', 'سلاسل الأثر: النفط والميزانية'], ['/energy', 'الطاقة والنفط'], ['/population', 'السكان'], ['/gcc', 'مقارنة بالخليج']]}
      lead="حجم الاقتصاد وسرعة نموه وما يحرّكه (النفط والسكان)، وهيكله بين القطاعات. افتح «تحليل وارتباط» على أي مؤشر لمعرفة مدى تأثير النفط والسكان فيه."
      readings={['imf.NGDP_RPCH', 'imf.NGDPD', 'imf.NGDPDPC']}
      tabs={[{ slug: 'overview', label: 'النمو والهيكل', render: () => <><GrowthSection /><StructureSection /></> }]} />
  );
}

export function PricesLivingHub() {
  return (
    <HubPage section={MACRO} title="الأسعار والمعيشة" base="/prices-living" related={[['/impact#food-inflation', 'الغذاء العالمي والتضخم'], ['/money', 'النقد والفائدة'], ['/labour', 'الأجور وسوق العمل'], ['/global/now', 'الأسعار العالمية الآن']]}
      lead="قصة واحدة: كم ترتفع الأسعار، وأي البنود تقودها، وماذا يبقى من الدخل بعدها."
      readings={['ncsi.CPI_INFLATION']}
      tabs={[
        { slug: 'inflation', label: 'التضخم والأسعار', render: () => <OmanPricesPage /> },
        { slug: 'purchasing', label: 'القوة الشرائية', render: () => <PurchasingPowerPage /> },
      ]} />
  );
}

export function FiscalHub() {
  return (
    <HubPage section={MACRO} title="المالية العامة" base="/fiscal" related={[['/impact#oil-balance-debt', 'النفط والرصيد والدين'], ['/energy', 'سعر النفط'], ['/commodities', 'حساسية الميزانية للنفط'], ['/gcc', 'الميزانية في الخليج']]}
      lead="الميزانية من طرفيها: من أين تأتي إيرادات الدولة، وأين تُنفق، وكم يبلغ الدين والعجز، ومدى ارتباط ذلك بسعر النفط."
      readings={['imf.GGXCNL_NGDP', 'imf.GGXWDG_NGDP']}
      tabs={[
        { slug: 'overview', label: 'الرصيد والدين', render: () => <FiscalSection /> },
        { slug: 'budget', label: 'الميزانية بالتفصيل', render: () => <FiscalPage /> },
        { slug: 'revenue', label: 'مصادر الدخل', render: () => <IncomeSourcesPage /> },
      ]} />
  );
}

export function MoneyHub() {
  return (
    <HubPage section={MACRO} title="النقد والمصارف" base="/money" related={[['/impact#us-rates-credit', 'الفائدة الأمريكية والائتمان'], ['/global/us', 'السوق الأمريكية'], ['/external', 'الاحتياطيات والحساب الجاري'], ['/investment', 'الاستثمار']]}
      lead="السيولة والائتمان وأسعار الفائدة والاحتياطيات، وسلامة البنوك. الريال مربوط بالدولار فتتبع الفائدة المحلية الأمريكية غالبًا."
      readings={['ncsi.MON_CREDIT_PRIVATE']}
      tabs={[
        { slug: 'monetary', label: 'النقد والاحتياطيات', render: () => <MoneySection /> },
        { slug: 'banks', label: 'القطاع المالي', render: () => <FinancePage /> },
      ]} />
  );
}

export function ExternalHub() {
  return (
    <HubPage section={MACRO} title="القطاع الخارجي" base="/external" related={[['/impact#oil-external', 'النفط والحساب الجاري والاحتياطيات'], ['/world', 'ملفات الدول الشريكة'], ['/maritime', 'الموانئ والملاحة'], ['/local-content', 'المحتوى المحلي']]}
      lead="تعاملات عُمان مع العالم: ميزان المدفوعات والحساب الجاري، والتجارة السلعية وشركاؤها."
      readings={['imf.BCA_NGDPD', 'manafeth.EXPORTS']}
      tabs={[
        { slug: 'bop', label: 'ميزان المدفوعات', render: () => <><BopSection /><ExternalSection /></> },
        { slug: 'trade', label: 'التجارة والشركاء', render: () => <TradePage /> },
      ]} />
  );
}

export function PopulationHub() {
  return (
    <HubPage section="السكان والمجتمع" title="السكان" base="/population" related={[['/labour', 'سوق العمل والتعمين'], ['/human', 'التعليم والصحة'], ['/impact#oil-people-prices', 'الإنفاق والوافدون والأسعار'], ['/growth', 'النمو والناتج']]}
      lead="عدد السكان وتركيبته (مواطنون ووافدون، ذكور وإناث)، والمواليد والوفيات، والعاملون."
      readings={['ncsi.POP_TOTAL', 'ilo.unemployment']}
      tabs={[{ slug: 'overview', label: 'السكان', render: () => <PeopleSection /> }]} />
  );
}

export function HumanHub() {
  return (
    <HubPage section="السكان والمجتمع" title="التعليم والصحة والتنمية" base="/human" related={[['/population', 'السكان'], ['/labour', 'سوق العمل'], ['/indices', 'المؤشرات العالمية'], ['/peers', 'دول المقارنة']]}
      lead="رأس المال البشري: التعليم والصحة ومؤشرات التنمية البشرية مقارنةً بالخليج والعالم."
      tabs={[
        { slug: 'society', label: 'المجتمع والصحة', render: () => <SocietyPage /> },
        { slug: 'development', label: 'التنمية البشرية', render: () => <DevelopmentPage /> },
      ]} />
  );
}

export function EnergyHub() {
  return (
    <HubPage section="القطاعات" title="الطاقة والنفط" base="/energy" related={[['/commodities', 'أسواق النفط والسلع'], ['/fiscal', 'المالية العامة'], ['/impact#oil-budget', 'سلاسل أثر النفط'], ['/minerals', 'المعادن']]}
      lead="نفط عُمان وغازها (السعر والإنتاج والصادرات) ثم قطاع الطاقة والبيئة والمناخ."
      readings={['ncsi.OIL_PRICE']}
      tabs={[
        { slug: 'oman-oil', label: 'نفط وغاز عُمان', render: () => <OmanOilSection /> },
        { slug: 'energy', label: 'الطاقة والبيئة', render: () => <EnergyPage /> },
      ]} />
  );
}

export function WorldHub({ tab }: { tab?: string }) {
  return (
    <HubPage section="عُمان والعالم" title="دول وأسواق العالم" base="/world" related={[['/gcc', 'دول الخليج'], ['/peers', 'دول المقارنة'], ['/external/trade', 'تجارة عُمان وشركاؤها'], ['/world-companies', 'الشركات العالمية']]} tab={tab}
      lead="ملف كل دولة: اقتصادها وتجارتها مع عُمان، ومقارنات بين دول العالم."
      tabs={[
        { slug: 'countries', label: 'دول العالم', render: () => <CountriesPage /> },
        { slug: 'markets', label: 'ملف دولة', render: () => <MarketsPage /> },
      ]} />
  );
}

export function GlobalHub() {
  return (
    <HubPage section="عُمان والعالم" title="الاقتصاد العالمي" base="/global" related={[['/money', 'النقد والفائدة في عُمان'], ['/commodities', 'النفط والسلع'], ['/impact#us-rates-credit', 'الفائدة الأمريكية والائتمان'], ['/prices-living', 'الأسعار والمعيشة']]}
      lead="ما يجري في الاقتصادات الكبرى ويؤثر في عُمان: الأسعار العالمية الآن، والسوق الأمريكية (والريال مربوط بالدولار)، وأوروبا، والمؤشرات الاستشرافية."
      tabs={[
        { slug: 'now', label: 'الأسعار الآن', render: () => <PricesNowPage /> },
        { slug: 'us', label: 'أمريكا', render: () => <UsMarketsPage /> },
        { slug: 'europe', label: 'أوروبا وبريطانيا', render: () => <EuroAreaPage /> },
        { slug: 'leading', label: 'المؤشرات الاستشرافية', render: () => <LeadingPage /> },
      ]} />
  );
}
