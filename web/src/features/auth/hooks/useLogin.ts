import { useMutation } from '@tanstack/react-query';
import { useNavigate, useLocation } from 'react-router';
import { login, type LoginPayload } from '@/api/endpoints/auth.api';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';

export function useLogin() {
  const navigate = useNavigate();
  const location = useLocation();
  const { setUser } = useAuthStore();

  // Where to go after login — either the intended page or dashboard
  const from = (location.state as { from?: { pathname: string } })?.from?.pathname ?? ROUTES.DASHBOARD;

  return useMutation({
    mutationFn: (payload: LoginPayload) => login(payload),
    onSuccess: (data) => {
      setUser(data.user);
      navigate(from, { replace: true });
    },
  });
}
