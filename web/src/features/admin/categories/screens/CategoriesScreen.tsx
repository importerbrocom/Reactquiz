import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getCategories, toggleCategoryStatus, deleteCategory } from '@/api/endpoints/admin-categories.api';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';
import { DataTable, type Column } from '../../components/DataTable';
import { StatusBadge } from '../../components/StatusBadge';
import { Button } from '@/components/ui/Button';
import { PageSpinner } from '@/components/ui/Spinner';
import type { AdminCategory } from '@/api/endpoints/admin-categories.api';

function CategoriesScreen() {
  const queryClient = useQueryClient();
  const { data: categories, isLoading } = useQuery({
    queryKey: queryKeys.admin.categories.list(),
    queryFn: getCategories,
  });

  const toggleMutation = useMutation({
    mutationFn: toggleCategoryStatus,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.categories.all }),
  });

  const deleteMutation = useMutation({
    mutationFn: deleteCategory,
    onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.categories.all }),
  });

  const columns: Column<AdminCategory>[] = [
    { key: 'name', header: 'Name', render: (r) => <span className="font-medium">{r.name}</span> },
    { key: 'slug', header: 'Slug', render: (r) => <span className="text-surface-400">{r.slug}</span> },
    { key: 'courses', header: 'Courses', render: (r) => r.courses_count },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    {
      key: 'actions',
      header: '',
      render: (r) => (
        <div className="flex gap-2">
          <Button size="sm" variant="ghost" onClick={() => toggleMutation.mutate(r.id)}>
            Toggle
          </Button>
          <Button size="sm" variant="ghost" onClick={() => deleteMutation.mutate(r.id)}>
            Delete
          </Button>
        </div>
      ),
    },
  ];

  if (isLoading) return <PageSpinner />;

  return (
    <div className="space-y-6">
      <AdminTableToolbar
        title="Exam Categories"
        count={categories?.length}
        actions={<Button>+ Add Category</Button>}
      />
      <DataTable
        columns={columns}
        data={categories ?? []}
        rowKey={(r) => r.id}
        emptyMessage="No categories yet"
      />
    </div>
  );
}

export const Component = CategoriesScreen;
export default CategoriesScreen;
