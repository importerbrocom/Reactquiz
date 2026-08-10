import { useInfiniteQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { getNotifications, markAsRead, markAllAsRead } from '@/api/endpoints/notifications.api';
import { queryKeys } from '@/api/query-keys';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { PageSpinner } from '@/components/ui/Spinner';
import { cn } from '@/utils/cn';

function NotificationsScreen() {
  const queryClient = useQueryClient();

  const {
    data,
    isLoading,
    hasNextPage,
    fetchNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery({
    queryKey: queryKeys.notifications.list(),
    queryFn: ({ pageParam }) => getNotifications(pageParam as string | undefined),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  });

  const markReadMutation = useMutation({
    mutationFn: markAsRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all });
    },
  });

  const markAllReadMutation = useMutation({
    mutationFn: markAllAsRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeys.notifications.all });
    },
  });

  const notifications = data?.pages.flatMap((page) => page.data) ?? [];
  const unreadCount = notifications.filter((n) => !n.read_at).length;

  if (isLoading) return <PageSpinner />;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-white">Notifications</h1>
        {unreadCount > 0 && (
          <Button
            variant="ghost"
            size="sm"
            onClick={() => markAllReadMutation.mutate()}
            loading={markAllReadMutation.isPending}
          >
            Mark all read
          </Button>
        )}
      </div>

      {notifications.length === 0 && (
        <div className="py-12 text-center">
          <span className="text-4xl">🔔</span>
          <p className="mt-3 text-surface-400">No notifications yet</p>
        </div>
      )}

      <div className="space-y-2">
        {notifications.map((notification) => (
          <Card
            key={notification.id}
            variant="outlined"
            className={cn(
              'cursor-pointer transition-colors',
              !notification.read_at && 'border-l-4 border-l-primary-500',
            )}
            onClick={() => {
              if (!notification.read_at) {
                markReadMutation.mutate(notification.id);
              }
            }}
          >
            <div className="flex items-start justify-between gap-2">
              <div>
                <p className={cn('text-sm', !notification.read_at ? 'font-medium text-white' : 'text-surface-200')}>
                  {notification.title}
                </p>
                <p className="mt-0.5 text-xs text-surface-400">{notification.body}</p>
              </div>
              <span className="flex-shrink-0 text-xs text-surface-500">
                {formatTimeAgo(notification.created_at)}
              </span>
            </div>
          </Card>
        ))}
      </div>

      {hasNextPage && (
        <div className="text-center">
          <Button
            variant="ghost"
            onClick={() => fetchNextPage()}
            loading={isFetchingNextPage}
          >
            Load More
          </Button>
        </div>
      )}
    </div>
  );
}

function formatTimeAgo(dateStr: string): string {
  const now = Date.now();
  const date = new Date(dateStr).getTime();
  const diff = now - date;
  const minutes = Math.floor(diff / 60000);
  if (minutes < 1) return 'now';
  if (minutes < 60) return `${minutes}m`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `${hours}h`;
  const days = Math.floor(hours / 24);
  return `${days}d`;
}

export const Component = NotificationsScreen;
export default NotificationsScreen;
