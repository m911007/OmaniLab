import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SeriesChart } from '@/core/components/SeriesChart';
import { dataset } from './fixtures';

afterEach(() => vi.unstubAllGlobals());

const payload = {
  indicator: { key: 'imf.NGDP_RPCH', name_ar: 'نمو', name_en: 'g', unit: '%', frequency: 'A' },
  dataset: dataset(),
  series: [{ entity: { code: 'OMN', name_ar: 'عُمان', name_en: 'Oman', type: 'country' }, points: [['2024', 1.6], ['2025', 2.4]] }],
};

function renderChart(ui: React.ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(<QueryClientProvider client={client}>{ui}</QueryClientProvider>);
}

describe('SeriesChart', () => {
  it('يعرض العنوان ومصدر البيانات وحداثتها ويبدّل إلى الجدول', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => payload }) as Response));
    renderChart(<SeriesChart title="نمو الناتج" lines={[{ indicator: 'imf.NGDP_RPCH', entity: 'OMN' }]} />);

    expect(await screen.findByText(/صندوق النقد الدولي/)).toBeInTheDocument();
    expect(screen.getByText('نمو الناتج')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'جدول' }));
    await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument());
    expect(screen.getByText('2.4')).toBeInTheDocument();
    expect(screen.getByText('1.6')).toBeInTheDocument();
  });

  it('يطلب كل مؤشر مرة واحدة لكياناته معًا', async () => {
    const fetchMock = vi.fn(async (_url: unknown) => ({ ok: true, status: 200, json: async () => payload }) as Response);
    vi.stubGlobal('fetch', fetchMock);
    renderChart(
      <SeriesChart
        title="مقارنة"
        lines={[
          { indicator: 'imf.NGDP_RPCH', entity: 'OMN' },
          { indicator: 'imf.NGDP_RPCH', entity: 'SAU' },
        ]}
      />,
    );
    await screen.findByText(/صندوق النقد الدولي/);
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(String(fetchMock.mock.calls[0][0])).toContain('entities=OMN%2CSAU');
  });

  it('خطأ الخادم يظهر برسالة وزر إعادة', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 500, json: async () => ({ message: 'تعطل' }) }) as Response));
    renderChart(<SeriesChart title="س" lines={[{ indicator: 'x', entity: 'OMN' }]} />);
    expect(await screen.findByRole('alert')).toHaveTextContent('تعذّر تحميل البيانات');
    expect(screen.getByText('تعطل')).toBeInTheDocument();
  });

  it('بلا نقاط يعرض حالة فارغة لا مخططًا مكسورًا', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ ...payload, series: [] }) }) as Response));
    renderChart(<SeriesChart title="س" lines={[{ indicator: 'imf.NGDP_RPCH', entity: 'OMN' }]} />);
    expect(await screen.findByText('لا توجد بيانات لهذا الاختيار.')).toBeInTheDocument();
  });
});
