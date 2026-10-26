/**
 * يحوّل رابط صفحة منتج (أمازون، نون، علي إكسبريس، تيمو، إيباي، أي متجر) إلى اسم منتج قابل للبحث — من الرابط نفسه فقط (مقطع الاسم في المسار)،
 * بلا زيارة الصفحة ولا كشطها. إن لم يكن النص رابطًا يُعاد كما هو.
 */
export interface ParsedProduct {
  query: string;
  store: string | null;
  url: string | null;
  id: string | null;
}

const STORES: [RegExp, string][] = [
  [/amazon\./, 'Amazon'], [/noon\.com/, 'noon'], [/aliexpress\./, 'AliExpress'], [/temu\.com/, 'Temu'], [/ebay\./, 'eBay'], [/alibaba\.com/, 'Alibaba'],
  [/shein\./, 'SHEIN'], [/walmart\.com/, 'Walmart'], [/luluhypermarket\./, 'Lulu'], [/carrefour/, 'Carrefour'], [/jarir\.com/, 'Jarir'], [/sharafdg\.com/, 'Sharaf DG'],
];

const STOP = new Set(['dp', 'gp', 'product', 'p', 'item', 'itm', 'goods', 'en', 'ar', 'uae-en', 'uae-ar', 'saudi-en', 'saudi-ar', 'egypt-en', 'ref', 'html', 'www', 'shop', 's', 'products']);

export function parseProductInput(input: string): ParsedProduct {
  const text = input.trim();
  let u: URL;
  try {
    u = new URL(text);
    if (!/^https?:$/.test(u.protocol)) throw new Error('not http');
  } catch {
    return { query: text, store: null, url: null, id: null };
  }
  const host = u.hostname.toLowerCase();
  const store = STORES.find(([re]) => re.test(host))?.[1] ?? host.replace(/^www\./, '');
  const segs = u.pathname.split('/').map((s) => decodeURIComponent(s)).filter(Boolean);
  const asin = u.pathname.match(/\/(?:dp|gp\/product)\/([A-Z0-9]{10})/i)?.[1] ?? null;
  const itemId = u.pathname.match(/\/item\/(\d+)/)?.[1] ?? null;
  // أطول مقطع يحتوي كلمات مفصولة بشَرطات = اسم المنتج في معظم المتاجر
  const slug = segs
    .filter((s) => !STOP.has(s.toLowerCase()) && /[-_]/.test(s) && /[a-z؀-ۿ]/i.test(s) && !/^[A-Z0-9]{10}$/.test(s))
    .sort((a, b) => b.length - a.length)[0];
  const words = slug
    ? slug.replace(/\.html?$/i, '').replace(/[-_]+/g, ' ').replace(/\b\d{6,}\b/g, '').replace(/\s+/g, ' ').trim()
    : (u.searchParams.get('k') ?? u.searchParams.get('q') ?? u.searchParams.get('_nkw') ?? '');
  return { query: words.slice(0, 120), store, url: text, id: asin ?? itemId };
}

/** الإشارة المرجعية: تُرسل عنوان صفحة المنتج المفتوحة في متصفح المستخدم إلى المقارنة (لا كشط من خوادمنا). */
export function bookmarkletCode(origin: string): string {
  const js = `(()=>{const e=document.querySelector('#productTitle,h1');const t=((e&&e.innerText)||document.title||'').trim().slice(0,140);window.open('${origin}/market-intel?tab=prices&q='+encodeURIComponent(t)+'&from='+encodeURIComponent(location.href),'_blank');})()`;
  return `javascript:${js}`;
}
