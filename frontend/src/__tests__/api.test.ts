import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError } from '@/core/api';

afterEach(() => vi.unstubAllGlobals());

const respond = (status: number, body: unknown) =>
  vi.stubGlobal('fetch', vi.fn(async () => ({ ok: status < 400, status, json: async () => body }) as Response));

describe('عميل الـ API', () => {
  it('يبني العنوان بالمعاملات ويتخطى الفارغة', async () => {
    respond(200, { ok: true });
    await api('observations', { indicator: 'imf.NGDPD', entities: 'OMN,SAU', from: undefined, to: '' });
    const url = String((fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]);
    expect(url).toContain('/api/v1/observations?');
    expect(url).toContain('indicator=imf.NGDPD');
    expect(url).toContain('entities=OMN%2CSAU');
    expect(url).not.toContain('from=');
    expect(url).not.toContain('to=');
  });

  it('يرمي ApiError برسالة الخادم', async () => {
    respond(404, { message: 'No query results for model [Indicator]' });
    await expect(api('observations')).rejects.toMatchObject({ status: 404, message: 'No query results for model [Indicator]' });
    await expect(api('observations')).rejects.toBeInstanceOf(ApiError);
  });

  it('رد غير JSON يعطي رسالة HTTP', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => ({ ok: false, status: 502, json: async () => { throw new Error('x'); } }) as unknown as Response));
    await expect(api('overview')).rejects.toMatchObject({ message: 'HTTP 502' });
  });
});
