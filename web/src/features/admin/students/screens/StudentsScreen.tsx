import { useState } from 'react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { queryKeys } from '@/api/query-keys';
import { getStudents } from '@/api/endpoints/admin-students.api';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';
import { DataTable, type Column } from '../../components/DataTable';
import { StatusBadge } from '../../components/StatusBadge';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import type { AdminStudent } from '@/api/endpoints/admin-students.api';

function StudentsScreen() {
  const navigate = useNavigate();
  const [search, setSearch] = useState('');

  const { data, isLoading, hasNextPage, fetchNextPage, isFetchingNextPage } = useInfiniteQuery({
    queryKey: queryKeys.admin.students.list({ search }),
    queryFn: ({ pageParam }) => getStudents({ search, cursor: pageParam as string | undefined }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  });

  const students = data?.pages.flatMap((p) => p.data) ?? [];

  const columns: Column<AdminStudent>[] = [
    { key: 'name', header: 'Name', render: (r) => <span className="font-medium">{r.name}</span> },
    { key: 'email', header: 'Email', render: (r) => <span className="text-surface-400">{r.email}</span> },
    { key: 'courses', header: 'Courses', render: (r) => r.courses_enrolled },
    { key: 'days', header: 'Days Done', render: (r) => r.days_completed },
    { key: 'streak', header: 'Streak', render: (r) => r.current_streak > 0 ? <Badge variant="success">{r.current_streak}d</Badge> : '—' },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  return (
    <div className="space-y-6">
      <AdminTableToolbar
        title="Students"
        count={students.length}
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder="Search by name or email..."
      />
      <DataTable columns={columns} data={students} isLoading={isLoading} rowKey={(r) => r.id} onRowClick={(r) => navigate(`/admin/students/${r.id}`)} emptyMessage="No students found" />
      {hasNextPage && (
        <div className="text-center">
          <Button variant="ghost" onClick={() => fetchNextPage()} loading={isFetchingNextPage}>Load More</Button>
        </div>
      )}
    </div>
  );
}

export const Component = StudentsScreen;
export default StudentsScreen;
