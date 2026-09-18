import React, { useEffect } from 'react';
import { registerRootComponent } from 'expo';
import { StatusBar } from 'expo-status-bar';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { AppNavigator } from './navigation/AppNavigator';
import { useAuthStore } from './store/auth.store';
import { apiClient, setAccessToken } from './api/client';
import * as SecureStore from 'expo-secure-store';

const queryClient = new QueryClient({
  defaultOptions: {
    queries: { staleTime: 5 * 60 * 1000, retry: 2 },
    mutations: { retry: false },
  },
});

function AuthBootstrap({ children }: { children: React.ReactNode }) {
  const { setUser, clearUser, setLoading } = useAuthStore();

  useEffect(() => {
    async function bootstrap() {
      try {
        const refreshToken = await SecureStore.getItemAsync('refresh_token');
        if (!refreshToken) {
          clearUser();
          return;
        }

        // Try to get a fresh access token
        const { data } = await apiClient.post('/auth/refresh', null, {
          headers: { 'X-Refresh-Token': refreshToken },
        });

        setAccessToken(data.data.access_token);
        if (data.data.refresh_token) {
          await SecureStore.setItemAsync('refresh_token', data.data.refresh_token);
        }

        // Fetch user
        const meRes = await apiClient.get('/auth/me');
        setUser(meRes.data.data);
      } catch {
        clearUser();
      }
    }

    setLoading(true);
    bootstrap();
  }, []);

  return <>{children}</>;
}

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthBootstrap>
        <StatusBar style="light" />
        <AppNavigator />
      </AuthBootstrap>
    </QueryClientProvider>
  );
}

// Register the root component so this file works as the app entry
// ("main": "src/App.tsx" in package.json). Without this, the JS bundle
// builds but no root component is registered with React Native.
registerRootComponent(App);
