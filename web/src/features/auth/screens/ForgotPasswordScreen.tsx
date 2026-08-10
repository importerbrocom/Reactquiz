import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Link } from 'react-router';
import { useMutation } from '@tanstack/react-query';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { forgotPassword } from '@/api/endpoints/auth.api';
import { isApiError } from '@/api/interceptors/error.interceptor';
import { ROUTES } from '@/config/routes.config';

const schema = z.object({
  email: z.string().email('Enter a valid email address'),
});

type FormData = z.infer<typeof schema>;

function ForgotPasswordScreen() {
  const [sent, setSent] = useState(false);

  const { mutate, isPending, error } = useMutation({
    mutationFn: (data: FormData) => forgotPassword(data),
    onSuccess: () => setSent(true),
  });

  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm<FormData>({
    resolver: zodResolver(schema),
  });

  const serverError = error && isApiError(error) ? error.message : null;

  if (sent) {
    return (
      <div className="space-y-4 text-center">
        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success-500/15">
          <svg className="h-6 w-6 text-success-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
            <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
          </svg>
        </div>
        <h2 className="text-xl font-bold text-white">Check your email</h2>
        <p className="text-sm text-surface-400">
          If an account exists with that email, we&apos;ve sent a password reset link.
        </p>
        <Link
          to={ROUTES.LOGIN}
          className="inline-block text-sm font-medium text-primary-400 hover:text-primary-300"
        >
          Back to sign in
        </Link>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="text-center">
        <h2 className="text-2xl font-bold text-white">Reset password</h2>
        <p className="mt-1 text-sm text-surface-400">
          Enter your email and we&apos;ll send a reset link
        </p>
      </div>

      {serverError && (
        <div className="rounded-lg bg-danger-500/10 border border-danger-500/30 px-4 py-3 text-sm text-danger-500" role="alert">
          {serverError}
        </div>
      )}

      <form onSubmit={handleSubmit((data) => mutate(data))} className="space-y-4">
        <Input
          label="Email"
          type="email"
          autoComplete="email"
          placeholder="you@example.com"
          error={errors.email?.message}
          {...register('email')}
        />

        <Button type="submit" fullWidth loading={isPending}>
          Send Reset Link
        </Button>
      </form>

      <p className="text-center text-sm text-surface-400">
        <Link to={ROUTES.LOGIN} className="font-medium text-primary-400 hover:text-primary-300">
          Back to sign in
        </Link>
      </p>
    </div>
  );
}

export const Component = ForgotPasswordScreen;
export default ForgotPasswordScreen;
