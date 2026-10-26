import { createContext, useContext, type ReactNode } from 'react';
import { usePageTitle } from '../hooks';

/** داخل صفحة مجمِّعة (HubPage) تُعرض الصفحة كتبويب: يبقى عنوان الصفحة الأم، ويظهر وصف التبويب فقط. */
export const EmbeddedContext = createContext(false);

export function PageHead({ title, lead, children }: { title: string; lead?: string; children?: ReactNode }) {
  const embedded = useContext(EmbeddedContext);
  usePageTitle(title, !embedded);
  if (embedded) {
    return lead || children ? (
      <div className="hub-tab-lead">
        {lead && <p>{lead}</p>}
        {children}
      </div>
    ) : null;
  }
  return (
    <header className="page-head">
      <h1>{title}</h1>
      {lead && <p>{lead}</p>}
      {children}
    </header>
  );
}

export function Section({ id, title, lead, children }: { id?: string; title: string; lead?: string; children: ReactNode }) {
  return (
    <section className="section" id={id} aria-labelledby={id ? `${id}-h` : undefined}>
      <h2 id={id ? `${id}-h` : undefined}>{title}</h2>
      {lead && <p className="lead">{lead}</p>}
      <div className="grid cols-2" style={{ marginTop: lead ? 0 : 12 }}>
        {children}
      </div>
    </section>
  );
}
