import { Outlet, Navigate } from 'react-router';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';

/**
 * Layout for public auth pages (login, register, forgot password, etc.).
 * If the user is already authenticated, redirect them to dashboard.
 */
function AuthLayout() {
  const { isAuthenticated, isLoading } = useAuthStore();

  if (isLoading) return null;

  if (isAuthenticated) {
    return <Navigate to={ROUTES.DASHBOARD} replace />;
  }

  return (
    <div className="flex min-h-dvh flex-col items-center justify-center bg-surface-950 px-4 py-8">
      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="mb-8 text-center">
          <img
            src="/icons/icon-192.png"
            alt="ERO"
            className="mx-auto h-20 w-20 rounded-2xl object-cover"
          />
          <p className="mt-3 text-sm text-surface-400">Daily Exam Practice</p>
        </div>

        {/* Page content */}
        <Outlet />
      </div>
    </div>
  );
}

export const Component = AuthLayout;
export default AuthLayout;
