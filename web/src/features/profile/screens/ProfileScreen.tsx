import { useAuthStore } from '@/store/auth.store';
import { useLogout } from '@/features/auth/hooks/useLogout';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';

function ProfileScreen() {
  const { user } = useAuthStore();
  const logoutMutation = useLogout();

  if (!user) return null;

  return (
    <div className="space-y-6">
      <h1 className="text-xl font-bold text-white">Profile</h1>

      {/* User info */}
      <Card variant="outlined" className="flex items-center gap-4">
        <div className="flex h-14 w-14 items-center justify-center rounded-full bg-primary-500/15">
          <span className="text-xl font-bold text-primary-400">
            {user.name.charAt(0).toUpperCase()}
          </span>
        </div>
        <div>
          <p className="font-medium text-white">{user.name}</p>
          <p className="text-sm text-surface-400">{user.email}</p>
        </div>
      </Card>

      {/* Settings */}
      <Card variant="outlined" className="space-y-3">
        <h3 className="font-medium text-white">Settings</h3>
        <div className="space-y-2">
          <SettingRow label="Timezone" value={user.timezone} />
          <SettingRow label="Language" value={user.locale || 'English'} />
          <SettingRow label="Member since" value={new Date(user.created_at).toLocaleDateString()} />
        </div>
      </Card>

      {/* Actions */}
      <div className="space-y-3">
        <Button
          variant="danger"
          fullWidth
          onClick={() => logoutMutation.mutate()}
          loading={logoutMutation.isPending}
        >
          Sign Out
        </Button>
      </div>
    </div>
  );
}

function SettingRow({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between border-b border-surface-800 pb-2 last:border-0 last:pb-0">
      <span className="text-sm text-surface-400">{label}</span>
      <span className="text-sm text-surface-200">{value}</span>
    </div>
  );
}

export const Component = ProfileScreen;
export default ProfileScreen;
