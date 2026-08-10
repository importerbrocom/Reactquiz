import { Navigate, Outlet } from 'react-router';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';
import type { UserRole } from '@/types/enums';

interface RequireRoleProps {
  role: UserRole;
}

/**
 * Guard: ensures the authenticated user has the required role.
 * If not, redirects to dashboard (or login if somehow unauthenticated).
 */
export function RequireRole({ role }: RequireRoleProps) {
  const { user, isAuthenticated } = useAuthStore();

  if (!isAuthenticated || !user) {
    return <Navigate to={ROUTES.LOGIN} replace />;
  }

  if (user.role !== role) {
    return <Navigate to={ROUTES.DASHBOARD} replace />;
  }

  return <Outlet />;
}
