import { Navigate, Outlet } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getDashboard } from '@/api/endpoints/student-dashboard.api';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';
import { isApiError } from '@/api/interceptors/error.interceptor';
import { ApiErrorCode } from '@/types/enums';

/**
 * Guard: ensures the student has an active enrolment.
 * If not, redirects to onboarding to select a course.
 */
export function RequireEnrolment() {
  const { isAuthenticated } = useAuthStore();

  const { isError, error } = useQuery({
    queryKey: queryKeys.dashboard.all,
    queryFn: getDashboard,
    enabled: isAuthenticated,
    retry: false,
  });

  if (
    isError &&
    isApiError(error) &&
    (error.code === ApiErrorCode.EnrolmentInactive || error.code === ApiErrorCode.OnboardingRequired)
  ) {
    return <Navigate to={ROUTES.ONBOARDING} replace />;
  }

  return <Outlet />;
}
