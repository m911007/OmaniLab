import { useMemo, useState } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { RankedBars, type RankedItem } from '@/core/components/RankedBars';
import { Segmented } from '@/core/components/Segmented';
import { Compact } from '@/core/components/Compact';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { entityName } from '@/core/format';
import { useLatest, useRelations, useSeries } from '@/core/hooks';
import { formatNumber } from '@/core/format';

type Flow = 'wits.EXPORTS_TO' | 'wits.IMPORTS_FROM';

/**
 * شركاء الدولة التجاريون عالميًا (WITS / UN Comtrade) كما تبلّغ بها الدولة نفسها.
 * قيم المصدر بآلاف الدولارات؛ تُعرض بالدولار. لعُمان مصدر رسمي أدق في صفحة «التجارة الخارجية» (منافذ).
 */
export function TradePartnersCard({ code, name }: { code: string; name: string }) {
  const [flow, setFlow] = useState<Flow>('wits.EXPORTS_TO');
  const latest = useLatest(code, ['wits.EXPORTS_TOTAL'], undefined);
  const latestPeriod = latest.data?.items[0]?.period ?? '';
  const years = latestPeriod ? [String(Number(latestPeriod)), String(Number(latestPeriod) - 1)] : [];
  const [yearChoice, setYearChoice] = useState<string | null>(null);
  const year = yearChoice && years.includes(yearChoice) ? yearChoice : (years[0] ?? '');

  const rel = useRelations({ indicator: flow, entity: code, period: year, limit: 12 });
  const totalIndicator = flow === 'wits.EXPORTS_TO' ? 'wits.EXPORTS_TOTAL' : 'wits.IMPORTS_TOTAL';
  const totals = useSeries(totalIndicator, [code]);
  const total = latest.data?.items[0];

  // الإجمالي المبلَّغ عنه (بآلاف الدولارات) مقابل مجموع ما وُزِّع على شركاء: الفرق لا ينشره المصدر موزّعًا (نفط خام، سلع سرية…).
  const reported = totals.data?.series[0]?.points.find((p) => p[0] === year)?.[1] ?? null;
  const distributed = rel.data?.sum ?? null;
  const gap = reported !== null && distributed !== null ? Math.max(reported - distributed, 0) : null;
  const gapShare = gap !== null && reported ? gap / reported : null;

  const items: RankedItem[] = useMemo(
    () => (rel.data?.items ?? []).map((i) => ({ key: i.entity.code, rank: i.rank, label: entityName(i.entity), value: i.value * 1000, share: i.share, color: 'var(--series-1)' })),
    [rel.data],
  );

  return (
    <ChartCard
      title={flow === 'wits.EXPORTS_TO' ? `أكبر وجهات صادرات ${name}` : `أكبر مصادر واردات ${name}`}
      subtitle={`${year || '—'} — دولار أمريكي، بحسب إبلاغ ${name} نفسها`}
      datasets={rel.data ? [rel.data.dataset] : []}
      note={rel.data ? `يغطي ${rel.data.count} شريكًا. النسب من مجموع ما وُزِّع على شركاء محددين.` : undefined}
      callout={
        gapShare !== null && gapShare > 0.1
          ? `لا يوزّع المصدر ${formatNumber(gapShare * 100, 0)}% من ${flow === 'wits.EXPORTS_TO' ? 'صادرات' : 'واردات'} ${name} المبلَّغ عنها على شركاء (مثل النفط الخام وبعض السلع السرية)، فلا تمثل الأشرطة كامل التجارة.${code === 'OMN' ? ' للتجارة الرسمية الكاملة لعُمان بالدول: صفحة «التجارة الخارجية» (منافذ).' : ''}`
          : undefined
      }
      actions={
        <Segmented
          label="التدفق"
          value={flow}
          onChange={setFlow}
          options={[
            { value: 'wits.EXPORTS_TO', label: 'الصادرات' },
            { value: 'wits.IMPORTS_FROM', label: 'الواردات' },
          ]}
        />
      }
    >
      {years.length > 1 && (
        <div className="controls">
          <Segmented label="السنة" value={year} onChange={setYearChoice} options={years.map((y) => ({ value: y, label: y }))} />
        </div>
      )}
      {latest.isLoading || rel.isLoading ? (
        <Loading height={320} />
      ) : rel.error ? (
        <ErrorState error={rel.error as Error} />
      ) : items.length === 0 ? (
        <Empty>لا بيانات شركاء متاحة لهذه الدولة في WITS.</Empty>
      ) : (
        <>
          <RankedBars items={items} unitLabel="دولار" />
          {total && reported !== null && (
            <p className="muted" style={{ marginTop: 10, fontSize: 13 }}>
              الإجمالي المبلَّغ عنه في {year}: <Compact v={reported * 1000} /> دولار
              {gap !== null && gap > 0 && (
                <>
                  ، منها غير موزَّع على شركاء: <Compact v={gap * 1000} /> دولار
                </>
              )}
              .
            </p>
          )}
        </>
      )}
    </ChartCard>
  );
}
