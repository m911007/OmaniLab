import { describe, expect, it } from 'vitest';
import { bookmarkletCode, parseProductInput } from '@/modules/marketintel/productUrl';

describe('استخراج المنتج من رابط المتجر', () => {
  it('أمازون: الاسم من المقطع قبل /dp/ مع رقم ASIN', () => {
    const p = parseProductInput('https://www.amazon.ae/Apple-iPhone-15-128-GB/dp/B0CHX1W1XY/ref=sr_1_1?crid=1');
    expect(p.store).toBe('Amazon');
    expect(p.query).toBe('Apple iPhone 15 128 GB');
    expect(p.id).toBe('B0CHX1W1XY');
  });
  it('نون وعلي إكسبريس', () => {
    expect(parseProductInput('https://www.noon.com/uae-en/galaxy-s24-ultra-256gb-titanium-black/N70035190V/p/').query).toBe('galaxy s24 ultra 256gb titanium black');
    const a = parseProductInput('https://www.aliexpress.com/item/1005006123456789.html');
    expect(a.store).toBe('AliExpress');
    expect(a.id).toBe('1005006123456789');
  });
  it('رابط بحث يأخذ كلمة البحث، والنص العادي يبقى كما هو', () => {
    expect(parseProductInput('https://www.amazon.com/s?k=lego+star+wars').query).toBe('lego star wars');
    expect(parseProductInput('حليب أطفال').query).toBe('حليب أطفال');
    expect(parseProductInput('javascript:alert(1)').url).toBeNull();
  });
  it('الإشارة المرجعية تفتح صفحة المقارنة في المنصة', () => {
    const code = bookmarkletCode('http://localhost:8100');
    expect(code.startsWith('javascript:')).toBe(true);
    expect(code).toContain('http://localhost:8100/market-intel?tab=prices&q=');
  });
});

import { landedOmr } from '@/modules/marketintel/PriceCompare';

describe('السعر الواصل إلى عُمان', () => {
  const offer = (country: string, omr: number) => ({ title: 'x', store: 's', price: omr, currency: 'OMR', link: null, thumbnail: null, rating: null, reviews: null, delivery: null, country, country_ar: '', omr, source: 'apple' });
  it('يضيف الشحن ثم الرسم الجمركي على قيمة CIF ثم الضريبة، ولا يغيّر عروض عُمان', () => {
    expect(landedOmr(offer('AE', 100), { ship: 10, duty: 5, vat: 5 })).toBeCloseTo(100 * 1.1 * 1.05 * 1.05);
    expect(landedOmr(offer('OM', 100), { ship: 10, duty: 5, vat: 5 })).toBe(100);
  });
});
