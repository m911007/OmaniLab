export interface TableData {
  columns: string[];
  rows: (string | number)[][];
}

/** عرض جدولي بديل للمخطط (إتاحة، وللطباعة، ولمن لا يميّز الألوان). */
export function DataTable({ columns, rows }: TableData) {
  return (
    <div className="table-scroll" style={{ maxHeight: 320, overflowY: 'auto' }}>
      <table>
        <thead>
          <tr>
            {columns.map((c) => (
              <th key={c}>{c}</th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((r, i) => (
            <tr key={i}>
              {r.map((cell, j) => (
                <td key={j} className={j > 0 ? 'num' : undefined}>
                  {cell}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

const NUMERIC_WITH_SEPARATORS = /^-?\d{1,3}(,\d{3})+(\.\d+)?$/;

/** CSV من الجدول: يُزال فاصل الآلاف من الأرقام (لتُقرأ رقمًا في Excel)، وتُحاط الخلايا التي فيها فاصلة أو علامة اقتباس أو سطر جديد. */
export function toCsv({ columns, rows }: TableData): string {
  const cell = (v: string | number): string => {
    const text = typeof v === 'string' && NUMERIC_WITH_SEPARATORS.test(v) ? v.replace(/,/g, '') : String(v);
    return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
  };
  return [columns, ...rows].map((r) => r.map(cell).join(',')).join('\r\n');
}
