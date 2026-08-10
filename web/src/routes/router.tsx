import { createBrowserRouter } from 'react-router';
import { RouteErrorBoundary } from './error/RouteErrorBoundary';
import { RequireAuth } from './guards/RequireAuth';
import { RequireOnboarded } from './guards/RequireOnboarded';
import { RequireRole } from './guards/RequireRole';
import { UserRole } from '@/types/enums';

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

          // ─── Admin routes ─────────────────────────────────────
          {
            element: <RequireRole role={UserRole.Admin} />,
            children: [
              {
                lazy: () => import('@/layouts/AdminLayout'),
                children: [
                  {
                    path: '/admin',
                    lazy: () => import('@/features/admin/dashboard/screens/AdminDashboardScreen'),
                  },
                  {
                    path: '/admin/categories',
                    lazy: () => import('@/features/admin/categories/screens/CategoriesScreen'),
                  },
                  {
                    path: '/admin/courses',
                    lazy: () => import('@/features/admin/courses/screens/CoursesScreen'),
                  },
                  {
                    path: '/admin/courses/:courseId',
                    lazy: () => import('@/features/admin/courses/screens/CourseDetailScreen'),
                  },
                  {
                    path: '/admin/questions',
                    lazy: () => import('@/features/admin/questions/screens/QuestionsScreen'),
                  },
                  {
                    path: '/admin/questions/:questionId',
                    lazy: () => import('@/features/admin/questions/screens/QuestionEditScreen'),
                  },
                  {
                    path: '/admin/students',
                    lazy: () => import('@/features/admin/students/screens/StudentsScreen'),
                  },
                  {
                    path: '/admin/students/:studentId',
                    lazy: () => import('@/features/admin/students/screens/StudentDetailScreen'),
                  },
                  {
                    path: '/admin/pdf-imports',
                    lazy: () => import('@/features/admin/pdf-imports/screens/PdfImportsScreen'),
                  },
                  {
                    path: '/admin/pdf-imports/:importId',
                    lazy: () => import('@/features/admin/pdf-imports/screens/PdfImportDetailScreen'),
                  },
                  {
                    path: '/admin/notifications',
                    lazy: () => import('@/features/admin/notifications/screens/NotificationCampaignsScreen'),
                  },
                  {
                    path: '/admin/reports',
                    lazy: () => import('@/features/admin/reports/screens/ReportsScreen'),
                  },
                  {
                    path: '/admin/activity-logs',
                    lazy: () => import('@/features/admin/reports/screens/ActivityLogsScreen'),
                  },
                ],
              },
            ],
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
