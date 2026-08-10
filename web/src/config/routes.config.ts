/**
 * Single source of truth for all route paths.
 * Used in both router definitions and link components.
 */
export const ROUTES = {
  // Public
  HOME: '/',
  LOGIN: '/login',
  REGISTER: '/register',
  FORGOT_PASSWORD: '/forgot-password',
  RESET_PASSWORD: '/reset-password',
  VERIFY_EMAIL: '/verify-email',

  // Onboarding
  ONBOARDING: '/onboarding',

  // Student
  DASHBOARD: '/dashboard',
  COURSES: '/courses',
  COURSE_DAYS: (courseId: string) => `/courses/${courseId}/days` as const,

  // Quiz
  QUIZ_DAY: (courseId: string, day: number) => `/courses/${courseId}/quiz/${day}` as const,
  QUIZ_RESULT: (attemptId: string) => `/quiz/attempts/${attemptId}/result` as const,

  // Final Test
  FINAL_TEST: (courseId: string) => `/courses/${courseId}/final-test` as const,
  FINAL_TEST_SESSION: (attemptId: string) => `/final-test/${attemptId}` as const,
  FINAL_TEST_RESULT: (attemptId: string) => `/final-test/${attemptId}/result` as const,

  // Progress & Mistakes
  PROGRESS: '/progress',
  MISTAKES: '/mistakes',

  // Notifications
  NOTIFICATIONS: '/notifications',

  // Profile
  PROFILE: '/profile',

  // Offline
  OFFLINE: '/offline',
} as const;
