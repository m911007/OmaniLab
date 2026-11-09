import { useMemo } from 'react';
import { ReadingGrid } from '@/core/components/ReadingGrid';
import { Loading, ErrorState } from '@/core/components/States';
import { formatNumber } from '@/core/format';
import { assess, breakevenOilPrice, hydrocarbonShare, lastCommonYear } from './metrics';
import { useFiscalData } from './useFiscalData';
import { StatLabel } from '@/core/components/IndicatorIcon';

interface Tile {
  label: string;
  value: string;
  meta: string;
  tone?: 'good' | 'warning' | 'critical';
}

const color = (t?: Tile['tone']) => (t === 'good' ? 'var(--good)' : t === 'warning' ? 'var(--warning)' : t === 'critical' ? 'var(--critical)' : undefined);

/** رأس الصفحة: خمسة مؤشرات مشتقة من الحساب الختامي وجملة تقييم من قوالب ثابتة، قبل أي رسم. */
export function FiscalHeadline() {
  const { isLoading, error, m } = useFiscalData();
  const view = useMemo(() => {
    const year = lastCommonYear(m['mof.rev_total'], m['mof.balance'], m['mof.rev_oil'], m['mof.rev_gas']);
    if (!year) return null;
    const share = hydrocarbonShare(m['mof.rev_oil'], m['mof.rev_gas'], m['mof.rev_total']).get(year)!;
    const be = breakevenOilPrice(m['ncsi.OIL_PRICE'], m['mof.balance'], m['mof.rev_oil']).get(year);
    const price = m['ncsi.OIL_PRICE'].get(year);
    const bal = m['mof.balance'].get(year)!;
    const debtYear = [...m['imf.GGXWDG_NGDP'].keys()].filter((y) => Number(y) <= Number(year)).sort().pop();
    const debt = debtYear ? m['imf.GGXWDG_NGDP'].get(debtYear) : undefined;
    const ie = m['imf.ie'].get(year) ?? [...m['imf.ie']].sort().pop()?.[1];
    const revGdp = m['imf.GGR_G01_GDP_PT'].get(year);
    const a = assess({ year, balance: bal, hydroShare: share, price, breakeven: be });
    const tiles: Tile[] = [
      { label: `الفائض/العجز ${year}`, value: `${bal > 0 ? '+' : ''}${formatNumber(bal, 2)}`, meta: 'B ر.ع — الحساب الختامي، وزارة المالية', tone: bal >= 0 ? 'good' : bal > -1 ? 'warning' : 'critical' },
      { label: 'الدين الحكومي', value: debt !== undefined ? `${formatNumber(debt, 1)}%` : '—', meta: `من الناتج ${debtYear ?? ''} — صندوق النقد`, tone: debt === undefined ? undefined : debt < 40 ? 'good' : debt < 60 ? 'warning' : 'critical' },
      { label: 'حصة النفط والغاز من الإيراد', value: `${formatNumber(share, 0)}%`, meta: `مشتق: (نفط + غاز) ÷ الإيراد ${year}`, tone: share < 50 ? 'good' : share < 70 ? 'warning' : 'critical' },
      { label: 'سعر التعادل التقريبي', value: be !== undefined ? `${formatNumber(be, 0)}$` : '—', meta: price !== undefined ? `مشتق، حجم إنتاج ثابت · متوسط السعر ${formatNumber(price, 0)}$` : 'مشتق', tone: be !== undefined && price !== undefined ? (price >= be ? 'good' : 'warning') : undefined },
      { label: 'الفوائد ÷ الإيرادات', value: ie !== undefined && revGdp ? `${formatNumber((ie / revGdp) * 100, 1)}%` : '—', meta: 'مشتق من نسب الناتج — صندوق النقد', tone: ie !== undefined && revGdp ? ((ie / revGdp) * 100 < 8 ? 'good' : (ie / revGdp) * 100 < 15 ? 'warning' : 'critical') : undefined },
    ];
    return { tiles, a };
  }, [m]);

  if (isLoading) return <Loading height={160} />;
  if (error) return <ErrorState error={error} />;
  if (!view) return null;
  return (
    <>
      <p className="callout fiscal-assess" role="status" style={{ borderInlineStartColor: color(view.a.tone) }}>{view.a.text}</p>
      <ReadingGrid className="fiscal-tiles">
        {view.tiles.map((t) => (
          <div key={t.label} className="card tile" style={{ borderInlineStartColor: color(t.tone) }}>
            <StatLabel>{t.label}</StatLabel>
            <span className="stat-value num">{t.value}</span>
            <span className="stat-meta">{t.meta}</span>
          </div>
        ))}
      </ReadingGrid>
      <p className="source-note">حدود الألوان تقديرية للقراءة السريعة (وليست أهدافًا رسمية). سعر التعادل = السعر × (1 − الرصيد ÷ إيراد النفط)، بافتراض ثبات الإنتاج وبقية البنود.</p>
    </>
  );
}
