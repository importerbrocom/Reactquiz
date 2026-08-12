import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { RouterProvider } from 'react-router';
import { QueryClientProvider } from '@tanstack/react-query';
import { queryClient } from '@/config/query-client';
import { router } from '@/routes/router';
import { useAuthStore } from '@/store/auth.store';
import { getMe } from '@/api/endpoints/auth.api';
import { setAccessToken } from '@/api/interceptors/auth.interceptor';
import '@/styles/index.css';
import '@/styles/motion.css';

// Bootstrap: check auth state before rendering the app.
// This prevents the blank screen caused by RequireAuth returning null during isLoading.
async function bootstrap() {
  const { setUser, clearUser } = useAuthStore.getState();
  try {
    const user = await getMe();
    setUser(user);
  } catch {
    setAccessToken(null);
    clearUser();
  }
}

bootstrap().then(() => {
  createRoot(document.getElementById('root')!).render(
    <StrictMode>
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
      </QueryClientProvider>
    </StrictMode>,
  );
});
