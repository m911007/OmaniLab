import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { SeriesChart } from '@/core/components/SeriesChart';
import { SourceNote } from '@/core/components/SourceNote';
import { dataset } from './fixtures';

afterEach(() => vi.unstubAllGlobals());

const wb = dataset({ key: 'worldbank.macro', source: { key: 'worldbank', name_ar: 'البنك الدولي', name_en: 'WB', url: 'https://data.worldbank.org', license: null } });
const imf = dataset();
const ind = (key: string) => ({ key, name_ar: 'عدد السكان', name_en: 'Pop', unit: 'نسمة', frequency: 'A' as const });
const omanSeries = (points: [string, number][]) => [{ entity: { code: 'OMN', name_ar: 'عُمان', name_en: 'Oman', type: 'country' }, points }];

function mockApi(verdict: 'agree' | 'differ') {
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: unknown) => {
      const u = String(url);
      const json = (b: unknown) => ({ ok: true, status: 200, json: async () => b }) as Response;
      if (u.includes('crosschecks/detail'))
        return json({
          group: { key: 'population', label: 'عدد السكان', mode: 'relative', tolerance: 0.02 },
          verdict,
          periods: [{ period: '2024', values: { 'wb.SP.POP.TOTL': 5_100_000, 'imf.LP': 5_300_000 }, spread: 0.04, agree: false }],
          sources: [
            { indicator: 'wb.SP.POP.TOTL', name_ar: 'عدد السكان', unit: 'نسمة', is_base: true, dataset: wb, points: [['2024', 5_100_000]] },
            { indicator: 'imf.LP', name_ar: 'عدد السكان', unit: 'نسمة', is_base: false, dataset: imf, points: [['2024', 5_300_000]] },
          ],
        });
      if (u.includes('crosschecks'))
        return json({
          data: [
            {
              key: 'population',
              label: 'عدد السكان',
              verdict,
              indicators: ['wb.SP.POP.TOTL', 'imf.LP'],
              members: [
                { indicator: 'wb.SP.POP.TOTL', has_data: true, source: { key: 'worldbank', name_ar: 'البنك الدولي', url: 'https://data.worldbank.org' } },
                { indicator: 'imf.LP', has_data: true, source: { key: 'imf', name_ar: 'صندوق النقد الدولي', url: 'https://www.imf.org' } },
              ],
            },
          ],
        });
      return json({ indicator: ind('wb.SP.POP.TOTL'), dataset: wb, series: omanSeries([['2024', 5_100_000]]) });
    }),
  );
}

const renderChart = () =>
  render(
    <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
      <SeriesChart title="السكان" lines={[{ indicator: 'wb.SP.POP.TOTL', entity: 'OMN' }]} />
    </QueryClientProvider>,
  );

describe('مصادر متعددة للقياس', () => {
  it('المصادر المتطابقة تُعرض كلها بشارات وحكم «متطابقة»', async () => {
    mockApi('agree');
    renderChart();

    expect(await screen.findByText(/2 مصادر متطابقة/)).toBeInTheDocument();
    expect(screen.getAllByText('IMF').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('التفاصيل')).toBeInTheDocument();
  });

  it('عند الاختلاف يظهر زر المصادر المتاحة، ويبدّل الرسم إلى المصدر الآخر', async () => {
    mockApi('differ');
    renderChart();

    fireEvent.click(await screen.findByRole('button', { name: /اعرض المصادر وقارن/ }));
    const radios = await screen.findAllByRole('radio');
    expect(radios).toHaveLength(2);
    expect(radios[0]).toBeChecked();

    fireEvent.click(radios[1]);
    await waitFor(() => expect(screen.getAllByRole('radio')[1]).toBeChecked());
    expect(screen.getAllByText(/صندوق النقد الدولي/).length).toBeGreaterThan(0);
  });
});

describe('SourceNote', () => {
  it('يعرض شارة المصدر برابطه الأصلي وتاريخ آخر تحديث للمصدر', () => {
    render(<SourceNote datasets={[wb]} />);

    const link = screen.getByRole('link', { name: /WB/ });
    expect(link).toHaveAttribute('href', 'https://data.worldbank.org');
    expect(link).toHaveAttribute('target', '_blank');
    expect(screen.getByText(/تحديث المصدر/)).toBeInTheDocument();
  });

  it('بلا تاريخ نشر يكتب «غير معلن» بدل أن يخفي السطر', () => {
    const d = dataset({ freshness: { ...dataset().freshness, source_published_at: null } });
    render(<SourceNote datasets={[d]} />);

    expect(screen.getByText('غير معلن')).toBeInTheDocument();
  });
});
