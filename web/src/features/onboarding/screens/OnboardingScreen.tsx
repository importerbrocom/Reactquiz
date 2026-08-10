import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useOnboardingState, useExamCategories } from '../hooks/useOnboardingState';
import { getCategoryCourses } from '@/api/endpoints/categories.api';
import {
  saveCategory,
  saveCourse,
  savePreferences,
  completeOnboarding,
} from '@/api/endpoints/onboarding.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { StepIndicator } from '../components/StepIndicator';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { PageSpinner } from '@/components/ui/Spinner';
import type { ExamCategory, Course } from '@/types/models';

const TOTAL_STEPS = 5;

function OnboardingScreen() {
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { data: state, isLoading: stateLoading } = useOnboardingState();
  const { data: categories, isLoading: catsLoading } = useExamCategories();

  const [step, setStep] = useState(1);
  const [selectedCategory, setSelectedCategory] = useState<ExamCategory | null>(null);
  const [courses, setCourses] = useState<Course[]>([]);
  const [selectedCourse, setSelectedCourse] = useState<Course | null>(null);
  const [coursesLoading, setCoursesLoading] = useState(false);

  // Resume from saved state
  useEffect(() => {
    if (!state) return;
    if (state.exam_category_id && categories) {
      const cat = categories.find((c) => c.id === state.exam_category_id);
      if (cat) setSelectedCategory(cat);
    }
    if (state.course_id) {
      setStep(state.preferences ? 5 : 4);
    } else if (state.exam_category_id) {
      setStep(3);
    }
  }, [state, categories]);

  // Fetch courses when category is selected
  useEffect(() => {
    if (!selectedCategory) return;
    setCoursesLoading(true);
    getCategoryCourses(selectedCategory.slug)
      .then(setCourses)
      .finally(() => setCoursesLoading(false));
  }, [selectedCategory]);

  const saveCategoryMutation = useMutation({
    mutationFn: (catId: string) => saveCategory({ exam_category_id: catId }),
    onSuccess: () => setStep(3),
  });

  const saveCourseMutation = useMutation({
    mutationFn: (courseId: string) => saveCourse({ course_id: courseId }),
    onSuccess: () => setStep(4),
  });

  const savePreferencesMutation = useMutation({
    mutationFn: () =>
      savePreferences({
        reminder_time: '08:00',
        notifications_opt_in: true,
        language: 'en',
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        study_goal: null,
      }),
    onSuccess: () => setStep(5),
  });

  const completeMutation = useMutation({
    mutationFn: () => completeOnboarding(),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.dashboard.all });
      navigate(ROUTES.DASHBOARD, { replace: true });
    },
  });

  if (stateLoading || catsLoading) return <PageSpinner />;

  return (
    <div className="space-y-6">
      <StepIndicator currentStep={step} totalSteps={TOTAL_STEPS} />

      {/* Step 1: Welcome */}
      {step === 1 && (
        <div className="space-y-6 text-center">
          <div className="mx-auto flex h-20 w-20 items-center justify-center rounded-2xl bg-primary-500/15">
            <span className="text-4xl">📚</span>
          </div>
          <div>
            <h2 className="text-2xl font-bold text-white">Welcome to QuizPath</h2>
            <p className="mt-2 text-surface-400">
              Master your medical licensing exam with daily practice.
              10 questions a day, every day, until you&apos;re ready.
            </p>
          </div>
          <Button fullWidth onClick={() => setStep(2)}>
            Get Started
          </Button>
        </div>
      )}

      {/* Step 2: Choose exam category */}
      {step === 2 && (
        <div className="space-y-4">
          <div className="text-center">
            <h2 className="text-xl font-bold text-white">Choose your exam</h2>
            <p className="mt-1 text-sm text-surface-400">Which exam are you preparing for?</p>
          </div>
          <div className="grid gap-3">
            {categories?.map((cat) => (
              <Card
                key={cat.id}
                variant="outlined"
                className={`cursor-pointer transition-all ${
                  selectedCategory?.id === cat.id
                    ? 'border-primary-500 bg-primary-500/5'
                    : 'hover:border-surface-600'
                }`}
                onClick={() => setSelectedCategory(cat)}
              >
                <div className="flex items-center gap-3">
                  {cat.icon_url && (
                    <img src={cat.icon_url} alt="" className="h-10 w-10 rounded-lg" />
                  )}
                  <div>
                    <h3 className="font-medium text-white">{cat.name}</h3>
                    {cat.description && (
                      <p className="text-xs text-surface-400">{cat.description}</p>
                    )}
                  </div>
                </div>
              </Card>
            ))}
          </div>
          <Button
            fullWidth
            disabled={!selectedCategory}
            loading={saveCategoryMutation.isPending}
            onClick={() => selectedCategory && saveCategoryMutation.mutate(selectedCategory.id)}
          >
            Continue
          </Button>
        </div>
      )}

      {/* Step 3: Choose course */}
      {step === 3 && (
        <div className="space-y-4">
          <div className="text-center">
            <h2 className="text-xl font-bold text-white">Choose your programme</h2>
            <p className="mt-1 text-sm text-surface-400">Select a study programme to begin</p>
          </div>
          {coursesLoading ? (
            <PageSpinner />
          ) : (
            <div className="grid gap-3">
              {courses.map((course) => (
                <Card
                  key={course.id}
                  variant="outlined"
                  className={`cursor-pointer transition-all ${
                    selectedCourse?.id === course.id
                      ? 'border-primary-500 bg-primary-500/5'
                      : 'hover:border-surface-600'
                  }`}
                  onClick={() => setSelectedCourse(course)}
                >
                  <h3 className="font-medium text-white">{course.name}</h3>
                  {course.description && (
                    <p className="mt-1 text-xs text-surface-400">{course.description}</p>
                  )}
                  <p className="mt-2 text-xs text-surface-500">
                    {course.total_questions} questions &middot; {course.duration_label}
                  </p>
                </Card>
              ))}
            </div>
          )}
          <div className="flex gap-3">
            <Button variant="ghost" onClick={() => setStep(2)}>
              Back
            </Button>
            <Button
              fullWidth
              disabled={!selectedCourse}
              loading={saveCourseMutation.isPending}
              onClick={() => selectedCourse && saveCourseMutation.mutate(selectedCourse.id)}
            >
              Continue
            </Button>
          </div>
        </div>
      )}

      {/* Step 4: Preferences */}
      {step === 4 && (
        <div className="space-y-4">
          <div className="text-center">
            <h2 className="text-xl font-bold text-white">Set your preferences</h2>
            <p className="mt-1 text-sm text-surface-400">We&apos;ll use these to personalise your experience</p>
          </div>
          <Card variant="outlined" className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-sm text-surface-200">Daily reminder</span>
              <span className="text-sm text-surface-400">8:00 AM</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-surface-200">Notifications</span>
              <span className="text-sm text-success-500">Enabled</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-surface-200">Timezone</span>
              <span className="text-sm text-surface-400">
                {Intl.DateTimeFormat().resolvedOptions().timeZone}
              </span>
            </div>
          </Card>
          <div className="flex gap-3">
            <Button variant="ghost" onClick={() => setStep(3)}>
              Back
            </Button>
            <Button
              fullWidth
              loading={savePreferencesMutation.isPending}
              onClick={() => savePreferencesMutation.mutate()}
            >
              Continue
            </Button>
          </div>
        </div>
      )}

      {/* Step 5: Confirmation */}
      {step === 5 && (
        <div className="space-y-4">
          <div className="text-center">
            <h2 className="text-xl font-bold text-white">Ready to start!</h2>
            <p className="mt-1 text-sm text-surface-400">Here&apos;s what you selected</p>
          </div>
          <Card variant="outlined" className="space-y-3">
            <div className="flex items-center justify-between">
              <span className="text-sm text-surface-400">Exam</span>
              <span className="text-sm font-medium text-white">{selectedCategory?.name}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-surface-400">Programme</span>
              <span className="text-sm font-medium text-white">{selectedCourse?.name}</span>
            </div>
            <div className="flex items-center justify-between">
              <span className="text-sm text-surface-400">Questions</span>
              <span className="text-sm text-surface-300">{selectedCourse?.total_questions}</span>
            </div>
          </Card>
          <div className="flex gap-3">
            <Button variant="ghost" onClick={() => setStep(4)}>
              Back
            </Button>
            <Button
              fullWidth
              loading={completeMutation.isPending}
              onClick={() => completeMutation.mutate()}
            >
              Start Course
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}

export const Component = OnboardingScreen;
export default OnboardingScreen;
