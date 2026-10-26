import { useQuery } from '@tanstack/react-query';
import { useEffect, useState } from 'react';
import { SearchPalette } from './SearchPalette';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { Segmented } from '@/core/components/Segmented';
import { api } from '@/core/api';
import { useAuth } from '@/core/auth';
import { formatNumber, relativeTime } from '@/core/format';
import { useOverview } from '@/core/hooks';
import { applyTheme, getStoredTheme, type ThemeMode } from '@/core/theme';
import { secretsQueryKey, type SecretStatus } from '@/modules/settings/SettingsPage';
import { NAV } from './nav';

const COLLAPSED_KEY = 'omanilab.nav.collapsed';
const RAIL_KEY = 'omanilab.nav.rail';

function loadCollapsed(): Record<string, boolean> {
  try {
    return JSON.parse(localStorage.getItem(COLLAPSED_KEY) ?? '{}') as Record<string, boolean>;
  } catch {
    return {};
  }
}

export function Shell() {
  const [open, setOpen] = useState(false);
  const [collapsed, setCollapsed] = useState<Record<string, boolean>>(loadCollapsed);
  // شريط مصغَّر: يبقى الأيقونات ظاهرة ويُخفي النصوص لتوسيع مساحة العمل.
  const [rail, setRail] = useState<boolean>(() => {
    try {
      return localStorage.getItem(RAIL_KEY) === '1';
    } catch {
      return false;
    }
  });
  const toggleRail = () =>
    setRail((r) => {
      try {
        localStorage.setItem(RAIL_KEY, r ? '0' : '1');
      } catch {
        /* بلا تخزين: يبقى للجلسة */
      }
      return !r;
    });
  const toggleGroup = (title: string) =>
    setCollapsed((c) => {
      const next = { ...c, [title]: !c[title] };
      try {
        localStorage.setItem(COLLAPSED_KEY, JSON.stringify(next));
      } catch {
        /* التخزين غير متاح: الطي يبقى لهذه الجلسة فقط */
      }
      return next;
    });
  const [theme, setTheme] = useState<ThemeMode>(getStoredTheme);
  const location = useLocation();
  const { data: overview } = useOverview();
  const { user, logout } = useAuth();

  useEffect(() => {
    applyTheme(theme);
  }, [theme]);
  useEffect(() => {
    setOpen(false);
  }, [location.pathname]);
  useEffect(() => {
    // رابط إلى قسم (#topics، /impact#oil-budget): ننتظر ظهوره بعد تحميل المحتوى ثم ننتقل إليه؛ وإلا إلى أعلى الصفحة
    const id = decodeURIComponent(location.hash.slice(1));
    if (!id) {
      window.scrollTo(0, 0);
      return;
    }
    let tries = 0;
    const timer = window.setInterval(() => {
      const el = document.getElementById(id);
      if (el || ++tries > 25) {
        window.clearInterval(timer);
        el?.scrollIntoView({ block: 'start' });
      }
    }, 200);
    return () => window.clearInterval(timer);
  }, [location.pathname, location.hash]);

  // قائمة المشرف: «مفاتيح الخدمات» تظهر بعد تسجيل الدخول، وعدد المفاتيح غير المضبوطة شارةٌ عليها
  const secrets = useQuery({ queryKey: secretsQueryKey, queryFn: ({ signal }) => api<{ data: SecretStatus[] }>('settings/secrets', {}, signal), enabled: !!user?.is_admin, staleTime: 60_000 });
  const missingKeys = secrets.data?.data.filter((s) => !s.configured).length ?? 0;
  const groups = NAV.map((g) => ({ ...g, items: g.items.filter((i) => !i.adminOnly || user?.is_admin) }));

  const fresh = overview?.by_status.fresh ?? 0;
  const total = overview?.counts.datasets ?? 0;
  const allFresh = total > 0 && fresh === total;

  return (
    <div className={`shell${rail ? ' rail' : ''}`}>
      {open && <div className="scrim" onClick={() => setOpen(false)} />}
      <aside className={`sidebar${open ? ' open' : ''}`} aria-label="التنقل الرئيسي">
        <div className="brand">
          <span className="brand-mark">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M4 17l5-6 4 4 7-9" />
            </svg>
          </span>
          <span className="brand-text">
            <div className="brand-name">المخبر العماني</div>
            <div className="brand-sub">بيانات واستخبارات اقتصادية</div>
          </span>
        </div>
        <nav>
          {groups.map((g) => {
            const holdsActive = g.items.some((i) => (i.end ? location.pathname === i.to : i.to === '/' ? location.pathname === '/' : location.pathname.startsWith(i.to)));
            const isOpen = rail || holdsActive || !collapsed[g.title];
            return (
              <div key={g.title} className="nav-section">
                <button type="button" className="nav-group" aria-expanded={isOpen} onClick={() => toggleGroup(g.title)}>
                  <span>{g.title}</span>
                  <span className="nav-chev" aria-hidden="true">{isOpen ? '▾' : '◂'}</span>
                </button>
                {isOpen &&
                  g.items.map((i) => (
                    <NavLink key={i.to} to={i.to} end={i.end} title={rail ? i.label : undefined} aria-label={i.label} className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>
                      {i.icon}
                      <span className="nav-label">{i.label}</span>
                      {i.to === '/settings' && missingKeys > 0 && <span className="nav-badge" title={`${missingKeys} مفاتيح غير مضبوطة`} aria-label={`${missingKeys} مفاتيح غير مضبوطة`}>{missingKeys}</span>}
                    </NavLink>
                  ))}
              </div>
            );
          })}
        </nav>
        {overview && (
          <div className="sidebar-status">
            <span className="status-dot" style={{ background: allFresh ? 'var(--good)' : 'var(--warning)' }} />
            <span>
              <b>{allFresh ? 'المصادر محدّثة' : 'بعض المصادر تحتاج انتباهًا'}</b>
              <small>آخر فحص {relativeTime(overview.last_checked_at)}</small>
            </span>
          </div>
        )}
      </aside>

      <div className="main">
        <div className="topbar">
          <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
            <button type="button" className="nav-toggle" onClick={toggleRail} aria-pressed={!rail} aria-label={rail ? 'إظهار الشريط الجانبي كاملًا' : 'طيّ الشريط الجانبي إلى أيقونات'} title={rail ? 'إظهار الشريط الجانبي' : 'طيّ الشريط الجانبي'}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="3" />
                <path d="M15 4v16" />
                {rail ? <path d="M9 9l3 3-3 3" /> : <path d="M11 9l-3 3 3 3" />}
              </svg>
            </button>
            <button className="btn menu-btn" onClick={() => setOpen(true)} aria-label="فتح القائمة">
              ☰
            </button>
            <SearchPalette />
            {overview && (
              <NavLink to="/status" className={`chip ${allFresh ? 'good' : 'warning'}`} title="حالة مصادر البيانات">
                <span aria-hidden="true">{allFresh ? '✓' : '!'}</span>
                {formatNumber(fresh, 0)} من {formatNumber(total, 0)} مجموعة محدّثة · آخر فحص {relativeTime(overview.last_checked_at)}
              </NavLink>
            )}
          </div>
          <div className="user-menu">
            {user ? (
              <>
                <NavLink to="/requests" className="chip good" title="طلباتي">
                  {user.name}{user.is_admin ? ' · مشرف' : ''}
                </NavLink>
                {user.is_admin && (
                  <NavLink to="/settings" className="btn" title="مفاتيح الخدمات">
                    الإعدادات
                  </NavLink>
                )}
                <button className="btn" onClick={() => void logout()}>
                  خروج
                </button>
              </>
            ) : (
              <NavLink to="/login" className="btn">
                دخول
              </NavLink>
            )}
          </div>
          <Segmented
            label="المظهر"
            value={theme}
            onChange={setTheme}
            options={[
              { value: 'light', label: 'فاتح' },
              { value: 'dark', label: 'داكن' },
            ]}
          />
        </div>
        <Outlet />
      </div>
    </div>
  );
}
