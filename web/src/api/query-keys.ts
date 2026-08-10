/**
 * ALL TanStack Query key factories in one file.
 * Centralised so invalidation never misses a key that a component invented inline.
 */
export const queryKeys = {
  // Auth
  auth: {
    all: ['auth'] as const,
    me: () => [...queryKeys.auth.all, 'me'] as const,
    devices: () => [...queryKeys.auth.all, 'devices'] as const,
  },

  // Bootstrap / public
  bootstrap: {
    all: ['bootstrap'] as const,
  },
  categories: {
    all: ['categories'] as const,
    list: () => [...queryKeys.categories.all, 'list'] as const,
    courses: (slug: string) => [...queryKeys.categories.all, slug, 'courses'] as const,
  },
  courses: {
    all: ['courses'] as const,
    detail: (slug: string) => [...queryKeys.courses.all, slug] as const,
  },

  // Onboarding
  onboarding: {
    all: ['onboarding'] as const,
    state: () => [...queryKeys.onboarding.all, 'state'] as const,
  },

  // Student dashboard
  dashboard: {
    all: ['dashboard'] as const,
  },

  // Days timeline
  days: {
    all: ['days'] as const,
    list: (courseId: string) => [...queryKeys.days.all, courseId] as const,
    detail: (courseId: string, day: number) => [...queryKeys.days.all, courseId, day] as const,
  },

  // Quiz attempts
  quiz: {
    all: ['quiz'] as const,
    day: (courseId: string, day: number) => [...queryKeys.quiz.all, courseId, 'day', day] as const,
    attempt: (uuid: string) => [...queryKeys.quiz.all, 'attempt', uuid] as const,
    result: (uuid: string) => [...queryKeys.quiz.all, 'result', uuid] as const,
  },

  // Final test
  finalTest: {
    all: ['finalTest'] as const,
    eligibility: (courseId: string) => [...queryKeys.finalTest.all, courseId, 'eligibility'] as const,
    attempt: (uuid: string) => [...queryKeys.finalTest.all, 'attempt', uuid] as const,
    questions: (uuid: string, position: number) =>
      [...queryKeys.finalTest.all, 'questions', uuid, position] as const,
    navigator: (uuid: string) => [...queryKeys.finalTest.all, 'navigator', uuid] as const,
    result: (uuid: string) => [...queryKeys.finalTest.all, 'result', uuid] as const,
  },

  // Progress
  progress: {
    all: ['progress'] as const,
    overview: (courseId: string) => [...queryKeys.progress.all, courseId, 'overview'] as const,
    calendar: (courseId: string) => [...queryKeys.progress.all, courseId, 'calendar'] as const,
    charts: (courseId: string) => [...queryKeys.progress.all, courseId, 'charts'] as const,
    topics: (courseId: string) => [...queryKeys.progress.all, courseId, 'topics'] as const,
  },

  // Mistakes
  mistakes: {
    all: ['mistakes'] as const,
    list: (params?: Record<string, unknown>) => [...queryKeys.mistakes.all, 'list', params] as const,
  },

  // Notifications
  notifications: {
    all: ['notifications'] as const,
    list: () => [...queryKeys.notifications.all, 'list'] as const,
    unreadCount: () => [...queryKeys.notifications.all, 'unread'] as const,
  },

  // Level advancement
  levels: {
    all: ['levels'] as const,
    next: () => [...queryKeys.levels.all, 'next'] as const,
  },

  // Profile
  profile: {
    all: ['profile'] as const,
  },
} as const;
