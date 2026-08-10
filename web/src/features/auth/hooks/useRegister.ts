import { useMutation } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { register, type RegisterPayload } from '@/api/endpoints/auth.api';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';

export function useRegister() {
  const navigate = useNavigate();
  const { setUser } = useAuthStore();

  return useMutation({
    mutationFn: (payload: RegisterPayload) => register(payload),
    onSuccess: (data) => {
      setUser(data.user);
      // New users always go to onboarding
      navigate(ROUTES.ONBOARDING, { replace: true });
    },
  });
}
