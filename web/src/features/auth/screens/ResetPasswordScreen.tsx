import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { useSearchParams, useNavigate } from 'react-router';
import { useMutation } from '@tanstack/react-query';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { resetPassword } from '@/api/endpoints/auth.api';
import { isApiError } from '@/api/interceptors/error.interceptor';
import { ROUTES } from '@/config/routes.config';

const schema = z
  .object({
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

type FormData = z.infer<typeof schema>;

function ResetPasswordScreen() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const token = searchParams.get('token') ?? '';
  const email = searchParams.get('email') ?? '';

  const { mutate, isPending, error } = useMutation({
    mutationFn: (data: FormData) =>
      resetPassword({ ...data, token, email }),
    onSuccess: () => {
      navigate(ROUTES.LOGIN, { replace: true });
    },
  });

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const serverError = error && isApiError(error) ? error.message : null;

  return (
    <div className="space-y-6">
      <div className="text-center">
        <h2 className="text-2xl font-bold text-white">Set new password</h2>
        <p className="mt-1 text-sm text-surface-400">Choose a strong password for your account</p>
      </div>

      {serverError && (
        <div className="rounded-lg bg-danger-500/10 border border-danger-500/30 px-4 py-3 text-sm text-danger-500" role="alert">
          {serverError}
        </div>
      )}

      <form onSubmit={handleSubmit((data) => mutate(data))} className="space-y-4">
        <Input
          label="New Password"
          type="password"
          autoComplete="new-password"
          placeholder="At least 8 characters"
          error={errors.password?.message}
          {...register('password')}
        />

        <Input
          label="Confirm Password"
          type="password"
          autoComplete="new-password"
          placeholder="Repeat your password"
          error={errors.password_confirmation?.message}
          {...register('password_confirmation')}
        />

        <Button type="submit" fullWidth loading={isPending}>
          Reset Password
        </Button>
      </form>
    </div>
  );
}

export const Component = ResetPasswordScreen;
export default ResetPasswordScreen;
