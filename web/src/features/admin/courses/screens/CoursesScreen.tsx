import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router';
import { queryKeys } from '@/api/query-keys';
import { getCourses } from '@/api/endpoints/admin-courses.api';
import { AdminTableToolbar } from '../../components/AdminTableToolbar';
import { DataTable, type Column } from '../../components/DataTable';
import { StatusBadge } from '../../components/StatusBadge';
import { Button } from '@/components/ui/Button';
import { PageSpinner } from '@/components/ui/Spinner';
import type { AdminCourse } from '@/api/endpoints/admin-courses.api';

function CoursesScreen() {
  const navigate = useNavigate();
  const { data: courses, isLoading } = useQuery({
    queryKey: queryKeys.admin.courses.list(),
    queryFn: getCourses,
  });

  const columns: Column<AdminCourse>[] = [
    { key: 'name', header: 'Name', render: (r) => <span className="font-medium">{r.name}</span> },
    { key: 'questions', header: 'Questions', render: (r) => r.questions_count },
    { key: 'students', header: 'Students', render: (r) => r.students_enrolled },
    { key: 'levels', header: 'Levels', render: (r) => r.levels_count },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ];

  if (isLoading) return <PageSpinner />;

  return (
    <div className="space-y-6">
      <AdminTableToolbar
        title="Courses"
        count={courses?.length}
        actions={<Button>+ Add Course</Button>}
      />
      <DataTable
        columns={columns}
        data={courses ?? []}
        rowKey={(r) => r.id}
        onRowClick={(r) => navigate(`/admin/courses/${r.id}`)}
        emptyMessage="No courses yet"
      />
    </div>
  );
}

export const Component = CoursesScreen;
export default CoursesScreen;
