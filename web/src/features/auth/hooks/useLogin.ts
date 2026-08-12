import { useMutation } from '@tanstack/react-query';
import { useNavigate, useLocation } from 'react-router';
import { login, type LoginPayload } from '@/api/endpoints/auth.api';
import { useAuthStore } from '@/store/auth.store';
import { ROUTES } from '@/config/routes.config';
import { UserRole } from '@/types/enums';

export function useLogin() {
  const navigate = useNavigate();
  const location = useLocation();
  const { setUser } = useAuthStore();

  // Where to go after login — admins go to /admin, students to dashboard
  const from = (location.state as { from?: { pathname: string } })?.from?.pathname;

  return useMutation({
    mutationFn: (payload: LoginPayload) => login(payload),
    onSuccess: (data) => {
      setUser(data.user);
      const destination = from ?? (data.user.role === UserRole.Admin ? '/admin' : ROUTES.DASHBOARD);
      navigate(destination, { replace: true });
    },
  });
}
