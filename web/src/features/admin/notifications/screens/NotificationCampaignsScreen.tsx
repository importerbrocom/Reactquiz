import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';

function NotificationCampaignsScreen() {
  return (
    <div className="space-y-6">
      <AdminTableToolbar
        title="Notification Campaigns"
        actions={<Button>+ New Campaign</Button>}
      />
      <Card variant="outlined" className="py-12 text-center">
        <span className="text-4xl">🔔</span>
        <p className="mt-3 text-surface-400">No campaigns yet</p>
        <p className="mt-1 text-xs text-surface-500">
          Create a campaign to send announcements to students
        </p>
      </Card>
    </div>
  );
}

export const Component = NotificationCampaignsScreen;
export default NotificationCampaignsScreen;
