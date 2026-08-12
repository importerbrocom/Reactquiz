import { Navigate, Outlet } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getDashboard } from '@/api/endpoints/student-dashboard.api';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';
import { isApiError } from '@/api/interceptors/error.interceptor';
import { ApiErrorCode, UserRole } from '@/types/enums';

/**
 * Guard: ensures the student has completed onboarding.
 * Admin users bypass this check entirely.
 */
export function RequireOnboarded() {
  const { isAuthenticated, user } = useAuthStore();

  // Admin users skip onboarding check
  const isAdmin = user?.role === UserRole.Admin;

  const { isLoading, isError, error } = useQuery({
    queryKey: queryKeys.dashboard.all,
    queryFn: getDashboard,
    enabled: isAuthenticated && !isAdmin,
    retry: (failureCount, err) => {
      if (isApiError(err) && err.code === ApiErrorCode.OnboardingRequired) return false;
      return failureCount < 2;
    },
  });

  // Admin bypasses — let them through
  if (isAdmin) {
    return <Outlet />;
  }

  if (isLoading) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', background: '#0B1120' }}>
        <div style={{ color: '#818cf8', fontSize: '1.2rem' }}>Loading...</div>
      </div>
    );
  }

  if (isError && isApiError(error) && error.code === ApiErrorCode.OnboardingRequired) {
    return <Navigate to={ROUTES.ONBOARDING} replace />;
  }

  return <Outlet />;
}
