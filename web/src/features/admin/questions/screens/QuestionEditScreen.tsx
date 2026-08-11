import { useParams, useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/query-keys';
import { getQuestion } from '@/api/endpoints/admin-questions.api';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { StatusBadge } from '../../components/StatusBadge';
import { PageSpinner } from '@/components/ui/Spinner';

function QuestionEditScreen() {
  const { questionId } = useParams<{ questionId: string }>();
  const navigate = useNavigate();

  const { data: question, isLoading } = useQuery({
    queryKey: queryKeys.admin.questions.detail(questionId!),
    queryFn: () => getQuestion(questionId!),
    enabled: !!questionId && questionId !== 'new',
  });

  if (isLoading) return <PageSpinner />;

  if (!question) {
    return (
      <div className="space-y-4">
        <h1 className="text-xl font-bold text-white">New Question</h1>
        <p className="text-surface-400">Question creation form would go here.</p>
        <Button variant="ghost" onClick={() => navigate(-1)}>Back</Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-white">Edit Question</h1>
        <StatusBadge status={question.status} />
      </div>

      <Card variant="outlined">
        <h3 className="mb-2 text-sm font-medium text-surface-400">Question Text</h3>
        <p className="text-surface-100">{question.text}</p>
      </Card>

      <Card variant="outlined">
        <h3 className="mb-3 text-sm font-medium text-surface-400">Options</h3>
        <div className="space-y-2">
          {question.options.map((opt) => (
            <div
              key={opt.key}
              className={`flex items-center gap-3 rounded-lg px-3 py-2 ${
                opt.is_correct ? 'bg-success-500/10 border border-success-500/30' : 'bg-surface-800'
              }`}
            >
              <span className="flex h-6 w-6 items-center justify-center rounded-full bg-surface-700 text-xs font-bold">
                {opt.key.toUpperCase()}
              </span>
              <span className="text-sm text-surface-200">{opt.text}</span>
              {opt.is_correct && <Badge variant="success">Correct</Badge>}
            </div>
          ))}
        </div>
      </Card>

      {question.explanation && (
        <Card variant="outlined">
          <h3 className="mb-2 text-sm font-medium text-surface-400">Explanation</h3>
          <p className="text-sm text-surface-300">{question.explanation}</p>
        </Card>
      )}

      <div className="grid grid-cols-3 gap-4">
        <Card variant="outlined" className="text-center">
          <p className="text-sm font-bold text-white">{question.times_served}</p>
          <p className="text-xs text-surface-400">Times Served</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-sm font-bold text-white">{question.times_correct}</p>
          <p className="text-xs text-surface-400">Times Correct</p>
        </Card>
        <Card variant="outlined" className="text-center">
          <p className="text-sm font-bold text-white">
            {question.observed_difficulty?.toFixed(2) ?? '—'}
          </p>
          <p className="text-xs text-surface-400">Difficulty</p>
        </Card>
      </div>

      <div className="flex gap-3">
        <Button variant="ghost" onClick={() => navigate(-1)}>Back</Button>
        <Button>Save Changes</Button>
      </div>
    </div>
  );
}

export const Component = QuestionEditScreen;
export default QuestionEditScreen;
