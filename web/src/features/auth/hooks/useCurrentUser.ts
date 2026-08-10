import { useQuery } from '@tanstack/react-query';
import { useEffect } from 'react';
import { getMe } from '@/api/endpoints/auth.api';
import { queryKeys } from '@/api/query-keys';
import { useAuthStore } from '@/store/auth.store';

/**
 * Fetches and syncs the current user on app boot.
 * Called once at the root level to establish auth state.
 */
export function useCurrentUser() {
  const { setUser, clearUser, setLoading } = useAuthStore();

  const query = useQuery({
    queryKey: queryKeys.auth.me(),
    queryFn: getMe,
    retry: false,
    staleTime: 5 * 60 * 1000,
  });

  useEffect(() => {
    if (query.isSuccess) {
      setUser(query.data);
    } else if (query.isError) {
      clearUser();
    } else if (query.isLoading) {
      setLoading(true);
    }
  }, [query.isSuccess, query.isError, query.isLoading, query.data, setUser, clearUser, setLoading]);

  return query;
}
