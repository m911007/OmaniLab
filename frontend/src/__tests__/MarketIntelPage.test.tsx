import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import MarketIntelPage from '@/modules/marketintel/MarketIntelPage';

afterEach(() => vi.unstubAllGlobals());

const cand = (iso: string, name: string, score: number, comps: Record<string, number | null>, extra: object = {}) => ({
  iso, name, name_ar: name, value: 5e9, share: 0.2, cagr: 5, unit_value: 12000, price_vs_median: -0.1, uv_series: { '2023': 12500, '2024': 12000 },
  oman_value: 1e8, oman_unit_value: 13000, fta: null, tariff: { tariff: 5, year: 2019 }, components: comps, score, risk: { lpi: 3, stability: 0, inform: 3 }, ...extra,
});

const analysis = {
  signals: [{ level: 'risk', title: 'اعتماد مرتفع على مورد واحد', detail: 'اليابان يورّد 70%', weight: 90 }],
  oman_trend: { '2023': { value: 2e9, qty: 1 }, '2024': { value: 2.1e9, qty: 1 } },
  world_trend: { '2023': { value: 9e11, qty: 1, unit_value: 15000 }, '2024': { value: 9.6e11, qty: 1, unit_value: 16000 } },
  rca: 0.02, pci: 0.82, tariffs_oman: [{ agreement: 'Most Favoured Nation duty rate treatment', tariff: 4.9, year: 2019 }], commodity: [], unit_prices: { year: 2024, unit_code: 5, unit: 'قطعة', oman_total: { value: 1e9, qty: 25000, unit_code: 5, net_kg: 1, estimated: false, unit_price: 40000 }, oman_by_partner: [], world_median_unit_price: 25000, world_p25_unit_price: 17000, hs6: [] }, generated_at: '2026-10-20T10:00:00Z',
  product: { id: 178703, hs4: '8703', name_en: 'Cars', name_ar: 'مركبات برية — Cars' },
  weights: { scale: 0.25, growth: 0.15, price: 0.3, logistics: 0.1, risk: 0.2 },
  summary: { mode: 'import', year: 2024, market_value: 9.6e11, hhi_market: 844, oman_value: 2.1e9, hhi_oman: 2050, oman_top_partner: 'JPN', oman_unit_value: 16600, median_unit_value: 16500, p25_unit_value: 13600, p75_unit_value: 0, saving_estimate: 3.8e8 },
  source: { name: 'BACI', url: 'https://oec.world', note: 'ملاحظة المصدر.' },
  candidates: [
    cand('CHN', 'الصين', 82, { scale: 97, growth: 100, price: 64, logistics: 67, risk: 85 }),
    cand('JPN', 'اليابان', 75, { scale: 98, growth: 67, price: 49, logistics: 72, risk: 90 }),
  ],
};

function mockApi() {
  vi.stubGlobal('fetch', vi.fn(async (url: URL | string) => {
    const u = new URL(String(url));
    const json = (body: unknown) => ({ ok: true, status: 200, json: async () => body }) as Response;
    if (u.pathname.endsWith('/market-intel/capabilities')) return json({ ai: false, shopping: false, sources: [{ key: 'apple', label: 'متجر آبل', free: true, enabled: true }], countries: [{ code: 'OM', name_ar: 'عُمان', currency: 'OMR' }, { code: 'AE', name_ar: 'الإمارات', currency: 'AED' }] });
    if (u.pathname.endsWith('/market-intel/catalog')) return json({ sections: [{ id: 17, name: 'معدات نقل', count: 1 }], chapters: [{ hs2: '87', section: 17, name: 'مركبات برية', count: 1 }], products: [{ id: 178703, hs4: '8703', name_en: 'Cars', name_ar: 'مركبات برية — Cars', section: 17, hs2: '87', commodity: false }] });
    if (u.pathname.endsWith('/market-intel/products')) return json({ data: [{ id: 178703, hs4: '8703', name_en: 'Cars', name_ar: 'مركبات برية — Cars' }] });
    if (u.pathname.endsWith('/market-intel/analysis')) return json(analysis);
    if (u.pathname.endsWith('/market-intel/brief')) return json({ ai_enabled: false, data: { source: 'rules', summary: 'ملخص آلي', recommendations: ['ابدأ بالصين'], risks: ['تركز'], next_steps: ['اطلب عروضًا'], model: null } });
    return { ok: false, status: 404, json: async () => ({ message: 'x' }) } as Response;
  }));
}

const renderPage = (entry = '/market-intel') =>
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <MemoryRouter initialEntries={[entry]}>
        <MarketIntelPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );

describe('صفحة ذكاء الأسواق', () => {
  it('تبحث عن منتج ثم تعرض الترتيب والتوصية الآلية مع وسمها الصريح', async () => {
    mockApi();
    renderPage();

    fireEvent.change(screen.getByLabelText('بحث عن منتج'), { target: { value: 'cars' } });
    fireEvent.click(await screen.findByRole('option', { name: /8703/ }));

    expect(await screen.findByText('ملخص آلي')).toBeInTheDocument();
    expect(screen.getByText('قواعد آلية (بلا ذكاء اصطناعي)')).toBeInTheDocument();
    expect(screen.getByText('اعتماد مرتفع على مورد واحد')).toBeInTheDocument();
    expect(screen.getByText(/ر.ع \/ قطعة/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'الموردون' }));
    const table = await screen.findByRole('table', { name: 'ترتيب المرشحين' });
    expect(within(table).getByText('الصين')).toBeInTheDocument();
    expect(within(table).getByText('سعر قطعة')).toBeInTheDocument();
  });

  it('القوائم المنسدلة المتسلسلة تختار المنتج بلا بحث', async () => {
    mockApi();
    renderPage();
    const section = await screen.findByLabelText('قسم المنتج');
    await waitFor(() => expect(section).not.toBeDisabled());
    fireEvent.change(section, { target: { value: '17' } });
    fireEvent.change(screen.getByLabelText('فصل المنتج'), { target: { value: '87' } });
    fireEvent.change(screen.getByLabelText('المنتج من القائمة'), { target: { value: '178703' } });
    expect(await screen.findByText('اعتماد مرتفع على مورد واحد')).toBeInTheDocument();
  });

  it('تغيير الأوزان يعيد الترتيب دون طلب جديد', async () => {
    mockApi();
    renderPage();
    fireEvent.change(screen.getByLabelText('بحث عن منتج'), { target: { value: 'cars' } });
    fireEvent.click(await screen.findByRole('option', { name: /8703/ }));
    fireEvent.click(await screen.findByRole('button', { name: 'الموردون' }));
    const table = await screen.findByRole('table', { name: 'ترتيب المرشحين' });
    await waitFor(() => expect(within(table).getAllByRole('row')[1]).toHaveTextContent('الصين'));

    // وزن السعر صفر ووزن الحجم أعلى: اليابان (حجم 98) تتقدم على الصين (97)
    for (const [label, value] of [['وزن السعر', '0'], ['وزن النمو', '0'], ['وزن اللوجستيات', '0'], ['وزن المخاطر', '0'], ['وزن الحجم', '100']]) {
      fireEvent.change(screen.getByLabelText(label), { target: { value } });
    }
    await waitFor(() => expect(within(table).getAllByRole('row')[1]).toHaveTextContent('اليابان'));
  });

  it('تبويبتا الأسعار والاشتراكات تشرحان الشروط ولا تُجريان بحثًا بلا تسجيل دخول', async () => {
    mockApi();
    renderPage('/market-intel?tab=prices');
    expect(await screen.findByText(/المصادر: متجر آبل/)).toBeInTheDocument();
    expect(screen.getByText(/لتسجيل الدخول|سجّل الدخول/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'قارن الأسعار' })).toBeDisabled();

    fireEvent.click(screen.getByRole('button', { name: 'الاشتراكات' }));
    expect(await screen.findByText(/تتطلب مفتاح Anthropic/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'قارن الأسعار' })).toBeDisabled();
  });
});
