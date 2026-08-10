import { Outlet, NavLink } from 'react-router';
import { useNetworkStore } from '@/store/network.store';
import { ROUTES } from '@/config/routes.config';

/**
 * Student layout — bottom nav (mobile) / sidebar (desktop).
 * Houses the network status banner and the main navigation.
 */
function StudentLayout() {
  const { isOnline, pendingSyncCount } = useNetworkStore();

  return (
    <div className="flex min-h-dvh flex-col bg-surface-950">
      {/* Network status banner */}
      {!isOnline && (
        <div
          className="bg-warning-600/90 px-4 py-2 text-center text-sm font-medium text-surface-950"
          role="alert"
          aria-live="polite"
        >
          Offline — your answers are being saved
          {pendingSyncCount > 0 && ` (${pendingSyncCount} pending)`}
        </div>
      )}

      {/* Main content */}
      <main className="flex-1 overflow-y-auto pb-20 lg:pb-0 lg:pl-64">
        <div className="mx-auto max-w-3xl px-4 py-6">
          <Outlet />
        </div>
      </main>

      {/* Bottom navigation (mobile) */}
      <nav
        className="fixed inset-x-0 bottom-0 z-40 border-t border-surface-800 bg-surface-900/95 backdrop-blur-sm lg:fixed lg:inset-y-0 lg:left-0 lg:top-0 lg:w-64 lg:border-r lg:border-t-0"
        aria-label="Main navigation"
      >
        <div className="flex h-16 items-center justify-around lg:h-full lg:flex-col lg:items-stretch lg:justify-start lg:gap-1 lg:px-3 lg:pt-6">
          {/* Logo (desktop sidebar only) */}
          <div className="hidden lg:mb-8 lg:block lg:px-3">
            <h2 className="text-xl font-bold text-primary-400">QuizPath</h2>
          </div>

          <NavItem to={ROUTES.DASHBOARD} icon={<HomeIcon />} label="Home" />
          <NavItem to={ROUTES.PROGRESS} icon={<ChartIcon />} label="Progress" />
          <NavItem to={ROUTES.MISTAKES} icon={<ListIcon />} label="Mistakes" />
          <NavItem to={ROUTES.NOTIFICATIONS} icon={<BellIcon />} label="Alerts" />
          <NavItem to={ROUTES.PROFILE} icon={<UserIcon />} label="Profile" />
        </div>
      </nav>
    </div>
  );
}

interface NavItemProps {
  to: string;
  icon: React.ReactNode;
  label: string;
}

function NavItem({ to, icon, label }: NavItemProps) {
  return (
    <NavLink
      to={to}
      className={({ isActive }) =>
        `flex min-h-[44px] min-w-[44px] flex-col items-center justify-center gap-0.5 rounded-lg px-3 py-2 text-xs transition-colors lg:flex-row lg:justify-start lg:gap-3 lg:text-sm ${
          isActive
            ? 'text-primary-400'
            : 'text-surface-400 hover:text-surface-200'
        }`
      }
    >
      {icon}
      <span>{label}</span>
    </NavLink>
  );
}

// ─── Inline SVG icons (lightweight, no external dep) ──────────────────────────

function HomeIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
    </svg>
  );
}

function ChartIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
    </svg>
  );
}

function ListIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
    </svg>
  );
}

function BellIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
    </svg>
  );
}

function UserIcon() {
  return (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
      <path strokeLinecap="round" strokeLinejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
    </svg>
  );
}

export const Component = StudentLayout;
export default StudentLayout;
