import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { lazy, Suspense } from 'react';
import { BrowserRouter, Route, Routes } from 'react-router-dom';
import { Loading } from '@/core/components/States';
import { Shell } from './Shell';

const HomePage = lazy(() => import('@/modules/home/HomePage'));
const OmanEconomyPage = lazy(() => import('@/modules/oman/OmanEconomyPage'));
const GccPage = lazy(() => import('@/modules/gcc/GccPage'));
const PeersPage = lazy(() => import('@/modules/peers/PeersPage'));
const TradePage = lazy(() => import('@/modules/trade/TradePage'));
const MarketsPage = lazy(() => import('@/modules/markets/MarketsPage'));
const CompaniesPage = lazy(() => import('@/modules/companies/CompaniesPage'));
const DevelopmentPage = lazy(() => import('@/modules/development/DevelopmentPage'));
const StatusPage = lazy(() => import('@/modules/status/StatusPage'));
const FiscalPage = lazy(() => import('@/modules/fiscal/FiscalPage'));
const SocietyPage = lazy(() => import('@/modules/society/SocietyPage'));
const TourismPage = lazy(() => import('@/modules/tourism/TourismPage'));
const EnergyPage = lazy(() => import('@/modules/energy/EnergyPage'));
const FinancePage = lazy(() => import('@/modules/finance/FinancePage'));
const InsightsPage = lazy(() => import('@/modules/insights/InsightsPage'));
const CommoditiesPage = lazy(() => import('@/modules/commodities/CommoditiesPage'));
const LabourPage = lazy(() => import('@/modules/labour/LabourPage'));
const IndicesPage = lazy(() => import('@/modules/indices/IndicesPage'));
const IndexDetailPage = lazy(() => import('@/modules/indices/IndexDetailPage'));
const PurchasingPowerPage = lazy(() => import('@/modules/purchasing/PurchasingPowerPage'));
const GovernancePage = lazy(() => import('@/modules/governance/GovernancePage'));

const queryClient = new QueryClient({ defaultOptions: { queries: { retry: 1, refetchOnWindowFocus: false } } });

export function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Suspense fallback={<div style={{ padding: 24 }}><Loading height={300} /></div>}>
          <Routes>
            <Route element={<Shell />}>
              <Route index element={<HomePage />} />
              <Route path="commodities" element={<CommoditiesPage />} />
              <Route path="labour" element={<LabourPage />} />
              <Route path="insights" element={<InsightsPage />} />
              <Route path="indices" element={<IndicesPage />} />
              <Route path="indices/:slug" element={<IndexDetailPage />} />
              <Route path="purchasing-power" element={<PurchasingPowerPage />} />
              <Route path="oman" element={<OmanEconomyPage />} />
              <Route path="gcc" element={<GccPage />} />
              <Route path="peers" element={<PeersPage />} />
              <Route path="fiscal" element={<FiscalPage />} />
              <Route path="society" element={<SocietyPage />} />
              <Route path="tourism" element={<TourismPage />} />
              <Route path="energy" element={<EnergyPage />} />
              <Route path="finance" element={<FinancePage />} />
              <Route path="governance" element={<GovernancePage />} />
              <Route path="development" element={<DevelopmentPage />} />
              <Route path="trade" element={<TradePage />} />
              <Route path="companies" element={<CompaniesPage />} />
              <Route path="markets/:code?" element={<MarketsPage />} />
              <Route path="status" element={<StatusPage />} />
              <Route path="*" element={<NotFound />} />
            </Route>
          </Routes>
        </Suspense>
      </BrowserRouter>
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
