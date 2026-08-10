import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link } from 'react-router';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useLogin } from '../hooks/useLogin';
import { isApiError } from '@/api/interceptors/error.interceptor';
import { ROUTES } from '@/config/routes.config';

const loginSchema = z.object({
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
});

type LoginFormData = z.infer<typeof loginSchema>;

function LoginScreen() {
  const { mutate, isPending, error } = useLogin();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<LoginFormData>({
    resolver: zodResolver(loginSchema),
  });

  const onSubmit = (data: LoginFormData) => {
    mutate(data);
  };

  const serverError = error && isApiError(error) ? error.message : null;

  return (
    <div className="space-y-6">
      <div className="text-center">
        <h2 className="text-2xl font-bold text-white">Welcome back</h2>
        <p className="mt-1 text-sm text-surface-400">Sign in to continue your practice</p>
      </div>

      {serverError && (
        <div className="rounded-lg bg-danger-500/10 border border-danger-500/30 px-4 py-3 text-sm text-danger-500" role="alert">
          {serverError}
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label="Email"
          type="email"
          autoComplete="email"
          placeholder="you@example.com"
          error={errors.email?.message}
          {...register('email')}
        />

        <Input
          label="Password"
          type="password"
          autoComplete="current-password"
          placeholder="Enter your password"
          error={errors.password?.message}
          {...register('password')}
        />

        <div className="text-right">
          <Link
            to={ROUTES.FORGOT_PASSWORD}
            className="text-sm text-primary-400 hover:text-primary-300"
          >
            Forgot password?
          </Link>
        </div>

        <Button type="submit" fullWidth loading={isPending}>
          Sign In
        </Button>
      </form>

      <p className="text-center text-sm text-surface-400">
        Don&apos;t have an account?{' '}
        <Link to={ROUTES.REGISTER} className="font-medium text-primary-400 hover:text-primary-300">
          Sign up
        </Link>
      </p>
    </div>
  );
}

export const Component = LoginScreen;
export default LoginScreen;
