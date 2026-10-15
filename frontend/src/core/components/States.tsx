import type { ReactNode } from 'react';

export function Loading({ height = 220 }: { height?: number }) {
  return <div className="skeleton" style={{ height }} role="status" aria-label="جارٍ التحميل" />;
}

export function ErrorState({ error, onRetry }: { error: Error; onRetry?: () => void }) {
  return (
    <div className="state" role="alert">
      <strong>تعذّر تحميل البيانات</strong>
      <span className="muted">{error.message}</span>
      {onRetry && (
        <button className="btn" onClick={onRetry}>
          إعادة المحاولة
        </button>
      )}
    </div>
  );
}

export function Empty({ children = 'لا توجد بيانات لهذا الاختيار.' }: { children?: ReactNode }) {
  return <div className="state">{children}</div>;
}
