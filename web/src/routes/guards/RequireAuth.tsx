import { Navigate, Outlet, useLocation } from 'react-router';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';

/**
 * Guard: redirects unauthenticated users to login.
 * Preserves the intended destination so login can redirect back.
 */
export function RequireAuth() {
  const { isAuthenticated, isLoading } = useAuthStore();
  const location = useLocation();

  if (isLoading) {
    // TODO: replace with skeleton/spinner once UI components exist
    return null;
  }

  if (!isAuthenticated) {
    return <Navigate to={ROUTES.LOGIN} state={{ from: location }} replace />;
  }

  return <Outlet />;
}
