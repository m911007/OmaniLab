import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { StrictMode } from 'react';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { Shell } from '@/app/Shell';

afterEach(() => vi.unstubAllGlobals());

describe('الغلاف والتنقل', () => {
  it('الانتقال بين الصفحات يعرض الصفحة الجديدة دون إعادة تحميل (وفي StrictMode)', async () => {
    vi.stubGlobal('scrollTo', vi.fn());
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 404, json: async () => ({ message: 'x' }) }) as Response));
    render(
      <StrictMode>
        <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
          <MemoryRouter initialEntries={['/']}>
            <Routes>
              <Route element={<Shell />}>
                <Route index element={<h1>الرئيسية</h1>} />
                <Route path="growth" element={<h1>صفحة النمو</h1>} />
              </Route>
            </Routes>
          </MemoryRouter>
        </QueryClientProvider>
      </StrictMode>,
    );

    expect(screen.getByText('الرئيسية')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('link', { name: 'النمو والناتج' }));

    expect(await screen.findByText('صفحة النمو')).toBeInTheDocument();
  });
});
