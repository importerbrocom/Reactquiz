import { useState } from 'react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { getMistakes } from '@/api/endpoints/mistakes.api';
import { queryKeys } from '@/api/query-keys';
import { Card } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { PageSpinner } from '@/components/ui/Spinner';

function MistakesScreen() {
  const [statusFilter, setStatusFilter] = useState<'unresolved' | 'resolved' | undefined>(undefined);

  const {
    data,
    isLoading,
    hasNextPage,
    fetchNextPage,
    isFetchingNextPage,
  } = useInfiniteQuery({
    queryKey: queryKeys.mistakes.list({ status: statusFilter }),
    queryFn: ({ pageParam }) => getMistakes({ status: statusFilter, cursor: pageParam as string | undefined }),
    initialPageParam: undefined as string | undefined,
    getNextPageParam: (lastPage) => lastPage.next_cursor ?? undefined,
  });

  const mistakes = data?.pages.flatMap((page) => page.data) ?? [];

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h1 className="text-xl font-bold text-white">Mistakes</h1>
        <span className="text-sm text-surface-400">{mistakes.length} questions</span>
      </div>

      {/* Filter tabs */}
      <div className="flex gap-2">
        <FilterButton
          active={!statusFilter}
          onClick={() => setStatusFilter(undefined)}
          label="All"
        />
        <FilterButton
          active={statusFilter === 'unresolved'}
          onClick={() => setStatusFilter('unresolved')}
          label="Unresolved"
        />
        <FilterButton
          active={statusFilter === 'resolved'}
          onClick={() => setStatusFilter('resolved')}
          label="Resolved"
        />
      </div>

      {isLoading && <PageSpinner />}

      {!isLoading && mistakes.length === 0 && (
        <div className="py-12 text-center">
          <span className="text-4xl">✓</span>
          <p className="mt-3 text-surface-400">No mistakes to review</p>
        </div>
      )}

      {/* Mistake cards */}
      <div className="space-y-3">
        {mistakes.map((mistake) => (
          <Card key={mistake.id} variant="outlined" className="space-y-2">
            <p className="text-sm text-surface-100 leading-relaxed">{mistake.question_text}</p>

            <div className="flex flex-wrap gap-2">
              {mistake.topic && (
                <Badge variant="neutral">{mistake.topic}</Badge>
              )}
              <Badge variant={mistake.resolved ? 'success' : 'danger'}>
                {mistake.resolved ? 'Resolved' : `Wrong ${mistake.wrong_count}x`}
              </Badge>
            </div>

            <div className="border-t border-surface-800 pt-2">
              <p className="text-xs text-surface-400">
                <span className="text-danger-500">Your answer: </span>
                {mistake.selected_option.toUpperCase()}
              </p>
              <p className="text-xs text-surface-400">
                <span className="text-success-500">Correct: </span>
                {mistake.correct_answer_text}
              </p>
              {mistake.explanation && (
                <p className="mt-1 text-xs leading-relaxed text-surface-300">
                  {mistake.explanation}
                </p>
              )}
            </div>
          </Card>
        ))}
      </div>

      {/* Load more */}
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

function FilterButton({
  active,
  onClick,
  label,
}: {
  active: boolean;
  onClick: () => void;
  label: string;
}) {
  return (
    <button
      onClick={onClick}
      className={`min-h-[36px] rounded-full px-4 py-1.5 text-sm font-medium transition-colors ${
        active
          ? 'bg-primary-500/15 text-primary-400 border border-primary-500/30'
          : 'bg-surface-800 text-surface-400 border border-surface-700 hover:text-surface-200'
      }`}
    >
      {label}
    </button>
  );
}

export const Component = MistakesScreen;
export default MistakesScreen;
