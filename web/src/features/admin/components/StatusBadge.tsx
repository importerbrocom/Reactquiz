import { Badge } from '@/components/ui/Badge';
import { ContentStatus } from '@/types/enums';

interface StatusBadgeProps {
  status: string;
}

const statusVariants: Record<string, 'success' | 'warning' | 'danger' | 'neutral' | 'primary'> = {
  [ContentStatus.Active]: 'success',
  [ContentStatus.Draft]: 'warning',
  [ContentStatus.Inactive]: 'neutral',
  [ContentStatus.Archived]: 'danger',
  suspended: 'danger',
  pending_verification: 'warning',
  completed: 'success',
  processing: 'primary',
  queued: 'neutral',
  failed: 'danger',
  needs_review: 'warning',
};

export function StatusBadge({ status }: StatusBadgeProps) {
  const variant = statusVariants[status] ?? 'neutral';
  return <Badge variant={variant}>{status.replace(/_/g, ' ')}</Badge>;
}
