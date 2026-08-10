import { useMutation } from '@tanstack/react-query';
import { Button } from '@/components/ui/Button';
import { resendVerificationEmail } from '@/api/endpoints/auth.api';
import { useAuthStore } from '@/store/auth.store';

function VerifyEmailScreen() {
  const { user } = useAuthStore();

  const { mutate, isPending, isSuccess } = useMutation({
    mutationFn: () => resendVerificationEmail(),
  });

  return (
    <div className="space-y-6 text-center">
      <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-primary-500/15">
        <svg className="h-8 w-8 text-primary-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
          <path strokeLinecap="round" strokeLinejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </div>

      <div>
        <h2 className="text-2xl font-bold text-white">Verify your email</h2>
        <p className="mt-2 text-sm text-surface-400">
          We sent a verification link to{' '}
          <span className="font-medium text-surface-200">{user?.email}</span>.
          <br />
          Click the link in the email to verify your account.
        </p>
      </div>

      {isSuccess && (
        <p className="text-sm text-success-500">
          Verification email sent! Check your inbox.
        </p>
      )}

      <Button
        variant="secondary"
        onClick={() => mutate()}
        loading={isPending}
        disabled={isSuccess}
      >
        Resend Verification Email
      </Button>
    </div>
  );
}

export const Component = VerifyEmailScreen;
export default VerifyEmailScreen;
