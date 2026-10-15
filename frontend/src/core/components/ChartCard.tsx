import { useState, type ReactNode } from 'react';
import type { Dataset } from '../api';
import { DataTable, type TableData } from './DataTable';
import { Segmented } from './Segmented';
import { SourceNote } from './SourceNote';

interface Props {
  title: string;
  subtitle?: string;
  datasets: Dataset[];
  children: ReactNode;
  /** جدول بديل؛ يظهر مفتاح «مخطط / جدول» عند توفره */
  table?: TableData;
  note?: string;
  /** تحذير بارز (مثل: السنة غير مكتملة) */
  callout?: string;
  actions?: ReactNode;
}

export function ChartCard({ title, subtitle, datasets, children, table, note, callout, actions }: Props) {
  const [view, setView] = useState<'chart' | 'table'>('chart');
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
              onChange={setView}
              options={[
                { value: 'chart', label: 'مخطط' },
                { value: 'table', label: 'جدول' },
              ]}
            />
          )}
        </div>
      </div>
      {callout && (
        <div className="callout" role="note" style={{ marginBottom: 10 }}>
          {callout}
        </div>
      )}
      {view === 'table' && table ? <DataTable {...table} /> : children}
      {datasets.length > 0 && <SourceNote datasets={datasets} note={note} />}
    </section>
  );
}
