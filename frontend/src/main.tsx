import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './app/App';
import './design/fonts.css';
import './design/tokens.css';
import './design/base.css';

// بعد نشر إصدار جديد تتغير أسماء ملفات الصفحات؛ الصفحة المفتوحة من الإصدار القديم تفشل في تحميلها.
// نعيد التحميل مرة واحدة لجلب الإصدار الجديد (والعلامة تمنع حلقة إعادة تحميل إن كان الخلل غير ذلك).
window.addEventListener('vite:preloadError', (event) => {
  try {
    if (sessionStorage.getItem('reloaded-for-new-version')) return;
    sessionStorage.setItem('reloaded-for-new-version', '1');
  } catch {
    // التخزين غير متاح: نعيد التحميل مرة دون علامة
  }
  event.preventDefault();
  window.location.reload();
});
window.addEventListener('load', () => {
  // تحميل ناجح: تُمسح العلامة ليعمل الإصلاح مع النشر التالي
  setTimeout(() => {
    try {
      sessionStorage.removeItem('reloaded-for-new-version');
    } catch {
      // لا شيء
    }
  }, 10_000);
});

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <App />
  </StrictMode>,
);
