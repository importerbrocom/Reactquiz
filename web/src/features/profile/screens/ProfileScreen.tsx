import { useAuthStore } from '@/store/auth.store';
import { useLogout } from '@/features/auth/hooks/useLogout';

/**
 * Profile screen — redesigned to match the ERO mockups: a gradient profile
 * header with avatar, grouped setting rows, and a sign-out action. Data and
 * logout wiring are unchanged.
 */
function ProfileScreen() {
  const { user } = useAuthStore();
  const logoutMutation = useLogout();

  if (!user) return null;

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-bold text-white">Profile</h1>

      {/* Gradient profile header */}
      <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-primary-600 to-primary-800 p-5">
        <div className="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10 blur-2xl" />
        <div className="relative flex items-center gap-4">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white/20 text-2xl font-bold text-white backdrop-blur-sm">
            {user.name.charAt(0).toUpperCase()}
          </div>
          <div>
            <p className="text-xl font-bold text-white">{user.name}</p>
            <p className="text-sm text-blue-100/90">{user.email}</p>
          </div>
        </div>
      </div>

      {/* Account details */}
      <div className="overflow-hidden rounded-2xl border border-surface-700 bg-surface-900/50">
        <SettingRow label="Timezone" value={user.timezone} />
        <SettingRow label="Language" value={user.locale || 'English'} />
        <SettingRow label="Member since" value={new Date(user.created_at).toLocaleDateString()} />
      </div>

      {/* Sign out */}
      <button
        type="button"
        onClick={() => logoutMutation.mutate()}
        disabled={logoutMutation.isPending}
        className="min-h-[48px] w-full rounded-xl border border-danger-500/40 bg-danger-500/10 px-4 py-3 text-sm font-semibold text-danger-500 transition-colors hover:bg-danger-500/20 disabled:opacity-60"
      >
        {logoutMutation.isPending ? 'Signing out…' : 'Sign Out'}
      </button>
    </div>
  );
}

function SettingRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between border-b border-surface-800 px-4 py-3.5 last:border-0">
      <span className="text-sm text-surface-400">{label}</span>
      <span className="text-sm font-medium text-surface-200">{value}</span>
    </div>
  );
}

export const Component = ProfileScreen;
export default ProfileScreen;
