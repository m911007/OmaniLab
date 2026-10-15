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

  it('الرسم الذي فيه عُمان ودول أخرى يبدأ بعُمان وحدها ثم يقارن عند الطلب بطلب واحد لكل المؤشر', async () => {
    const fetchMock = vi.fn(async (url: unknown) => ({ ok: true, status: 200, json: async () => (String(url).includes('crosschecks') ? { data: [] } : payload) }) as Response);
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
    const obs = () => fetchMock.mock.calls.map((c) => String(c[0])).filter((u) => u.includes('/observations'));
    expect(obs()).toHaveLength(1);
    expect(obs()[0]).toContain('entities=OMN');
    expect(obs()[0]).not.toContain('SAU');
    expect(screen.getByRole('button', { name: 'عُمان' })).toHaveAttribute('aria-pressed', 'true');

    fireEvent.click(screen.getByRole('button', { name: 'مقارنة مع دول أخرى' }));
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('entities=OMN%2CSAU'))).toBe(true));
    expect(screen.getByLabelText('إضافة دولة إلى المقارنة')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'جدول' }));
    await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument());
  });

  it('الرسم الذي فيه عُمان وحدها يبقى بمفتاح مخطط/جدول فقط', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => payload }) as Response));
    renderChart(<SeriesChart title="س" lines={[{ indicator: 'imf.NGDP_RPCH', entity: 'OMN' }]} />);
    await screen.findByText(/صندوق النقد الدولي/);

    expect(screen.queryByRole('button', { name: 'مقارنة مع دول أخرى' })).toBeNull();
    expect(screen.getByRole('button', { name: 'مخطط' })).toBeInTheDocument();
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
