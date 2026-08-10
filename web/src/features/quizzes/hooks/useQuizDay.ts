import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getQuizDay, startOrResumeAttempt, completeQuiz } from '@/api/endpoints/quiz.api';

export function useQuizDay(courseId: string, day: number) {
  return useQuery({
    queryKey: queryKeys.quiz.day(courseId, day),
    queryFn: () => getQuizDay(courseId, day),
  });
}

export function useStartAttempt(courseId: string, day: number) {
  return useMutation({
    mutationFn: () => startOrResumeAttempt(courseId, day),
  });
}

export function useCompleteQuiz() {
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (attemptUuid: string) => completeQuiz(attemptUuid),
    onSuccess: () => {
      // Invalidate dashboard and days so they refresh
      queryClient.invalidateQueries({ queryKey: queryKeys.dashboard.all });
      queryClient.invalidateQueries({ queryKey: queryKeys.days.all });
    },
  });
}
