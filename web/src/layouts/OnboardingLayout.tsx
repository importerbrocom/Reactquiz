import { Outlet } from 'react-router';

/**
 * Onboarding layout — full-screen flow, no nav.
 * Displays the 5-step onboarding wizard.
 */
function OnboardingLayout() {
  return (
    <div className="flex min-h-dvh flex-col items-center bg-surface-950 px-4 py-8">
      <div className="w-full max-w-lg">
        {/* Logo */}
        <div className="mb-6 text-center">
          <div className="flex items-center justify-center gap-3">
            <img
              src="/icons/icon-192.png"
              alt="ERO logo"
              className="h-12 w-12 rounded-xl object-cover"
            />
            <h1 className="text-2xl font-bold text-primary-400">ERO</h1>
          </div>
          <p className="mt-2 text-sm text-surface-400">Let&apos;s get you set up</p>
        </div>

        <Outlet />
      </div>
    </div>
  );
}

export const Component = OnboardingLayout;
export default OnboardingLayout;
