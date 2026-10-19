import { useEffect, useState } from 'react';
import { NavLink, Outlet, useLocation } from 'react-router-dom';
import { Segmented } from '@/core/components/Segmented';
import { useAuth } from '@/core/auth';
import { formatNumber, relativeTime } from '@/core/format';
import { useOverview } from '@/core/hooks';
import { applyTheme, getStoredTheme, type ThemeMode } from '@/core/theme';
import { NAV } from './nav';

const COLLAPSED_KEY = 'omanilab.nav.collapsed';

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
    window.scrollTo(0, 0);
  }, [location.pathname]);

  const fresh = overview?.by_status.fresh ?? 0;
  const total = overview?.counts.datasets ?? 0;
  const allFresh = total > 0 && fresh === total;

  return (
    <div className="shell">
      {open && <div className="scrim" onClick={() => setOpen(false)} />}
      <aside className={`sidebar${open ? ' open' : ''}`} aria-label="التنقل الرئيسي">
        <div className="brand">
          <span className="brand-mark">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <path d="M4 17l5-6 4 4 7-9" />
            </svg>
          </span>
          <span>
            <div className="brand-name">المخبر العماني</div>
            <div className="brand-sub">بيانات واستخبارات اقتصادية</div>
          </span>
        </div>
        <nav>
          {NAV.map((g) => {
            const holdsActive = g.items.some((i) => (i.end ? location.pathname === i.to : i.to === '/' ? location.pathname === '/' : location.pathname.startsWith(i.to)));
            const isOpen = holdsActive || !collapsed[g.title];
            return (
              <div key={g.title} className="nav-section">
                <button type="button" className="nav-group" aria-expanded={isOpen} onClick={() => toggleGroup(g.title)}>
                  <span>{g.title}</span>
                  <span className="nav-chev" aria-hidden="true">{isOpen ? '▾' : '◂'}</span>
                </button>
                {isOpen &&
                  g.items.map((i) => (
                    <NavLink key={i.to} to={i.to} end={i.end} className={({ isActive }) => `nav-link${isActive ? ' active' : ''}`}>
                      {i.icon}
                      {i.label}
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
            <button className="btn menu-btn" onClick={() => setOpen(true)} aria-label="فتح القائمة">
              ☰
            </button>
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
