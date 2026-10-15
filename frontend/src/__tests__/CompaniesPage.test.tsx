import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CompaniesPage from '@/modules/companies/CompaniesPage';
import { dataset } from './fixtures';

afterEach(() => {
  vi.unstubAllGlobals();
  vi.useRealTimers();
});

const stats = {
  total: 264,
  by_status: [{ label: 'ACTIVE', count: 260 }, { label: 'INACTIVE', count: 4 }],
  by_governorate: [{ label: 'مسقط', count: 185 }, { label: 'ظفار', count: 8 }],
  by_category: [{ label: 'GENERAL', count: 200 }],
  by_legal_form: [],
  by_year: [{ year: '2020', count: 7 }],
  dataset: dataset({ key: 'gleif.oman' }),
};

const row = { code: 'LEI1', name_en: 'OQ Gas', name_ar: 'أوكيو للغاز', status: 'ACTIVE', category: 'GENERAL', city: 'Muscat', governorate: 'مسقط', creation_date: '2019-05-01', registered_as: '123' };

function mockApi() {
  const calls: string[] = [];
  vi.stubGlobal('fetch', vi.fn(async (url: URL | string) => {
    const u = new URL(String(url));
    calls.push(u.pathname + u.search);
    const json = (body: unknown) => ({ ok: true, status: 200, json: async () => body }) as Response;
    if (u.pathname.endsWith('/companies/stats')) return json(stats);
    if (u.pathname.endsWith('/companies/LEI1')) return json({ code: 'LEI1', name_en: 'OQ Gas', name_ar: 'أوكيو للغاز', attributes: { status: 'ACTIVE', category: 'GENERAL', city: 'Muscat', other_names: ['OQ Gas Networks', 'Oman Gas'] }, dataset: dataset({ key: 'gleif.oman' }) });
    if (u.pathname.endsWith('/companies')) return json({ total: u.searchParams.get('q') ? 1 : 264, page: 1, per_page: 20, data: [row], dataset: dataset({ key: 'gleif.oman' }) });
    if (u.pathname.includes('/observations')) return json({ indicator: { key: 'x', name_ar: 'س', name_en: 'x', unit: null, frequency: 'A' }, dataset: dataset(), series: [] });
    return { ok: false, status: 404, json: async () => ({ message: 'x' }) } as Response;
  }));
  return calls;
}

const renderPage = () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <CompaniesPage />
      </MemoryRouter>
    </QueryClientProvider>,
  );
};

describe('صفحة الشركات', () => {
  it('تعرض الملخص وقائمة الشركات بالاسم العربي مع الإنجليزي', async () => {
    mockApi();
    renderPage();

    expect(await screen.findByText('الكيانات المسجَّلة')).toBeInTheDocument();
    expect((await screen.findAllByText('264')).length).toBeGreaterThanOrEqual(1); // في الملخص وفي عدّاد الجدول
    const table = await screen.findByRole('table', { name: 'الشركات' });
    expect(within(table).getByText('أوكيو للغاز')).toBeInTheDocument();
    expect(within(table).getByText('OQ Gas')).toBeInTheDocument();
    expect(within(table).getByText('نشط')).toBeInTheDocument();
  });

  it('البحث مؤجَّل ثم يُطلب بالنص المكتوب مرة واحدة', async () => {
    const calls = mockApi();
    renderPage();
    await screen.findByRole('table', { name: 'الشركات' });
    const before = calls.filter((c) => c.includes('/companies?')).length;

    const input = screen.getByLabelText('بحث في الشركات');
    fireEvent.change(input, { target: { value: 'أ' } });
    fireEvent.change(input, { target: { value: 'أوكيو' } });

    await waitFor(() => expect(calls.some((c) => c.includes('q=%D8%A3%D9%88%D9%83%D9%8A%D9%88'))).toBe(true));
    const after = calls.filter((c) => c.includes('/companies?') && c.includes('q=')).length;
    expect(after).toBe(1);
    expect(calls.filter((c) => c.includes('/companies?')).length).toBeGreaterThanOrEqual(before);
  });

  it('النقر على شركة يفتح ملفها بكل سماتها ويُغلق بـ Escape', async () => {
    mockApi();
    renderPage();
    const table = await screen.findByRole('table', { name: 'الشركات' });
    fireEvent.click(within(table).getByRole('button', { name: 'أوكيو للغاز' }));

    const dialog = await screen.findByRole('dialog', { name: 'ملف الشركة' });
    expect(await within(dialog).findByText('OQ Gas Networks، Oman Gas')).toBeInTheDocument();
    expect(within(dialog).getByText('حالة الكيان')).toBeInTheDocument();

    fireEvent.keyDown(window, { key: 'Escape' });
    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
  });
});
