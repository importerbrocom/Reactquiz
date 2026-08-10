import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getReports, createReport } from '@/api/endpoints/admin-reports.api';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';
import { DataTable, type Column } from '../../components/DataTable';
import { StatusBadge } from '../../components/StatusBadge';
import { Button } from '@/components/ui/Button';
import type { Report } from '@/api/endpoints/admin-reports.api';

function ReportsScreen() {
  const queryClient = useQueryClient();
  const { data: reports, isLoading } = useQuery({
    queryKey: queryKeys.admin.reports.list(),
    queryFn: getReports,
  });

  const createMut = useMutation({
    mutationFn: () => createReport({ type: 'student_progress', format: 'csv' }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.reports.all }),
  });

  const columns: Column<Report>[] = [
    { key: 'type', header: 'Type', render: (r) => <span className="font-medium capitalize">{r.type.replace(/_/g, ' ')}</span> },
    { key: 'format', header: 'Format', render: (r) => r.format.toUpperCase() },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'date', header: 'Created', render: (r) => new Date(r.created_at).toLocaleDateString() },
    {
      key: 'download',
      header: '',
      render: (r) => r.download_url ? (
        <a href={r.download_url} className="text-xs text-primary-400 hover:text-primary-300" target="_blank" rel="noreferrer">Download</a>
      ) : null,
    },
  ];

  return (
    <div className="space-y-6">
      <AdminTableToolbar
        title="Reports"
        count={reports?.length}
        actions={<Button onClick={() => createMut.mutate()} loading={createMut.isPending}>+ Generate Report</Button>}
      />
      <DataTable columns={columns} data={reports ?? []} isLoading={isLoading} rowKey={(r) => r.id} emptyMessage="No reports generated yet" />
    </div>
  );
}

export const Component = ReportsScreen;
export default ReportsScreen;
