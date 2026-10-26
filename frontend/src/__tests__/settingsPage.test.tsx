import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { groupSecrets, type SecretStatus } from '@/modules/settings/SettingsPage';

const secret = (over: Partial<SecretStatus>): SecretStatus => ({
  name: 'k', label: 'مفتاح', group: 'ذكاء الأسواق', kind: 'secret', help: 'مساعدة', enables: 'ميزة', url: 'https://example.com', example: 'x', configured: false, source: null, hint: null, value: null, updated_at: null, ...over,
});

describe('صفحة مفاتيح الخدمات', () => {
  afterEach(() => vi.unstubAllGlobals());

  it('تجمع المفاتيح في مجموعاتها بترتيب ظهورها', () => {
    const groups = groupSecrets([secret({ name: 'a' }), secret({ name: 'b', group: 'التنبيهات' }), secret({ name: 'c' })]);
    expect(groups.map((g) => [g.group, g.items.map((i) => i.name)])).toEqual([['ذكاء الأسواق', ['a', 'c']], ['التنبيهات', ['b']]]);
  });

  it('للمشرف: تعرض المجموعات والحالة والرابط، وتمنع الحفظ حتى تُكتب قيمة، وتعرض خطأ الصيغة عند الحقل', async () => {
    const data = [
      secret({ name: 'bls_api_key', label: 'مفتاح BLS', group: 'مصادر البيانات', url: 'https://data.bls.gov/registrationEngine/' }),
      secret({ name: 'telegram_chat_id', label: 'معرّف المحادثة', group: 'التنبيهات', kind: 'text', configured: true, source: 'db', value: '-1001234567890' }),
    ];
    vi.stubGlobal('fetch', vi.fn(async (url: string | URL, init?: RequestInit) => {
      const u = String(url);
      if (u.includes('/auth/me')) return { ok: true, status: 200, json: async () => ({ user: { id: 1, name: 'م', email: 'a@b.c', is_admin: true } }) } as Response;
      if (init?.method === 'PUT') return { ok: false, status: 422, json: async () => ({ message: 'الصيغة غير صحيحة. المتوقع: 32 خانة', errors: { value: ['الصيغة غير صحيحة. المتوقع: 32 خانة'] } }) } as Response;
      return { ok: true, status: 200, json: async () => ({ data }) } as Response;
    }));
    const { default: SettingsPage } = await import('@/modules/settings/SettingsPage');
    const { AuthProvider } = await import('@/core/auth');
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <AuthProvider><MemoryRouter><SettingsPage /></MemoryRouter></AuthProvider>
      </QueryClientProvider>,
    );

    expect(await screen.findByText('مصادر البيانات')).toBeInTheDocument();
    expect(screen.getByText('1 من 2 مضبوطة')).toBeInTheDocument();
    const links = screen.getAllByRole('link', { name: /احصل على المفتاح/ }).map((a) => a.getAttribute('href'));
    expect(links).toContain('https://data.bls.gov/registrationEngine/');
    // معرّف المحادثة (نوع text) يظهر قيمته كما هي
    expect(screen.getByDisplayValue('-1001234567890')).toBeInTheDocument();
    const blsSave = screen.getAllByRole('button', { name: 'حفظ' })[0];
    expect(blsSave).toBeDisabled();

    fireEvent.change(screen.getByLabelText('قيمة مفتاح BLS'), { target: { value: 'wrong' } });
    expect(blsSave).toBeEnabled();
    fireEvent.click(blsSave);
    expect(await screen.findByRole('alert')).toHaveTextContent('الصيغة غير صحيحة');
  });
});
