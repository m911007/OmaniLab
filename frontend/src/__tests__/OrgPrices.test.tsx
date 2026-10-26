import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { AuthProvider } from '@/core/auth';
import { OrgPrices } from '@/modules/marketintel/OrgPrices';

afterEach(() => vi.unstubAllGlobals());

function mock(user: object) {
  vi.stubGlobal('fetch', vi.fn(async (url: URL | string) => {
    const u = new URL(String(url), 'http://x');
    const json = (b: unknown) => ({ ok: true, status: 200, json: async () => b }) as Response;
    if (u.pathname.endsWith('/auth/me')) return json({ user });
    if (u.pathname.endsWith('/intel/nodes')) return json({ data: [{ id: 7, name_ar: 'حديد التسليح', unit: 'طن', chain_name: 'حديد التسليح' }] });
    if (u.pathname.endsWith('/intel/org/prices')) return json({ data: [{ id: 1, node_id: 7, node_name: 'حديد التسليح', node_unit: 'طن', chain_name: 'حديد التسليح', product_label: 'حديد 16 مم', spec: null, supplier_name: 'مورد أ', supplier_country: 'OMN', price: '250', currency: 'OMR', unit: 'طن', quantity: '100', incoterm: null, price_date: '2025-01-15', price_omr_per_unit: '250', source: 'manual' }] });
    if (u.pathname.endsWith('/intel/org/benchmark')) return json({ status: 'ok', node: { name_ar: 'حديد التسليح', unit: 'طن' }, paid_omr_per_node_unit: 250, unit_compatible: true, references: [], range: { min: 190, median: 201.86, max: 230, n: 3 }, position: { gap_vs_median: 0.238, label: 'أعلى من نطاق السوق' }, should_cost: { factor: 1.12, should_cost_omr: 280, base_period: '2025-01', covered_weight: 0.6, inputs: [{ input: 'البليت', weight: 0.6, from: '2025-01', to: '2026-09', change: 0.2, series: 'x' }], note: 'n' }, savings_vs_median_omr: 4813.75, supplier_behaviour: null, peer_index: null, computed_at: '2026-10-21T00:00:00Z' });
    return { ok: false, status: 404, json: async () => ({}) } as Response;
  }));
}
const renderIt = () => render(<QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}><AuthProvider><MemoryRouter><OrgPrices /></MemoryRouter></AuthProvider></QueryClientProvider>);

describe('أسعار جهتي', () => {
  it('يشرح الحاجة إلى ربط الحساب بجهة', async () => {
    mock({ id: 1, name: 'م', email: 'a@b', is_admin: false, memberships: [] });
    renderIt();
    expect(await screen.findByText(/حسابك غير مرتبط بجهة/)).toBeInTheDocument();
  });

  it('يعرض أسعار الجهة ويقارن السعر بالسوق والتكلفة المتوقعة', async () => {
    mock({ id: 1, name: 'م', email: 'a@b', is_admin: false, memberships: [{ organization_id: 3, name_ar: 'وزارة', kind: 'government', role: 'purchaser' }] });
    renderIt();
    fireEvent.click(await screen.findByRole('button', { name: 'قارن بالسوق' }));
    expect(await screen.findByText('أعلى من نطاق السوق')).toBeInTheDocument();
    expect(screen.getByText('4,814')).toBeInTheDocument();
    expect(screen.getByText('التكلفة المتوقعة (Should-cost) بمعادلة تعديل السعر')).toBeInTheDocument();
  });
});
