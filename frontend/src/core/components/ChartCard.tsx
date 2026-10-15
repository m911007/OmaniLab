import { useState, type ReactNode } from 'react';
import type { Dataset } from '../api';
import { DataTable, toCsv, type TableData } from './DataTable';
import { Segmented } from './Segmented';
import { SourceNote } from './SourceNote';

export interface ViewOption {
  value: string;
  label: string;
}

interface Props {
  title: string;
  subtitle?: string;
  datasets: Dataset[];
  /** المحتوى، أو دالة تستلم العرض الحالي (للمخططات متعددة الأوضاع) */
  children: ReactNode | ((view: string) => ReactNode);
  /** أوضاع العرض؛ الافتراضي مخطط/جدول. آخر وضع اسمه table يعرض الجدول البديل. */
  views?: ViewOption[];
  /** وضع العرض الابتدائي */
  defaultView?: string;
  /** محتوى إضافي يظهر في وضع المقارنة (اختيار دول…) */
  extra?: (view: string) => ReactNode;
  /** يُستدعى عند تغيير الوضع (لمن يريد جلب بيانات مختلفة لكل وضع) */
  onViewChange?: (view: string) => void;
  /** جدول بديل؛ يظهر مفتاح «مخطط / جدول» عند توفره */
  table?: TableData;
  note?: string;
  /** تحذير بارز (مثل: السنة غير مكتملة) */
  callout?: string;
  actions?: ReactNode;
}

/** تنزيل جدول المخطط كملف CSV (بعلامة UTF-8 كي يفتحه Excel بالعربية سليمًا). */
function downloadCsv(title: string, table: TableData): void {
  const blob = new Blob([`\uFEFF${toCsv(table)}`], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `${title.replace(/[\\/:*?"<>|\s]+/g, '-')}.csv`;
  a.click();
  URL.revokeObjectURL(url);
}

export function ChartCard({ title, subtitle, datasets, children, table, note, callout, actions, views, defaultView, extra, onViewChange }: Props) {
  const options = views ?? [
    { value: 'chart', label: 'مخطط' },
    { value: 'table', label: 'جدول' },
  ];
  const [view, setView] = useState<string>(defaultView ?? options[0].value);
  return (
    <section className="card" aria-label={title}>
      <div className="card-head">
        <div>
          <h3 className="card-title">{title}</h3>
          {subtitle && <div className="card-sub">{subtitle}</div>}
        </div>
        <div style={{ display: 'flex', gap: 8, alignItems: 'center' }}>
          {actions}
          {table && (
            <Segmented
              label="طريقة العرض"
              value={view}
              onChange={(v) => {
                setView(v);
                onViewChange?.(v);
              }}
              options={options}
            />
          )}
        </div>
      </div>
      {callout && (
        <div className="callout" role="note" style={{ marginBottom: 10 }}>
          {callout}
        </div>
      )}
      {view === 'table' && table ? (
        <>
          <DataTable {...table} />
          <button className="btn csv-btn" onClick={() => downloadCsv(title, table)}>
            تنزيل CSV
          </button>
        </>
      ) : (
        <>
          {extra?.(view)}
          {typeof children === 'function' ? children(view) : children}
        </>
      )}
      {datasets.length > 0 && <SourceNote datasets={datasets} note={note} />}
    </section>
  );
}
