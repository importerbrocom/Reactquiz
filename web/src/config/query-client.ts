import { QueryClient } from '@tanstack/react-query';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 5 * 60 * 1000, // 5 minutes
      gcTime: 30 * 60 * 1000, // 30 minutes
      retry: (failureCount, error) => {
        // Don't retry on 4xx errors (client mistakes)
        if (error && 'status' in error && typeof error.status === 'number') {
          if (error.status >= 400 && error.status < 500) return false;
        }
        return failureCount < 3;
      },
      refetchOnWindowFocus: false,
    },
    mutations: {
      retry: false,
    },
  },
});
