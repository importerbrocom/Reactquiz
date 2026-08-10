/**
 * Mirrors backend PHP enums exactly.
 * Keep in sync with api/app/Enums/*.php
 */

export enum UserRole {
  Admin = 'admin',
  Student = 'student',
}

export enum UserStatus {
  Active = 'active',
  Suspended = 'suspended',
  PendingVerification = 'pending_verification',
}

export enum ContentStatus {
  Draft = 'draft',
  Active = 'active',
  Inactive = 'inactive',
  Archived = 'archived',
}

export enum Difficulty {
  Easy = 'easy',
  Medium = 'medium',
  Hard = 'hard',
}

export enum QuestionOption {
  A = 'a',
  B = 'b',
  C = 'c',
  D = 'd',
}

export enum AttemptStatus {
  InProgress = 'in_progress',
  Completed = 'completed',
  Abandoned = 'abandoned',
  Expired = 'expired',
}

export enum FinalTestAttemptStatus {
  InProgress = 'in_progress',
  Paused = 'paused',
  Submitted = 'submitted',
  Graded = 'graded',
  Expired = 'expired',
}

export enum UnlockMode {
  Immediate = 'immediate',
  NextCalendarDay = 'next_calendar_day',
  Scheduled = 'scheduled',
}

export enum RetryMode {
  RequeueAtEnd = 'requeue_at_end',
  Immediate = 'immediate',
}

export enum NotificationType {
  DailyReminder = 'daily_reminder',
  MissedQuiz = 'missed_quiz',
  Streak = 'streak',
  FinalTestUnlocked = 'final_test_unlocked',
  CourseCompleted = 'course_completed',
  Announcement = 'announcement',
  System = 'system',
}

export enum NotificationStatus {
  Queued = 'queued',
  Sent = 'sent',
  Delivered = 'delivered',
  Failed = 'failed',
  Dismissed = 'dismissed',
}

export enum DayStatus {
  Locked = 'locked',
  Available = 'available',
  InProgress = 'in_progress',
  Completed = 'completed',
}

/**
 * The dashboard tells the client what primary action to render.
 * The client holds NO progression logic of its own.
 */
export enum NextAction {
  StartDay = 'start_day',
  ResumeDay = 'resume_day',
  Locked = 'locked',
  TakeLevelTest = 'take_level_test',
  RetakeLevelTest = 'retake_level_test',
  AdvanceLevel = 'advance_level',
  ProgrammeComplete = 'programme_complete',
}

/** API error codes — machine-readable, used for branching in the client */
export enum ApiErrorCode {
  OnboardingRequired = 'ONBOARDING_REQUIRED',
  EnrolmentInactive = 'ENROLMENT_INACTIVE',
  QuizDayLocked = 'QUIZ_DAY_LOCKED',
  QuizNotComplete = 'QUIZ_NOT_COMPLETE',
  LevelTestNotEligible = 'LEVEL_TEST_NOT_ELIGIBLE',
  TestAttemptClosed = 'TEST_ATTEMPT_CLOSED',
  TestAttemptExpired = 'TEST_ATTEMPT_EXPIRED',
  LevelAdvanceNotAllowed = 'LEVEL_ADVANCE_NOT_ALLOWED',
  AttemptAlreadyCompleted = 'ATTEMPT_ALREADY_COMPLETED',
}

/** Offline answer sync status */
export enum PendingAnswerStatus {
  Pending = 'pending',
  Inflight = 'inflight',
  Failed = 'failed',
}

/** Onboarding steps */
export enum OnboardingStep {
  Welcome = 'welcome',
  Category = 'category',
  Course = 'course',
  Preferences = 'preferences',
  Confirmation = 'confirmation',
}
