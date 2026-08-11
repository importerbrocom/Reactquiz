import { useInfiniteQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getActivityLogs } from '@/api/endpoints/admin-reports.api';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';
import { DataTable, type Column } from '../../components/DataTable';
import { Button } from '@/components/ui/Button';
import type { ActivityLog } from '@/api/endpoints/admin-reports.api';

function ActivityLogsScreen() {
  const { data, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useInfiniteQuery({
    queryKey: queryKeys.admin.activityLogs.list({}),
    queryFn: ({ pageParam }) => getActivityLogs({ cursor: pageParam as string | undefined }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  });

  const logs = data?.pages.flatMap((p) => p.data) ?? [];

  const columns: Column<ActivityLog>[] = [
    { key: 'user', header: 'User', render: (r) => <span className="font-medium">{r.user_name}</span> },
    { key: 'event', header: 'Event', render: (r) => <span className="text-surface-300">{r.event}</span> },
    { key: 'subject', header: 'Subject', render: (r) => `${r.subject_type} #${r.subject_id}` },
    { key: 'reason', header: 'Reason', render: (r) => r.reason ?? '—' },
    { key: 'date', header: 'When', render: (r) => new Date(r.created_at).toLocaleString() },
  ];

  return (
    <div className="space-y-6">
      <AdminTableToolbar title="Activity Logs" count={logs.length} />
      <DataTable columns={columns} data={logs} isLoading={isLoading} rowKey={(r) => r.id} emptyMessage="No activity recorded yet" />
      {hasNextPage && (
        <div className="text-center">
          <Button variant="ghost" onClick={() => fetchNextPage()} loading={isFetchingNextPage}>Load More</Button>
        </div>
      )}
    </div>
  );
}

export const Component = ActivityLogsScreen;
export default ActivityLogsScreen;
