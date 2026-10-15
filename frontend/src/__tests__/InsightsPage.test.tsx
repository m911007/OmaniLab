import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen, within } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { Scorecard, ScorecardItem, StandingClass } from '@/core/api';
import { headlines } from '@/modules/insights/headlines';
import InsightsPage from '@/modules/insights/InsightsPage';
import { PositionStrip } from '@/modules/insights/PositionStrip';
import { SignalsPanel } from '@/modules/home/SignalsPanel';
import { dataset } from './fixtures';

afterEach(() => vi.unstubAllGlobals());

const item = (key: string, name: string, cls: StandingClass, percentile: number, over: Partial<ScorecardItem> = {}): ScorecardItem => ({
  theme: 'macro',
  key,
  name_ar: name,
  unit: '%',
  polarity: 'higher',
  period: '2025',
  value: 3.3,
  baseline: { period: '2020', value: 2 },
  trend: 'improving',
  class: cls,
  world: { rank: Math.round(200 - percentile * 2), of: 200, percentile, median: 2 },
  gcc: { rank: 3, of: 6, best: { code: 'ARE', value: 5.1, percentile: 90 }, items: [{ code: 'ARE', value: 5.1, percentile: 90 }, { code: 'OMN', value: 3.3, percentile }] },
  dataset: dataset(),
  ...over,
});

const card: Scorecard = {
  entity: 'OMN',
  peers: ['ARE'],
  thresholds: { strong_from: 70, weak_until: 30, baseline_years: 5 },
  counts: { strength: 2, challenge: 1, opportunity: 1 },
  themes: [
    { key: 'macro', label: 'النمو والاستقرار الكلي', count: 3, percentile: 62 },
    { key: 'fiscal', label: 'المالية العامة', count: 1, percentile: 20 },
  ],
  items: [
    item('a', 'البطالة', 'strength', 88),
    item('b', 'الدين', 'strength', 75),
    item('c', 'الطاقة المتجددة', 'challenge', 4, { trend: 'stable', theme: 'fiscal' }),
    item('d', 'سيادة القانون', 'opportunity', 55),
    item('e', 'بطالة الشباب', 'watch', 45, { trend: 'worsening' }),
  ],
};

describe('headlines', () => {
  it('يرتّب نقاط القوة تنازليًا والتحديات من الأشد، ويقتصر على العدد المطلوب', () => {
    const h = headlines(card, 1);

    expect(h.strengths.map((i) => i.key)).toEqual(['a']);
    expect(h.challenges.map((i) => i.key)).toEqual(['c']);
    expect(h.opportunities.map((i) => i.key)).toEqual(['d']);
    expect(h.watch.map((i) => i.key)).toEqual(['e']);
  });
});

describe('PositionStrip', () => {
  it('يصف الموقع نصًا لقارئات الشاشة ويضع علامة عُمان عند المئين', () => {
    const { container } = render(<PositionStrip item={item('a', 'x', 'strength', 88)} weakUntil={30} strongFrom={70} />);

    expect(screen.getByRole('img').getAttribute('aria-label')).toContain('المئين 88');
    expect((container.querySelector('.strip-me') as HTMLElement).style.left).toBe('88%');
    expect(container.querySelectorAll('.strip-peer')).toHaveLength(1); // الإمارات فقط؛ عُمان لها علامتها
  });
});

describe('صفحة التحديات والفرص', () => {
  const renderPage = () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => card }) as Response));
    return render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <MemoryRouter>
          <InsightsPage />
        </MemoryRouter>
      </QueryClientProvider>,
    );
  };

  it('تعرض العدّادات والخلاصة والمؤشرات مجمّعة بالتصنيف', async () => {
    renderPage();

    expect(await screen.findByText('الخلاصة التنفيذية')).toBeInTheDocument();
    expect(screen.getAllByText('البطالة').length).toBeGreaterThanOrEqual(2); // في الخلاصة وفي التفصيل
    expect(screen.getAllByRole('heading', { name: /التحديات/ }).length).toBeGreaterThanOrEqual(2);
    expect(screen.getAllByText(/المرتبة/).length).toBeGreaterThan(0);
  });

  it('الضغط على عدّاد يصفّي القائمة، والضغط عليه ثانية يلغي التصفية', async () => {
    renderPage();
    await screen.findByText('الخلاصة التنفيذية');

    const list = () => document.querySelectorAll('.ind-row').length;
    expect(list()).toBe(5);
    const tile = () => document.querySelector('.tile-challenge') as HTMLElement;
    fireEvent.click(tile());
    expect(list()).toBe(1);
    fireEvent.click(tile());
    expect(list()).toBe(5);
  });

  it('اختيار محور يقصر القائمة على مؤشراته', async () => {
    renderPage();
    await screen.findByText('الخلاصة التنفيذية');

    fireEvent.click(within(screen.getByLabelText('موقع عُمان حسب المحور')).getByRole('button', { name: /المالية العامة/ }));

    expect(document.querySelectorAll('.ind-row')).toHaveLength(1);
  });
});

describe('لوحة الإشارات', () => {
  const renderPanel = (data: unknown) => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: true, status: 200, json: async () => ({ data }) }) as Response));
    return render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <SignalsPanel />
      </QueryClientProvider>,
    );
  };

  it('تعرض كل إشارة بعنوانها ونصها وفترتها وبأيقونة مع النص', async () => {
    renderPanel([{ id: 'a', title: 'تحرّك حاد في سعر برنت', message: 'تغيّر 12% في سبتمبر', tone: 'warning', indicator: 'x', entity: 'WLD', period: '2026-09', value: 1, previous: 2, change: 12 }]);

    expect(await screen.findByText('تحرّك حاد في سعر برنت')).toBeInTheDocument();
    expect(screen.getByText(/تغيّر 12%/)).toBeInTheDocument();
    expect(screen.getByText('انتباه')).toBeInTheDocument();
  });

  it('لا تعرض شيئًا حين لا توجد إشارات', async () => {
    const { container } = renderPanel([]);

    await new Promise((r) => setTimeout(r, 20));
    expect(container).toBeEmptyDOMElement();
  });
});
