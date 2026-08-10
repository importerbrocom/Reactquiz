import { createBrowserRouter } from 'react-router';
import { RouteErrorBoundary } from './error/RouteErrorBoundary';
import { RequireAuth } from './guards/RequireAuth';
import { RequireOnboarded } from './guards/RequireOnboarded';

/**
 * Application router — all route modules are lazy-loaded.
 * Layout hierarchy:
 *   RootLayout → AuthLayout (public)
 *   RootLayout → RequireAuth → OnboardingLayout
 *   RootLayout → RequireAuth → RequireOnboarded → StudentLayout (bottom nav)
 *   RootLayout → RequireAuth → RequireOnboarded → QuizLayout (distraction-free)
 */
export const router = createBrowserRouter([
  {
    errorElement: <RouteErrorBoundary />,
    children: [
      // ─── Public / Auth routes ──────────────────────────────────
      {
        lazy: () => import('@/layouts/AuthLayout'),
        children: [
          {
            path: '/login',
            lazy: () => import('@/features/auth/screens/LoginScreen'),
          },
          {
            path: '/register',
            lazy: () => import('@/features/auth/screens/RegisterScreen'),
          },
          {
            path: '/forgot-password',
            lazy: () => import('@/features/auth/screens/ForgotPasswordScreen'),
          },
          {
            path: '/reset-password',
            lazy: () => import('@/features/auth/screens/ResetPasswordScreen'),
          },
          {
            path: '/verify-email',
            lazy: () => import('@/features/auth/screens/VerifyEmailScreen'),
          },
        ],
      },

      // ─── Authenticated routes ──────────────────────────────────
      {
        element: <RequireAuth />,
        children: [
          // Onboarding (no bottom nav)
          {
            path: '/onboarding',
            lazy: () => import('@/features/onboarding/screens/OnboardingScreen'),
          },

          // Student routes (require onboarding complete)
          {
            element: <RequireOnboarded />,
            children: [
              // StudentLayout — bottom nav / sidebar
              {
                lazy: () => import('@/layouts/StudentLayout'),
                children: [
                  {
                    path: '/',
                    lazy: () => import('@/features/student-dashboard/screens/DashboardScreen'),
                  },
                  {
                    path: '/dashboard',
                    lazy: () => import('@/features/student-dashboard/screens/DashboardScreen'),
                  },
                  {
                    path: '/courses/:courseId/days',
                    lazy: () => import('@/features/student-dashboard/screens/DaysScreen'),
                  },
                  {
                    path: '/progress',
                    lazy: () => import('@/features/progress/screens/ProgressScreen'),
                  },
                  {
                    path: '/mistakes',
                    lazy: () => import('@/features/mistakes/screens/MistakesScreen'),
                  },
                  {
                    path: '/notifications',
                    lazy: () => import('@/features/notifications/screens/NotificationsScreen'),
                  },
                  {
                    path: '/profile',
                    lazy: () => import('@/features/profile/screens/ProfileScreen'),
                  },
                ],
              },

              // QuizLayout — distraction-free, no bottom nav
              {
                lazy: () => import('@/layouts/QuizLayout'),
                children: [
                  {
                    path: '/courses/:courseId/quiz/:day',
                    lazy: () => import('@/features/quizzes/screens/DailyQuizScreen'),
                  },
                  {
                    path: '/quiz/attempts/:attemptId/result',
                    lazy: () => import('@/features/quizzes/screens/QuizResultScreen'),
                  },
                  {
                    path: '/courses/:courseId/final-test',
                    lazy: () => import('@/features/final-test/screens/FinalTestScreen'),
                  },
                  {
                    path: '/final-test/:attemptId',
                    lazy: () => import('@/features/final-test/screens/FinalTestSessionScreen'),
                  },
                  {
                    path: '/final-test/:attemptId/result',
                    lazy: () => import('@/features/final-test/screens/FinalTestResultScreen'),
                  },
                ],
              },
            ],
          },
        ],
      },
    ],
  },
]);
