import { Navigate, Outlet, useLocation } from 'react-router';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';

/**
 * Guard: redirects unauthenticated users to login.
 * Preserves the intended destination so login can redirect back.
 *
 * NOTE: isLoading is resolved BEFORE the app renders (in main.tsx bootstrap),
 * so this guard should never see isLoading=true. The fallback is kept for safety.
 */
export function RequireAuth() {
  const { isAuthenticated, isLoading } = useAuthStore();
  const location = useLocation();

  if (isLoading) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0B1120' }}>
        <div style={{ color: '#818cf8', fontSize: '1.2rem' }}>Loading...</div>
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to={ROUTES.LOGIN} state={{ from: location }} replace />;
  }

  return <Outlet />;
}
