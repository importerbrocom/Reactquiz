import { useParams } from 'react-router';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getStudent, suspendStudent, activateStudent, revokeStudentSessions } from '@/api/endpoints/admin-students.api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { StatusBadge } from '../../components/StatusBadge';
import { PageSpinner } from '@/components/ui/Spinner';

function StudentDetailScreen() {
  const { studentId } = useParams<{ studentId: string }>();
  const queryClient = useQueryClient();

  const { data: student, isLoading } = useQuery({
    queryKey: queryKeys.admin.students.detail(studentId!),
    queryFn: () => getStudent(studentId!),
    enabled: !!studentId,
  });

  const suspendMut = useMutation({ mutationFn: () => suspendStudent(studentId!), onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.students.detail(studentId!) }) });
  const activateMut = useMutation({ mutationFn: () => activateStudent(studentId!), onSuccess: () => queryClient.invalidateQueries({ queryKey: queryKeys.admin.students.detail(studentId!) }) });
  const revokeMut = useMutation({ mutationFn: () => revokeStudentSessions(studentId!) });

  if (isLoading || !student) return <PageSpinner />;

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">{student.name}</h1>
          <p className="text-sm text-surface-400">{student.email}</p>
        </div>
        <StatusBadge status={student.status} />
      </div>

      <div className="grid grid-cols-3 gap-4">
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{student.courses_enrolled}</p>
          <p className="text-xs text-surface-400">Courses</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{student.days_completed}</p>
          <p className="text-xs text-surface-400">Days Done</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-lg font-bold text-white">{student.current_streak}</p>
          <p className="text-xs text-surface-400">Streak</p>
        </Card>
      </div>

      {/* Enrolments */}
      {student.enrolments.length > 0 && (
        <Card variant="outlined">
          <h3 className="mb-3 font-medium text-white">Enrolments</h3>
          <div className="space-y-2">
            {student.enrolments.map((e) => (
              <div key={e.id} className="flex items-center justify-between rounded-lg bg-surface-800 px-3 py-2">
                <div>
                  <p className="text-sm font-medium text-surface-200">{e.course_name}</p>
                  <p className="text-xs text-surface-400">Level {e.current_level} · Day {e.current_day} · Cycle {e.cycle}</p>
                </div>
                <Badge variant="neutral">{e.status}</Badge>
              </div>
            ))}
          </div>
        </Card>
      )}

      {/* Actions */}
      <Card variant="outlined">
        <h3 className="mb-3 font-medium text-white">Actions</h3>
        <div className="flex flex-wrap gap-3">
          {student.status === 'active' ? (
            <Button variant="danger" size="sm" onClick={() => suspendMut.mutate()} loading={suspendMut.isPending}>Suspend</Button>
          ) : (
            <Button variant="primary" size="sm" onClick={() => activateMut.mutate()} loading={activateMut.isPending}>Activate</Button>
          )}
          <Button variant="ghost" size="sm" onClick={() => revokeMut.mutate()} loading={revokeMut.isPending}>Revoke Sessions</Button>
        </div>
      </Card>
    </div>
  );
}

export const Component = StudentDetailScreen;
export default StudentDetailScreen;
