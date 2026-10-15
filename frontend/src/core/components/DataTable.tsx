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
