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
          <img
            src="/icons/icon-192.png"
            alt="ERO"
            className="mx-auto h-16 w-16 rounded-2xl object-cover"
          />
          <p className="mt-3 text-sm text-surface-400">Let&apos;s get you set up</p>
        </div>

        <Outlet />
      </div>
    </div>
  );
}

export const Component = OnboardingLayout;
export default OnboardingLayout;
