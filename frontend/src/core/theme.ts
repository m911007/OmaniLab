export type ThemeMode = 'auto' | 'light' | 'dark';

const KEY = 'omanilab.theme';

export function getStoredTheme(): ThemeMode {
  try {
    const v = window.localStorage.getItem(KEY);
    return v === 'light' ? 'light' : 'dark';
  } catch {
    return 'dark';
  }
}

export function applyTheme(mode: ThemeMode): void {
  const root = document.documentElement;
  if (mode === 'light') root.setAttribute('data-theme', 'light');
  else root.removeAttribute('data-theme');
  try {
    window.localStorage.setItem(KEY, mode);
  } catch {
    /* التخزين محجوب (نافذة خاصة): يعمل بلا حفظ */
  }
}
