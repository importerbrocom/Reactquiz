import { useState } from 'react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { queryKeys } from '@/api/query-keys';
import { getQuestions } from '@/api/endpoints/admin-questions.api';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';
import { DataTable, type Column } from '../../components/DataTable';
import { StatusBadge } from '../../components/StatusBadge';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import type { AdminQuestion } from '@/api/endpoints/admin-questions.api';

function QuestionsScreen() {
  const navigate = useNavigate();
  const [search, setSearch] = useState('');

  const { data, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useInfiniteQuery({
    queryKey: queryKeys.admin.questions.list({ search }),
    queryFn: ({ pageParam }) => getQuestions({ search, cursor: pageParam as string | undefined }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  });

  const questions = data?.pages.flatMap((p) => p.data) ?? [];

  const columns: Column<AdminQuestion>[] = [
    {
      key: 'text',
      header: 'Question',
      render: (r) => <span className="line-clamp-2 max-w-md">{r.text}</span>,
    },
    { key: 'course', header: 'Course', render: (r) => r.course_name ?? '—' },
    { key: 'day', header: 'Day', render: (r) => r.day_number ?? '—' },
    {
      key: 'difficulty',
      header: 'Difficulty',
      render: (r) => r.difficulty ? <Badge variant="neutral">{r.difficulty}</Badge> : '—',
    },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'stats',
      header: 'Stats',
      render: (r) => (
        <span className="text-xs text-surface-400">
          {r.times_served > 0 ? `${Math.round((r.times_correct / r.times_served) * 100)}% correct` : 'No data'}
        </span>
      ),
    },
  ];

  return (
    <div className="space-y-6">
      <AdminTableToolbar
        title="Questions"
        count={questions.length}
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder="Search questions..."
        actions={<Button onClick={() => navigate('/admin/questions/new')}>+ Add Question</Button>}
      />
      <DataTable
        columns={columns}
        data={questions}
        isLoading={isLoading}
        rowKey={(r) => r.id}
        onRowClick={(r) => navigate(`/admin/questions/${r.id}`)}
        emptyMessage="No questions found"
      />
      {hasNextPage && (
        <div className="text-center">
          <Button variant="ghost" onClick={() => fetchNextPage()} loading={isFetchingNextPage}>
            Load More
          </Button>
        </div>
      )}
    </div>
  );
}

export const Component = QuestionsScreen;
export default QuestionsScreen;
