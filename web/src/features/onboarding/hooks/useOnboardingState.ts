import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getOnboardingState } from '@/api/endpoints/onboarding.api';
import { getCategories } from '@/api/endpoints/categories.api';

export function useOnboardingState() {
  return useQuery({
    queryKey: queryKeys.onboarding.state(),
    queryFn: getOnboardingState,
  });
}

export function useExamCategories() {
  return useQuery({
    queryKey: queryKeys.categories.list(),
    queryFn: getCategories,
  });
}
