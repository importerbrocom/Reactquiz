import { useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { logout } from '@/api/endpoints/auth.api';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';

export function useLogout() {
  const navigate = useNavigate();
  const { clearUser } = useAuthStore();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => logout(),
    onSettled: () => {
      // Clear regardless of success — user wants out
      clearUser();
      queryClient.clear();
      navigate(ROUTES.LOGIN, { replace: true });
    },
  });
}
