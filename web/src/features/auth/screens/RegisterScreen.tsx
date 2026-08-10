import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link } from 'react-router';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useRegister } from '../hooks/useRegister';
import { isApiError } from '@/api/interceptors/error.interceptor';
import { ROUTES } from '@/config/routes.config';

const registerSchema = z
  .object({
    name: z.string().min(2, 'Name must be at least 2 characters'),
    email: z.string().email('Enter a valid email address'),
    password: z.string().min(8, 'Password must be at least 8 characters'),
    password_confirmation: z.string(),
  })
  .refine((data) => data.password === data.password_confirmation, {
    message: 'Passwords do not match',
    path: ['password_confirmation'],
  });

type RegisterFormData = z.infer<typeof registerSchema>;

function RegisterScreen() {
  const { mutate, isPending, error } = useRegister();

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<RegisterFormData>({
    resolver: zodResolver(registerSchema),
  });

  const onSubmit = (data: RegisterFormData) => {
    mutate({
      ...data,
      timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
    });
  };

  const serverError = error && isApiError(error) ? error.message : null;
  const fieldErrors = error && isApiError(error) ? error.errors : null;

  return (
    <div className="space-y-6">
      <div className="text-center">
        <h2 className="text-2xl font-bold text-white">Create your account</h2>
        <p className="mt-1 text-sm text-surface-400">Start your exam preparation today</p>
      </div>

      {serverError && (
        <div className="rounded-lg bg-danger-500/10 border border-danger-500/30 px-4 py-3 text-sm text-danger-500" role="alert">
          {serverError}
        </div>
      )}

      <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
        <Input
          label="Full Name"
          autoComplete="name"
          placeholder="Your name"
          error={errors.name?.message || fieldErrors?.name?.[0]}
          {...register('name')}
        />

        <Input
          label="Email"
          type="email"
          autoComplete="email"
          placeholder="you@example.com"
          error={errors.email?.message || fieldErrors?.email?.[0]}
          {...register('email')}
        />

        <Input
          label="Password"
          type="password"
          autoComplete="new-password"
          placeholder="At least 8 characters"
          error={errors.password?.message || fieldErrors?.password?.[0]}
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
          Create Account
        </Button>
      </form>

      <p className="text-center text-sm text-surface-400">
        Already have an account?{' '}
        <Link to={ROUTES.LOGIN} className="font-medium text-primary-400 hover:text-primary-300">
          Sign in
        </Link>
      </p>
    </div>
  );
}

export const Component = RegisterScreen;
export default RegisterScreen;
