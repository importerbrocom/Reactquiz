import { Navigate, Outlet } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getDashboard } from '@/api/endpoints/student-dashboard.api';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';
import { isApiError } from '@/api/interceptors/error.interceptor';
import { ApiErrorCode } from '@/types/enums';

/**
 * Guard: ensures the student has completed onboarding.
 * If the dashboard returns ONBOARDING_REQUIRED, redirects to onboarding flow.
 */
export function RequireOnboarded() {
  const { isAuthenticated } = useAuthStore();

  const { isLoading, isError, error } = useQuery({
    queryKey: queryKeys.dashboard.all,
    queryFn: getDashboard,
    enabled: isAuthenticated,
    retry: (failureCount, err) => {
      // Don't retry if it's an onboarding-required error
      if (isApiError(err) && err.code === ApiErrorCode.OnboardingRequired) return false;
      return failureCount < 2;
    },
  });

  if (isLoading) {
    return null; // Loading state handled by parent
  }

  if (isError && isApiError(error) && error.code === ApiErrorCode.OnboardingRequired) {
    return <Navigate to={ROUTES.ONBOARDING} replace />;
  }

  return <Outlet />;
}
