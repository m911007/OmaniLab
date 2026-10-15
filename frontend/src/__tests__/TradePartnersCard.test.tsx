import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { TradePartnersCard } from '@/modules/markets/TradePartnersCard';
import { dataset } from './fixtures';

afterEach(() => vi.unstubAllGlobals());

const entity = (code: string, ar: string) => ({ code, name_ar: ar, name_en: code, type: 'country' });

function mock(reportedTotal: number, partners: { code: string; ar: string; value: number }[]) {
  const sum = partners.reduce((a, p) => a + p.value, 0);
  const calls: string[] = [];
  vi.stubGlobal('fetch', vi.fn(async (url: URL | string) => {
    const u = new URL(String(url));
    calls.push(u.pathname + u.search);
    const ok = (body: unknown) => ({ ok: true, status: 200, json: async () => body }) as Response;
    const ind = { key: 'x', name_ar: 'س', name_en: 'x', unit: 'ألف دولار', frequency: 'A' };
    if (u.pathname.endsWith('/latest')) {
      return ok({ entity: entity('OMN', 'عُمان'), items: [{ indicator: ind, period: '2023', value: reportedTotal, previous: null, rank: null, dataset: dataset({ key: 'wits.trade' }) }] });
    }
    if (u.pathname.endsWith('/observations')) {
      return ok({ indicator: ind, dataset: dataset({ key: 'wits.trade' }), series: [{ entity: entity('OMN', 'عُمان'), points: [['2022', reportedTotal], ['2023', reportedTotal]] }] });
    }
    if (u.pathname.endsWith('/relations')) {
      return ok({
        indicator: ind, dataset: dataset({ key: 'wits.trade' }), entity: { code: 'OMN', name_ar: 'عُمان', name_en: 'Oman' }, direction: 'out', period: u.searchParams.get('period'), count: partners.length, sum,
        items: partners.map((p, i) => ({ rank: i + 1, entity: entity(p.code, p.ar), value: p.value, share: p.value / sum })),
      });
    }
    return { ok: false, status: 404, json: async () => ({ message: 'x' }) } as Response;
  }));
  return calls;
}

const renderCard = () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <TradePartnersCard code="OMN" name="عُمان" />
    </QueryClientProvider>,
  );
};

describe('بطاقة شركاء التجارة', () => {
  it('تُبرز تحذيرًا حين يُنشر جزء كبير من التجارة غير موزّع على شركاء، وتعرض الأرقام بالدولار', async () => {
    mock(59_000_000, [{ code: 'ARE', ar: 'الإمارات', value: 3_760_000 }, { code: 'SAU', ar: 'السعودية', value: 2_940_000 }]);
    renderCard();

    expect(await screen.findByText('الإمارات')).toBeInTheDocument();
    expect(screen.getByText(/لا يوزّع المصدر 89% من صادرات عُمان/)).toBeInTheDocument();
    expect(screen.getByText(/للتجارة الرسمية الكاملة لعُمان/)).toBeInTheDocument();
    expect(screen.getByText(/غير موزَّع على شركاء/)).toBeInTheDocument();
  });

  it('لا تحذير حين يغطي الشركاء معظم الإجمالي', async () => {
    mock(7_000_000, [{ code: 'ARE', ar: 'الإمارات', value: 3_760_000 }, { code: 'SAU', ar: 'السعودية', value: 2_940_000 }]);
    renderCard();

    await screen.findByText('الإمارات');
    expect(screen.queryByText(/لا يوزّع المصدر/)).not.toBeInTheDocument();
  });

  it('التبديل إلى الواردات يطلب مؤشر الواردات', async () => {
    const calls = mock(7_000_000, [{ code: 'ARE', ar: 'الإمارات', value: 3_760_000 }]);
    renderCard();
    await screen.findByText('الإمارات');

    fireEvent.click(within(screen.getByRole('group', { name: 'التدفق' })).getByRole('button', { name: 'الواردات' }));

    await screen.findByText(/أكبر مصادر واردات/);
    expect(calls.some((c) => c.includes('indicator=wits.IMPORTS_FROM'))).toBe(true);
  });
});
