import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
import { BrowserRouter, Navigate, Route, Routes, useLocation, useParams } from 'react-router-dom';
import { AuthProvider } from '@/core/auth';
import { Loading } from '@/core/components/States';
import { Shell } from './Shell';

const HomePage = lazy(() => import('@/modules/home/HomePage'));
const ImpactPage = lazy(() => import('@/modules/impact/ImpactPage'));
const IndicatorPage = lazy(() => import('@/modules/indicator/IndicatorPage'));
const IndicatorsPage = lazy(() => import('@/modules/indicator/IndicatorsPage'));
const MethodologyPage = lazy(() => import('@/modules/indicator/MethodologyPage'));
const hub = <K extends string>(name: K) => lazy(() => import('@/modules/hubs/hubs').then((m) => ({ default: m[name as keyof typeof m] as React.ComponentType<{ tab?: string }> })));
const GrowthHub = hub('GrowthHub');
const PricesLivingHub = hub('PricesLivingHub');
const FiscalHub = hub('FiscalHub');
const MoneyHub = hub('MoneyHub');
const ExternalHub = hub('ExternalHub');
const TradeHub = hub('TradeHub');
const InvestmentHub = hub('InvestmentHub');
const PopulationHub = hub('PopulationHub');
const HumanHub = hub('HumanHub');
const EnergyHub = hub('EnergyHub');
const WorldHub = hub('WorldHub');
const GlobalHub = hub('GlobalHub');

/** يحوّل رابطًا قديمًا إلى مكانه الجديد مع الحفاظ على قسم الصفحة (#) والاستعلام. */
export function Moved({ to }: { to: string }) {
  const { hash, search } = useLocation();
  const params = useParams();
  const target = to.replace(/:(\w+)/g, (_, k: string) => params[k] ?? '').replace(/\/$/, '');
  return <Navigate replace to={{ pathname: target || '/', hash, search }} />;
}
const GccPage = lazy(() => import('@/modules/gcc/GccPage'));
const PeersPage = lazy(() => import('@/modules/peers/PeersPage'));
const SettingsPage = lazy(() => import('@/modules/settings/SettingsPage'));
const ChainsPage = lazy(() => import('@/modules/chains/ChainsPage'));
const MarketIntelPage = lazy(() => import('@/modules/marketintel/MarketIntelPage'));
const CompaniesPage = lazy(() => import('@/modules/companies/CompaniesPage'));
const StatusPage = lazy(() => import('@/modules/status/StatusPage'));
const TourismPage = lazy(() => import('@/modules/tourism/TourismPage'));
const InsightsPage = lazy(() => import('@/modules/insights/InsightsPage'));
const CommoditiesPage = lazy(() => import('@/modules/commodities/CommoditiesPage'));
const IndicesPage = lazy(() => import('@/modules/indices/IndicesPage'));
const IndexDetailPage = lazy(() => import('@/modules/indices/IndexDetailPage'));
const LoginPage = lazy(() => import('@/modules/account/LoginPage'));
const RequestsPage = lazy(() => import('@/modules/account/RequestsPage'));
const PlacesPage = lazy(() => import('@/modules/places/PlacesPage'));
const DigitalPage = lazy(() => import('@/modules/digital/DigitalPage'));
const WorldFirmsPage = lazy(() => import('@/modules/firms/WorldFirmsPage'));
const FirmDetailPage = lazy(() => import('@/modules/firms/FirmDetailPage'));
const AgriculturePage = lazy(() => import('@/modules/agriculture/AgriculturePage'));
const MineralsPage = lazy(() => import('@/modules/minerals/MineralsPage'));
const MaritimePage = lazy(() => import('@/modules/maritime/MaritimePage'));
const LocalContentPage = lazy(() => import('@/modules/localcontent/LocalContentPage'));
const GovernancePage = lazy(() => import('@/modules/governance/GovernancePage'));

const queryClient = new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } } });

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
      <BrowserRouter>
        <Suspense fallback={<div style={{ padding: 24 }}><Loading height={300} /></div>}>
          <Routes>
            <Route element={<Shell />}>
              <Route index element={<HomePage />} />
              <Route path="commodities" element={<CommoditiesPage />} />
              <Route path="labour" element={<Moved to="/population/labour" />} />
              <Route path="scorecard" element={<InsightsPage />} />
              <Route path="impact" element={<ImpactPage />} />
              <Route path="indicators" element={<IndicatorsPage />} />
              <Route path="indicator/:key" element={<IndicatorPage />} />
              <Route path="methodology" element={<MethodologyPage />} />
              <Route path="insights" element={<Moved to="/scorecard" />} />
              <Route path="indices" element={<IndicesPage />} />
              <Route path="indices/:slug" element={<IndexDetailPage />} />
              <Route path="purchasing-power" element={<Navigate replace to={{ pathname: '/prices-living', hash: '#purchasing' }} />} />
              <Route path="prices-living/purchasing" element={<Navigate replace to={{ pathname: '/prices-living', hash: '#purchasing' }} />} />
              <Route path="investment/:tab?" element={<InvestmentHub />} />
              <Route path="login" element={<LoginPage />} />
              <Route path="requests" element={<RequestsPage />} />
              <Route path="settings" element={<SettingsPage />} />
              <Route path="institutions" element={<PlacesPage />} />
              <Route path="countries" element={<Moved to="/world" />} />
              <Route path="digital" element={<DigitalPage />} />
              <Route path="world-companies" element={<WorldFirmsPage />} />
              <Route path="world-companies/:code" element={<FirmDetailPage />} />
              <Route path="us-markets" element={<Moved to="/global/us" />} />
              <Route path="prices" element={<Moved to="/global" />} />
              <Route path="agriculture" element={<AgriculturePage />} />
              <Route path="oman-prices" element={<Moved to="/prices-living" />} />
              <Route path="euro-area" element={<Moved to="/global/europe" />} />
              <Route path="leading" element={<Moved to="/global/leading" />} />
              <Route path="minerals" element={<MineralsPage />} />
              <Route path="maritime" element={<MaritimePage />} />
              <Route path="local-content" element={<LocalContentPage />} />
              <Route path="market-intel" element={<MarketIntelPage />} />
              <Route path="market-intel/chains" element={<ChainsPage />} />
              <Route path="market-intel/chains/:slug" element={<ChainsPage />} />
              <Route path="income-sources" element={<Moved to="/fiscal/revenue" />} />
              <Route path="oman" element={<Navigate replace to={{ pathname: '/', hash: '#topics' }} />} />
              <Route path="growth/:tab?" element={<GrowthHub />} />
              <Route path="prices-living/:tab?" element={<PricesLivingHub />} />
              <Route path="money/:tab?" element={<MoneyHub />} />
              <Route path="external/trade" element={<Moved to="/trade" />} />
              <Route path="external/:tab?" element={<ExternalHub />} />
              <Route path="population/:tab?" element={<PopulationHub />} />
              <Route path="human/society" element={<Moved to="/human/health" />} />
              <Route path="human/development" element={<Moved to="/human" />} />
              <Route path="human/:tab?" element={<HumanHub />} />
              <Route path="world/markets/:code?" element={<WorldHub tab="markets" />} />
              <Route path="world/:tab?" element={<WorldHub />} />
              <Route path="global/:tab?" element={<GlobalHub />} />
              <Route path="gcc" element={<GccPage />} />
              <Route path="peers" element={<PeersPage />} />
              <Route path="fiscal/budget" element={<Moved to="/fiscal" />} />
              <Route path="fiscal/:tab?" element={<FiscalHub />} />
              <Route path="society" element={<Moved to="/human" />} />
              <Route path="tourism" element={<TourismPage />} />
              <Route path="energy/energy" element={<Moved to="/energy/climate" />} />
              <Route path="energy/:tab?" element={<EnergyHub />} />
              <Route path="finance" element={<Moved to="/money/banks" />} />
              <Route path="governance" element={<GovernancePage />} />
              <Route path="development" element={<Moved to="/human" />} />
              <Route path="trade/:tab?" element={<TradeHub />} />
              <Route path="companies" element={<CompaniesPage />} />
              <Route path="markets/:code?" element={<Moved to="/world/markets/:code" />} />
              <Route path="status" element={<StatusPage />} />
              <Route path="*" element={<NotFound />} />
            </Route>
          </Routes>
        </Suspense>
      </BrowserRouter>
      </AuthProvider>
    </QueryClientProvider>
  );
}

function NotFound() {
  return (
    <div className="state">
      <h2>الصفحة غير موجودة</h2>
      <a href="/">العودة إلى لوحة القيادة</a>
    </div>
  );
}
