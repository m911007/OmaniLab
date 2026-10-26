import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ChainsPage from '@/modules/chains/ChainsPage';
import { AuthProvider } from '@/core/auth';

afterEach(() => vi.unstubAllGlobals());

const chain = {
  chain: { id: 1, slug: 'rebar', name_ar: 'حديد التسليح', name_en: 'Rebar', description: 'وصف', route_note: 'ملاحظة المسار', status: 'review', version: 1, is_demo: false, updated_at: '2026-10-21T08:00:00Z' },
  nodes: [
    { id: 1, slug: 'billet', name_ar: 'البليت', name_en: 'Billet', kind: 'intermediate', stage: 3, unit: 'طن', hs_codes: ['720711'], notes: null, check: null,
      series: [{ id: 9, role: 'primary', indicator_key: 'ctm.uv_m.720711', entity_code: 'OMN', label_ar: 'قيمة وحدة الاستيراد الجمركية: البليت', price_type: 'customs_unit_value', confidence: 'medium', market: 'عُمان', incoterm: 'CIF', unit: 'دولار/طن', currency: 'USD', frequency: 'M', license_note: null, is_demo: false,
        latest: { period: '2026-06', value: 512.4, updated_at: '2026-10-21T07:00:00Z', dataset: 'comtrade.chain_unit_values', dataset_status: 'fresh', last_success_at: null, points: 30 } }] },
    { id: 2, slug: 'rebar', name_ar: 'حديد التسليح', name_en: 'Rebar', kind: 'final', stage: 4, unit: 'طن', hs_codes: ['721420'], notes: null, series: [], check: null },
  ],
  edges: [{ id: 5, from_node_id: 1, to_node_id: 2, weight: '0.8600', weight_min: '0.8000', weight_max: '0.9000', weight_basis: 'derived', conversion_factor: null, conversion_min: null, conversion_max: null, conversion_unit: null, notes: null, logistics_note: null, updated_at: '',
    sources: [{ id: 1, applies_to: 'weight', title: 'Rebar production cost model', url: 'https://steelonthenet.com/cost-rebar.html', publisher: 'Steelonthenet', year: 2025, kind: 'industry', reliability: 'medium', excerpt: 'بليت 475 من 550' }] }],
};

function mock(user: object | null) {
  vi.stubGlobal('fetch', vi.fn(async (url: URL | string) => {
    const u = new URL(String(url), 'http://x');
    const json = (b: unknown) => ({ ok: true, status: 200, json: async () => b }) as Response;
    if (u.pathname.endsWith('/auth/me')) return json({ user });
    if (u.pathname.endsWith('/intel/chains/rebar')) return json(chain);
    if (u.pathname.endsWith('/analytics')) return json({ final_node_id: 2, final_series: null, nodes: [{ node_id: 1, series: null, volatility: 0.25, trade: { hs4: '7207', year: 2024, total_usd: 3e10, hhi: 1800, exporters: [{ iso: 'RUS', name_ar: 'روسيا', value_usd: 6e9, share: 0.2 }], computed_at: '' }, risk: { score: 30, components: {}, note: '' }, lag: { best_lag: 2, best_corr: 0.6, n: 80, by_lag: [{ lag: 0, corr: 0.2, n: 80 }, { lag: 2, corr: 0.6, n: 80 }] } }] });
    if (u.pathname.endsWith('/history')) return json({ series: {}, points: [['2026-04', 500], ['2026-05', 512]], latest: null });
    if (u.pathname.endsWith('/intel/chains')) return json({ data: [] });
    if (u.pathname.endsWith('/revisions')) return json({ data: [{ id: 1, version: 1, note: 'تحميل أولي', created_at: '2026-10-21T08:00:00Z', editor: null }] });
    return { ok: false, status: 404, json: async () => ({}) } as Response;
  }));
}

const renderAt = () => render(
  <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
    <AuthProvider>
      <MemoryRouter initialEntries={['/market-intel/chains/rebar']}>
        <Routes><Route path="/market-intel/chains/:slug" element={<ChainsPage />} /></Routes>
      </MemoryRouter>
    </AuthProvider>
  </QueryClientProvider>,
);

describe('صفحة سلسلة القيمة', () => {
  it('تعرض الوزن بنطاقه وأساسه ومصدره، والسلسلة السعرية بنوعها وتاريخ جلبها', async () => {
    mock({ id: 1, name: 'م', email: 'a@b', is_admin: false, can_edit_chains: false });
    renderAt();
    fireEvent.click(await screen.findByRole('button', { name: 'الأوزان والمصادر (الإدارة)' }));
    expect(await screen.findByText('86%')).toBeInTheDocument();
    expect(screen.getByText('80%–90%')).toBeInTheDocument();
    expect(screen.getByText('مشتق من مصادر')).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Rebar production cost model' })).toHaveAttribute('href', 'https://steelonthenet.com/cost-rebar.html');
    expect(screen.getByText('قيمة وحدة جمركية')).toBeInTheDocument();
    expect(screen.getByText(/جُلبت/)).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'تعديل' })).not.toBeInTheDocument();
  });

  it('المحلل يرى أزرار التعديل', async () => {
    mock({ id: 1, name: 'م', email: 'a@b', is_admin: false, can_edit_chains: true });
    renderAt();
    fireEvent.click(await screen.findByRole('button', { name: 'الأوزان والمصادر (الإدارة)' }));
    expect(await screen.findByRole('button', { name: 'تعديل' })).toBeInTheDocument();
  });

  it('المستكشف: الرسم وبطاقة العقدة ومحاكاة الصدمة', async () => {
    mock({ id: 1, name: 'م', email: 'a@b', is_admin: false, can_edit_chains: false });
    renderAt();
    expect(await screen.findByRole('group', { name: 'رسم سلسلة حديد التسليح' })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'البليت' }));
    expect(await screen.findByText(/وزنه الفعلي في سعر حديد التسليح/)).toBeInTheDocument();
    expect(await screen.findByText(/روسيا/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'محاكاة الصدمة' }));
    expect(await screen.findByText(/فالأثر المتوقع على سعر/)).toBeInTheDocument();
    expect(screen.getAllByText('+8.6%').length).toBeGreaterThan(0);
  });
});
