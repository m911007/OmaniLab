import { useMemo, useState } from 'react';
import { ChartCard } from '@/core/components/ChartCard';
import { RankedBars, type RankedItem } from '@/core/components/RankedBars';
import { Segmented } from '@/core/components/Segmented';
import { SeriesChart } from '@/core/components/SeriesChart';
import { Empty, ErrorState, Loading } from '@/core/components/States';
import { entityName, formatDate, formatNumber } from '@/core/format';
import { useRanking } from '@/core/hooks';
import type { Dataset } from '@/core/api';
import { MONTHS, parseManafethVersion } from './manafeth';

type Flow = 'EXPORTS_TO' | 'IMPORTS_FROM' | 'REEXPORTS_TO';

const FLOWS: { value: Flow; label: string; trend: string }[] = [
  { value: 'EXPORTS_TO', label: 'صادرات عُمان إلى', trend: 'الصادرات إلى' },
  { value: 'IMPORTS_FROM', label: 'واردات عُمان من', trend: 'الواردات من' },
  { value: 'REEXPORTS_TO', label: 'إعادة التصدير إلى', trend: 'إعادة التصدير إلى' },
];

/** الشركاء التجاريون: ترتيب بحسب التدفق والسنة. السنة الجارية تُعرض مؤشرات «منذ بداية العام» مع تنبيه صريح بعدم اكتمالها. */
export function PartnersExplorer({ dataset }: { dataset: Dataset }) {
  const v = parseManafethVersion(dataset.freshness.source_version);
  const lastComplete = v ? (v.month >= 12 ? v.year : v.year - 1) : new Date().getFullYear() - 1;
  const years = [lastComplete - 3, lastComplete - 2, lastComplete - 1, lastComplete].map(String);
  const partialYear = v && v.month < 12 ? String(v.year) : null;

  const [flow, setFlow] = useState<Flow>('EXPORTS_TO');
  const [year, setYear] = useState<string>(String(lastComplete));
  const [partner, setPartner] = useState<string | null>(null);

  const isYtd = partialYear !== null && year === partialYear;
  const indicator = `manafeth.${flow}${isYtd ? '_YTD' : ''}`;
  const { data, isLoading, error, refetch } = useRanking({ indicator, period: year, limit: 15, type: 'any' });

  const items: RankedItem[] = useMemo(
    () =>
      (data?.items ?? []).map((i) => ({
        key: i.entity.code,
        rank: i.rank,
        label: entityName(i.entity),
        value: i.value,
        share: i.share,
        muted: i.entity.type === 'other',
        color: i.entity.type === 'other' ? undefined : 'var(--series-1)',
      })),
    [data],
  );

  const countries = (data?.items ?? []).filter((i) => i.entity.type === 'country');
  const top5Share = countries.slice(0, 5).reduce((a, i) => a + (i.share ?? 0), 0);
  const unallocated = data?.items.find((i) => i.entity.code === 'ZZZ');
  const selected = partner ?? countries[0]?.entity.code ?? null;
  const flowMeta = FLOWS.find((f) => f.value === flow)!;

  const callout = isYtd && v
    ? `بيانات ${year} غير مكتملة: تغطي من بداية العام حتى ${MONTHS[v.month - 1]} ${v.year} فقط، ولا تصح مقارنتها بسنة كاملة. آخر تغيير في بياناتنا من المصدر: ${formatDate(dataset.freshness.last_changed_at)}.`
    : undefined;

  return (
    <div className="grid" style={{ gap: 16 }}>
      <ChartCard
        title={`${flowMeta.label} — أكبر 15 شريكًا`}
        subtitle={`${isYtd ? 'منذ بداية ' + year + ' (غير مكتملة)' : 'سنة ' + year + ' كاملة'} — ريال عُماني`}
        datasets={[data?.dataset ?? dataset]}
        callout={callout}
        note={unallocated ? 'بند «غير محدد / غير موزّع» جزء من الإجمالي الرسمي لا ينشره المصدر موزّعًا على دول (نفط خام غالبًا).' : undefined}
        actions={
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
            <Segmented label="التدفق" value={flow} onChange={setFlow} options={FLOWS.map((f) => ({ value: f.value, label: f.label.replace(' عُمان', '') }))} />
          </div>
        }
      >
        <div className="controls">
          <span className="field">السنة</span>
          <Segmented
            label="السنة"
            value={year}
            onChange={setYear}
            options={[...years.map((y) => ({ value: y, label: y })), ...(partialYear ? [{ value: partialYear, label: `${partialYear} (حتى ${MONTHS[(v?.month ?? 1) - 1]})` }] : [])]}
          />
        </div>

        {isLoading ? <Loading height={340} /> : error ? <ErrorState error={error} onRetry={() => refetch()} /> : items.length === 0 ? <Empty /> : (
          <>
            <RankedBars items={items} unitLabel="ريال عُماني" />
            <p className="muted" style={{ marginTop: 10, fontSize: 13 }}>
              أكبر 5 شركاء يمثلون <strong className="num">{formatNumber(top5Share * 100, 1)}%</strong> من الإجمالي.
              {data && <> إجمالي التدفق الرسمي: <strong className="num">{formatNumber(data.sum / 1e6, 0)}</strong> مليون ريال عُماني.</>}
            </p>
          </>
        )}
      </ChartCard>

      {selected && !isYtd && (
        <div>
          <div className="controls">
            <label className="field">
              تطور تجارة شريك:
              <select value={selected} onChange={(e) => setPartner(e.target.value)} aria-label="الشريك">
                {countries.map((c) => (
                  <option key={c.entity.code} value={c.entity.code}>
                    {entityName(c.entity)}
                  </option>
                ))}
              </select>
            </label>
          </div>
          <SeriesChart
            title={`${flowMeta.trend} ${entityName(countries.find((c) => c.entity.code === selected)!.entity)} عبر السنوات`}
            subtitle="السنوات الكاملة فقط"
            kind="columns"
            lines={[{ indicator: `manafeth.${flow}`, entity: selected, label: flowMeta.trend }]}
            last={4}
          />
        </div>
      )}
    </div>
  );
}
