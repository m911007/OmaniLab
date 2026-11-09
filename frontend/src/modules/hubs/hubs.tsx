import { lazy } from 'react';
import { TradeHeadline } from '@/modules/trade/TradeHeadline';
import { PopulationHeadline } from '@/modules/population/PopulationHeadline';
import { EnergyHeadline, InvestmentHeadline, HumanHeadline, ExternalHeadline, GrowthHeadline, MoneyHeadline, PricesHeadline } from './headlines';
import { HubPage } from '@/core/components/HubPage';

/*
 * الصفحات الموضوعية الجديدة (المرحلة 2 من خطة «من الأرقام إلى القرار»): كل موضوع في صفحة واحدة بتبويبات،
 * تجمع ما كان مبعثرًا بين /oman وصفحات متداخلة. الروابط القديمة تُحوَّل إليها في App.tsx.
 */

const FiscalOverviewTab = lazy(() => import('@/modules/fiscal/FiscalOverviewTab'));
const FiscalRevenueTab = lazy(() => import('@/modules/fiscal/FiscalRevenueTab'));
const FiscalSpendingTab = lazy(() => import('@/modules/fiscal/FiscalSpendingTab'));
const FiscalDebtTab = lazy(() => import('@/modules/fiscal/FiscalDebtTab'));
const FiscalGulfTab = lazy(() => import('@/modules/fiscal/FiscalGulfTab'));
const GrowthOverviewTab = lazy(() => import('@/modules/growth/GrowthTabs').then((m) => ({ default: m.GrowthOverviewTab })));
const GrowthDemandTab = lazy(() => import('@/modules/growth/GrowthTabs').then((m) => ({ default: m.GrowthDemandTab })));
const GrowthGulfTab = lazy(() => import('@/modules/growth/GrowthTabs').then((m) => ({ default: m.GrowthGulfTab })));
const ExternalOverviewTab = lazy(() => import('@/modules/external/ExternalTabs').then((m) => ({ default: m.ExternalOverviewTab })));
const ExternalPositionTab = lazy(() => import('@/modules/external/ExternalTabs').then((m) => ({ default: m.ExternalPositionTab })));
const ExternalGulfTab = lazy(() => import('@/modules/external/ExternalTabs').then((m) => ({ default: m.ExternalGulfTab })));
const MoneyOverviewTab = lazy(() => import('@/modules/money/MoneyTabs').then((m) => ({ default: m.MoneyOverviewTab })));
const MoneyDetailTab = lazy(() => import('@/modules/money/MoneyTabs').then((m) => ({ default: m.MoneyDetailTab })));
const MoneyGulfTab = lazy(() => import('@/modules/money/MoneyTabs').then((m) => ({ default: m.MoneyGulfTab })));
const PopulationOverviewTab = lazy(() => import('@/modules/population/PopulationTabs').then((m) => ({ default: m.PopulationOverviewTab })));
const LabourWorkforceTab = lazy(() => import('@/modules/population/PopulationTabs').then((m) => ({ default: m.LabourWorkforceTab })));
const LabourUnemploymentTab = lazy(() => import('@/modules/population/PopulationTabs').then((m) => ({ default: m.LabourUnemploymentTab })));
const LabourWagesTab = lazy(() => import('@/modules/population/PopulationTabs').then((m) => ({ default: m.LabourWagesTab })));
const LabourGulfTab = lazy(() => import('@/modules/population/PopulationTabs').then((m) => ({ default: m.LabourGulfTab })));
const HumanOverviewTab = lazy(() => import('@/modules/human/HumanTabs').then((m) => ({ default: m.HumanOverviewTab })));
const HumanHealthTab = lazy(() => import('@/modules/human/HumanTabs').then((m) => ({ default: m.HumanHealthTab })));
const HumanEducationTab = lazy(() => import('@/modules/human/HumanTabs').then((m) => ({ default: m.HumanEducationTab })));
const HumanWealthTab = lazy(() => import('@/modules/human/HumanTabs').then((m) => ({ default: m.HumanWealthTab })));
const HumanFoodTab = lazy(() => import('@/modules/human/HumanTabs').then((m) => ({ default: m.HumanFoodTab })));
const HumanDemographyTab = lazy(() => import('@/modules/human/HumanTabs').then((m) => ({ default: m.HumanDemographyTab })));
const HumanGulfTab = lazy(() => import('@/modules/human/HumanTabs').then((m) => ({ default: m.HumanGulfTab })));
const TradeOverviewTab = lazy(() => import('@/modules/trade/TradeTabs').then((m) => ({ default: m.TradeOverviewTab })));
const TradeMonthlyTab = lazy(() => import('@/modules/trade/TradeTabs').then((m) => ({ default: m.TradeMonthlyTab })));
const TradeProductsTab = lazy(() => import('@/modules/trade/TradeTabs').then((m) => ({ default: m.TradeProductsTab })));
const TradePartnersTab = lazy(() => import('@/modules/trade/TradeTabs').then((m) => ({ default: m.TradePartnersTab })));
const TradeTransportTab = lazy(() => import('@/modules/trade/TradeTabs').then((m) => ({ default: m.TradeTransportTab })));
const TradeGulfTab = lazy(() => import('@/modules/trade/TradeTabs').then((m) => ({ default: m.TradeGulfTab })));
const InvestmentOverviewTab = lazy(() => import('@/modules/investment/InvestmentTabs').then((m) => ({ default: m.InvestmentOverviewTab })));
const InvestmentFdiTab = lazy(() => import('@/modules/investment/InvestmentTabs').then((m) => ({ default: m.InvestmentFdiTab })));
const InvestmentMarketsTab = lazy(() => import('@/modules/investment/InvestmentTabs').then((m) => ({ default: m.InvestmentMarketsTab })));
const InvestmentClimateTab = lazy(() => import('@/modules/investment/InvestmentTabs').then((m) => ({ default: m.InvestmentClimateTab })));
const InvestmentAbroadTab = lazy(() => import('@/modules/investment/InvestmentTabs').then((m) => ({ default: m.InvestmentAbroadTab })));
const InvestmentGulfTab = lazy(() => import('@/modules/investment/InvestmentTabs').then((m) => ({ default: m.InvestmentGulfTab })));
const EconomySourcesTab = lazy(() => import('@/modules/income/EconomySourcesTab'));
const OmanPricesPage = lazy(() => import('@/modules/omanprices/OmanPricesPage'));
const FinancePage = lazy(() => import('@/modules/finance/FinancePage'));
const EnergyOilTab = lazy(() => import('@/modules/energy/EnergyTabs').then((m) => ({ default: m.EnergyOilTab })));
const EnergyElectricityTab = lazy(() => import('@/modules/energy/EnergyTabs').then((m) => ({ default: m.EnergyElectricityTab })));
const EnergyTransitionTab = lazy(() => import('@/modules/energy/EnergyTabs').then((m) => ({ default: m.EnergyTransitionTab })));
const EnergyClimateTab = lazy(() => import('@/modules/energy/EnergyTabs').then((m) => ({ default: m.EnergyClimateTab })));
const EnergyGulfTab = lazy(() => import('@/modules/energy/EnergyTabs').then((m) => ({ default: m.EnergyGulfTab })));
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
      headline={<GrowthHeadline />}
      tabs={[
        { slug: 'overview', label: 'الصورة العامة', render: () => <GrowthOverviewTab /> },
        { slug: 'demand', label: 'الطلب والاستثمار', render: () => <GrowthDemandTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <GrowthGulfTab /> },
        { slug: 'sources', label: 'مصادر دخل الاقتصاد', render: () => <EconomySourcesTab /> },
      ]} />
  );
}

export function PricesLivingHub() {
  return (
    <HubPage section={MACRO} title="الأسعار والمعيشة" base="/prices-living" related={[['/impact#food-inflation', 'الغذاء العالمي والتضخم'], ['/money', 'النقد والفائدة'], ['/labour', 'الأجور وسوق العمل'], ['/global/now', 'الأسعار العالمية الآن']]}
      lead="قصة واحدة: كم ترتفع الأسعار، وأي البنود والمحافظات تقودها، وماذا يبقى من الدخل بعدها (القوة الشرائية في أسفل الصفحة)."
      headline={<PricesHeadline />}
      tabs={[
        { slug: 'inflation', label: 'التضخم والأسعار', render: () => <OmanPricesPage /> },
      ]} />
  );
}

export function FiscalHub() {
  return (
    <HubPage section={MACRO} title="المالية العامة" base="/fiscal" related={[['/impact#oil-balance-debt', 'النفط والرصيد والدين'], ['/energy', 'سعر النفط'], ['/commodities', 'حساسية الميزانية للنفط'], ['/gcc', 'الميزانية في الخليج']]}
      lead="هل المالية العامة في وضع صحي؟ من أين تأتي إيرادات الدولة وأين تُنفق، وكيف يُموَّل العجز، وما موقع عُمان بين جيرانها — بأرقام وزارة المالية الفعلية."
      tabs={[
        { slug: 'overview', label: 'الصورة العامة', render: () => <FiscalOverviewTab /> },
        { slug: 'revenue', label: 'الإيرادات', render: () => <FiscalRevenueTab /> },
        { slug: 'spending', label: 'الإنفاق', render: () => <FiscalSpendingTab /> },
        { slug: 'debt', label: 'الدين والتمويل', render: () => <FiscalDebtTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <FiscalGulfTab /> },
      ]} />
  );
}

export function MoneyHub() {
  return (
    <HubPage section={MACRO} title="النقد والمصارف" base="/money" related={[['/impact#us-rates-credit', 'الفائدة الأمريكية والائتمان'], ['/global/us', 'السوق الأمريكية'], ['/external', 'الاحتياطيات والحساب الجاري'], ['/investment', 'الاستثمار']]}
      lead="السيولة والائتمان وأسعار الفائدة والاحتياطيات، وسلامة البنوك. الريال مربوط بالدولار فتتبع الفائدة المحلية الأمريكية غالبًا."
      headline={<MoneyHeadline />}
      tabs={[
        { slug: 'overview', label: 'الصورة العامة', render: () => <MoneyOverviewTab /> },
        { slug: 'monetary', label: 'النقد والاحتياطيات', render: () => <MoneyDetailTab /> },
        { slug: 'banks', label: 'القطاع المالي', render: () => <FinancePage /> },
        { slug: 'gulf', label: 'الخليج', render: () => <MoneyGulfTab /> },
      ]} />
  );
}

export function ExternalHub() {
  return (
    <HubPage section={MACRO} title="القطاع الخارجي" base="/external" related={[['/impact#oil-external', 'النفط والحساب الجاري والاحتياطيات'], ['/world', 'ملفات الدول الشريكة'], ['/maritime', 'الموانئ والملاحة'], ['/local-content', 'المحتوى المحلي']]}
      lead="تعاملات عُمان مع العالم: ميزان المدفوعات والحساب الجاري، والاستثمار الدولي والاحتياطيات (التجارة السلعية وشركاؤها في صفحتها)."
      headline={<ExternalHeadline />}
      tabs={[
        { slug: 'bop', label: 'الصورة العامة', render: () => <ExternalOverviewTab /> },
        { slug: 'position', label: 'الاستثمار والاحتياطيات', render: () => <ExternalPositionTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <ExternalGulfTab /> },
      ]} />
  );
}

export function TradeHub() {
  return (
    <HubPage section={MACRO} title="التجارة والشركاء" base="/trade" related={[['/external', 'ميزان المدفوعات'], ['/world', 'ملفات الدول الشريكة'], ['/commodities', 'أسواق النفط والسلع'], ['/impact#oil-external', 'النفط والحساب الجاري']]}
      lead="ماذا تبيع عُمان وماذا تشتري ومع من؟ الميزان التجاري ونمو التجارة، والأرقام الشهرية، وتركيبة المنتجات والتعقيد الاقتصادي، والشركاء ووسائل النقل، ومقارنةً بالخليج."
      headline={<TradeHeadline />}
      tabs={[
        { slug: 'overview', label: 'الصورة العامة', render: () => <TradeOverviewTab /> },
        { slug: 'monthly', label: 'التجارة الشهرية', render: () => <TradeMonthlyTab /> },
        { slug: 'products', label: 'المنتجات والتعقيد', render: () => <TradeProductsTab /> },
        { slug: 'partners', label: 'الشركاء', render: () => <TradePartnersTab /> },
        { slug: 'transport', label: 'النقل والمنافذ', render: () => <TradeTransportTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <TradeGulfTab /> },
      ]} />
  );
}

export function InvestmentHub() {
  return (
    <HubPage section="الأعمال والاستثمار" title="الاستثمار" base="/investment" related={[['/companies', 'الشركات العُمانية'], ['/money', 'النقد والمصارف'], ['/external/position', 'الاستثمار الدولي والاحتياطيات'], ['/institutions', 'المؤسسات']]}
      lead="هل يتدفق المال إلى الاقتصاد العُماني؟ الاستثمار الأجنبي والمحلي والادخار، والأسواق والتمويل، ومناخ الأعمال، واستثمارات عُمان في الخارج، ومقارنةً بالخليج."
      headline={<InvestmentHeadline />}
      tabs={[
        { slug: 'overview', label: 'الصورة العامة', render: () => <InvestmentOverviewTab /> },
        { slug: 'fdi', label: 'الاستثمار الأجنبي', render: () => <InvestmentFdiTab /> },
        { slug: 'markets', label: 'الأسواق والتمويل', render: () => <InvestmentMarketsTab /> },
        { slug: 'climate', label: 'مناخ الأعمال', render: () => <InvestmentClimateTab /> },
        { slug: 'abroad', label: 'الاستثمارات الدولية', render: () => <InvestmentAbroadTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <InvestmentGulfTab /> },
      ]} />
  );
}

export function PopulationHub() {
  return (
    <HubPage section="السكان والمجتمع" title="السكان والعمل" base="/population" related={[['/human', 'التعليم والصحة'], ['/impact#oil-people-prices', 'الإنفاق والوافدون والأسعار'], ['/gcc', 'الخليج'], ['/indices', 'المؤشرات العالمية']]}
      lead="من هم سكان عُمان وكيف يعملون؟ عدد السكان وتركيبتهم، والعاملون بين العُمانيين والوافدين، والتعمين والبطالة والأجور، ومقارنةً بالخليج."
      headline={<PopulationHeadline />}
      tabs={[
        { slug: 'overview', label: 'السكان', render: () => <PopulationOverviewTab /> },
        { slug: 'labour', label: 'التعمين والعاملون', render: () => <LabourWorkforceTab /> },
        { slug: 'unemployment', label: 'البطالة والمشاركة', render: () => <LabourUnemploymentTab /> },
        { slug: 'wages', label: 'الأجور', render: () => <LabourWagesTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <LabourGulfTab /> },
      ]} />
  );
}

export function HumanHub() {
  return (
    <HubPage section="السكان والمجتمع" title="التعليم والصحة والتنمية" base="/human" related={[['/population', 'السكان'], ['/labour', 'سوق العمل'], ['/indices', 'المؤشرات العالمية'], ['/peers', 'دول المقارنة']]}
      lead="رأس المال البشري: التعليم والصحة ومؤشرات التنمية البشرية مقارنةً بالخليج والعالم."
      headline={<HumanHeadline />}
      tabs={[
        { slug: 'overview', label: 'الصورة العامة', render: () => <HumanOverviewTab /> },
        { slug: 'health', label: 'الصحة', render: () => <HumanHealthTab /> },
        { slug: 'education', label: 'التعليم والبحث', render: () => <HumanEducationTab /> },
        { slug: 'wealth', label: 'الثروة والمساواة', render: () => <HumanWealthTab /> },
        { slug: 'food', label: 'الأمن الغذائي', render: () => <HumanFoodTab /> },
        { slug: 'demography', label: 'الديموغرافيا', render: () => <HumanDemographyTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <HumanGulfTab /> },
      ]} />
  );
}

export function EnergyHub() {
  return (
    <HubPage section="القطاعات" title="الطاقة والنفط" base="/energy" related={[['/commodities', 'أسواق النفط والسلع'], ['/fiscal', 'المالية العامة'], ['/impact#oil-budget', 'سلاسل أثر النفط'], ['/minerals', 'المعادن']]}
      lead="نفط عُمان وغازها (السعر والإنتاج والصادرات) ثم قطاع الطاقة والبيئة والمناخ."
      headline={<EnergyHeadline />}
      tabs={[
        { slug: 'oman-oil', label: 'النفط والغاز', render: () => <EnergyOilTab /> },
        { slug: 'electricity', label: 'الكهرباء', render: () => <EnergyElectricityTab /> },
        { slug: 'transition', label: 'التحول والانبعاثات', render: () => <EnergyTransitionTab /> },
        { slug: 'climate', label: 'المناخ والموارد', render: () => <EnergyClimateTab /> },
        { slug: 'gulf', label: 'الخليج', render: () => <EnergyGulfTab /> },
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
