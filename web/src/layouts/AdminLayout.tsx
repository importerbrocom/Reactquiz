import { Outlet, NavLink } from 'react-router';
import { useAuthStore } from '@/store/auth.store';

/**
 * Admin layout — sidebar + topbar, desktop-first.
 * Admin data is never cached on a device (per steering).
 */
function AdminLayout() {
  const { user } = useAuthStore();

  return (
    <div className="flex min-h-dvh bg-surface-950">
      {/* Sidebar */}
      <aside className="fixed inset-y-0 left-0 z-30 w-64 overflow-y-auto border-r border-surface-800 bg-surface-900">
        <div className="flex h-16 items-center px-6">
          <h1 className="text-lg font-bold text-primary-400">QuizPath Admin</h1>
        </div>
        <nav className="space-y-1 px-3 pb-6" aria-label="Admin navigation">
          <AdminNavItem to="/admin" label="Dashboard" icon="📊" end />
          <AdminNavItem to="/admin/categories" label="Categories" icon="📁" />
          <AdminNavItem to="/admin/courses" label="Courses" icon="📚" />
          <AdminNavItem to="/admin/questions" label="Questions" icon="❓" />
          <AdminNavItem to="/admin/students" label="Students" icon="👥" />
          <AdminNavItem to="/admin/pdf-imports" label="PDF Imports" icon="📄" />
          <AdminNavItem to="/admin/notifications" label="Notifications" icon="🔔" />
          <AdminNavItem to="/admin/reports" label="Reports" icon="📈" />
          <AdminNavItem to="/admin/activity-logs" label="Activity Logs" icon="📋" />
        </nav>
      </aside>

      {/* Main area */}
      <div className="flex flex-1 flex-col pl-64">
        {/* Topbar */}
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-surface-800 bg-surface-950/95 px-6 backdrop-blur-sm">
          <div />
          <div className="flex items-center gap-3">
            <span className="text-sm text-surface-400">{user?.name}</span>
            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-500/15 text-xs font-bold text-primary-400">
              {user?.name?.charAt(0).toUpperCase()}
            </div>
          </div>
        </header>

        {/* Page content */}
        <main className="flex-1 p-6">
          <Outlet />
        </main>
      </div>
    </div>
  );
}

interface AdminNavItemProps {
  to: string;
  label: string;
  icon: string;
  end?: boolean;
}

function AdminNavItem({ to, label, icon, end }: AdminNavItemProps) {
  return (
    <NavLink
      to={to}
      end={end}
      className={({ isActive }) =>
        `flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
          isActive
            ? 'bg-primary-500/10 text-primary-400'
            : 'text-surface-400 hover:bg-surface-800 hover:text-surface-200'
        }`
      }
    >
      <span className="text-base">{icon}</span>
      {label}
    </NavLink>
  );
}

export const Component = AdminLayout;
export default AdminLayout;
