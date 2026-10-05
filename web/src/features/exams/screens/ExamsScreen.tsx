import { useQuery } from '@tanstack/react-query';
import { Link } from 'react-router';
import { getCategories } from '@/api/endpoints/categories.api';
import { queryKeys } from '@/api/query-keys';
import { ROUTES } from '@/config/routes.config';
import { PageSpinner } from '@/components/ui/Spinner';
import type { ExamCategory } from '@/types/models';

/**
 * "Choose Your Exam" — student-facing exam-category picker, matching the ERO
 * mockups: full-width gradient cards, one per exam category, with question
 * counts and a tap-through to that category's courses/days.
 *
 * Data comes from the existing /exam-categories endpoint. The gradient per
 * card is assigned deterministically from the category slug so colours stay
 * stable across renders without needing a colour field on the API.
 */
function ExamsScreen() {
  const { data: categories, isLoading } = useQuery({
    queryKey: queryKeys.categories.list(),
    queryFn: getCategories,
  });

  if (isLoading || !categories) return <PageSpinner />;

  return (
    <div className="space-y-5">
      <header>
        <h1 className="text-2xl font-bold text-white">Choose Your Exam</h1>
        <p className="mt-0.5 text-sm text-surface-400">
          Select an exam to start your preparation
        </p>
      </header>

      {categories.length === 0 ? (
        <div className="rounded-2xl border border-surface-700 bg-surface-900/50 p-8 text-center">
          <p className="text-surface-400">No exams available yet. Check back soon.</p>
        </div>
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {categories.map((cat, i) => (
            <ExamCard key={cat.id} category={cat} index={i} />
          ))}
        </div>
      )}
    </div>
  );
}

/** Gradient palette cycled across cards — mirrors the colourful mockup tiles. */
const GRADIENTS = [
  'from-primary-600 to-primary-800',
  'from-accent-pink/80 to-rose-700',
  'from-accent-purple/80 to-violet-800',
  'from-accent-teal/80 to-emerald-800',
  'from-accent-amber/80 to-orange-700',
  'from-accent-cyan/80 to-sky-800',
] as const;

function ExamCard({ category, index }: { category: ExamCategory; index: number }) {
  const gradient = GRADIENTS[index % GRADIENTS.length];

  return (
    <Link
      to={`${ROUTES.COURSES}?exam=${category.slug}`}
      className={`group relative flex min-h-[132px] flex-col justify-between overflow-hidden rounded-2xl bg-gradient-to-br ${gradient} p-5 transition-transform active:scale-[0.98]`}
    >
      {/* decorative glow */}
      <div className="absolute -right-6 -top-6 h-28 w-28 rounded-full bg-white/10 blur-2xl" />

      <div className="relative flex items-start justify-between">
        <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 text-xl backdrop-blur-sm">
          {category.icon_url ? (
            <img src={category.icon_url} alt="" className="h-7 w-7 object-contain" />
          ) : (
            <span>🎓</span>
          )}
        </div>
        <span className="flex h-8 w-8 items-center justify-center rounded-full bg-white/20 text-white transition-transform group-hover:translate-x-0.5">
          →
        </span>
      </div>

      <div className="relative mt-3">
        <h2 className="text-lg font-bold text-white">{category.name}</h2>
        {category.description && (
          <p className="mt-0.5 line-clamp-2 text-xs text-white/85">{category.description}</p>
        )}
        <p className="mt-2 inline-flex items-center gap-1.5 rounded-md bg-black/20 px-2 py-1 text-xs font-medium text-white">
          📄 {category.courses_count} {category.courses_count === 1 ? 'course' : 'courses'}
        </p>
      </div>
    </Link>
  );
}

export const Component = ExamsScreen;
export default ExamsScreen;
