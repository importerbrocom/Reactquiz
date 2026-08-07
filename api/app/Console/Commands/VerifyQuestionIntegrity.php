<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Level;
use App\Models\Question;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly drift check for the invariants that MySQL cannot express as constraints.
 *
 * Phase 1 documented these as application-level invariants rather than pretending
 * the database enforces them; this command is how we actually know they hold.
 * Scheduled daily and alerts on a non-zero exit.
 */
final class VerifyQuestionIntegrity extends Command
{
    protected $signature = 'quiz:verify-question-integrity
                            {--level= : restrict to one level id}
                            {--fix : repair denormalised columns from question_options}';

    protected $description = 'Verify question/option invariants that cannot be enforced by database constraints';

    public function handle(): int
    {
        $levelId = $this->option('level');
        $problems = 0;

        $problems += $this->checkOptionCount($levelId);
        $problems += $this->checkExactlyOneCorrect($levelId);
        $problems += $this->checkDenormalisation($levelId, (bool) $this->option('fix'));
        $problems += $this->checkHashes($levelId);
        $problems += $this->checkLevelReadiness($levelId);

        if ($problems === 0) {
            $this->info('All question invariants hold.');

            return self::SUCCESS;
        }

        $this->error("{$problems} integrity problem(s) found.");

        return self::FAILURE;
    }

    private function baseQuery(?string $levelId)
    {
        return Question::query()
            ->when($levelId !== null, fn ($q) => $q->where('level_id', $levelId));
    }

    private function checkOptionCount(?string $levelId): int
    {
        // Correlated subquery rather than HAVING: portable across MySQL and the
        // SQLite used by the test suite, and index-friendly on both.
        $bad = $this->baseQuery($levelId)
            ->whereRaw('(select count(*) from question_options
                         where question_options.question_id = questions.id) != 4')
            ->pluck('id');

        if ($bad->isNotEmpty()) {
            $this->warn("Questions without exactly 4 options: {$bad->count()}");
            $this->line('  ids: '.$bad->take(20)->implode(', '));
        }

        return $bad->count();
    }

    private function checkExactlyOneCorrect(?string $levelId): int
    {
        $bad = $this->baseQuery($levelId)
            ->whereRaw('(select count(*) from question_options
                         where question_options.question_id = questions.id
                           and question_options.is_correct = 1) != 1')
            ->pluck('id');

        if ($bad->isNotEmpty()) {
            $this->warn("Questions without exactly one correct option: {$bad->count()}");
            $this->line('  ids: '.$bad->take(20)->implode(', '));
        }

        return $bad->count();
    }

    /** questions.correct_option / correct_answer_text must agree with the flagged option. */
    private function checkDenormalisation(?string $levelId, bool $fix): int
    {
        $drifted = 0;

        $this->baseQuery($levelId)
            ->with('options')
            ->chunkById(500, function ($questions) use (&$drifted, $fix): void {
                foreach ($questions as $question) {
                    $flagged = $question->options->firstWhere('is_correct', true);

                    if ($flagged === null) {
                        continue;   // already reported by checkExactlyOneCorrect
                    }

                    $keyMatches = $flagged->option_key->value === $question->getRawOriginal('correct_option');
                    $textMatches = $question->correct_answer_text === $flagged->option_text;

                    if ($keyMatches && $textMatches) {
                        continue;
                    }

                    $drifted++;

                    if ($fix) {
                        DB::table('questions')->where('id', $question->id)->update([
                            'correct_option' => $flagged->option_key->value,
                            'correct_answer_text' => $flagged->option_text,
                        ]);
                    } else {
                        $this->line("  question {$question->id}: correct_option drift");
                    }
                }
            });

        if ($drifted > 0) {
            $fix
                ? $this->info("Repaired {$drifted} drifted question(s).")
                : $this->warn("Denormalisation drift: {$drifted} (re-run with --fix)");
        }

        return $fix ? 0 : $drifted;
    }

    private function checkHashes(?string $levelId): int
    {
        $mismatched = 0;

        $this->baseQuery($levelId)
            ->with('options')
            ->chunkById(500, function ($questions) use (&$mismatched): void {
                foreach ($questions as $question) {
                    $expected = Question::makeHash(
                        $question->question_text,
                        $question->options->pluck('option_text')->all(),
                    );

                    if ($expected !== $question->question_hash) {
                        $mismatched++;
                        $this->line("  question {$question->id}: stale question_hash");
                    }
                }
            });

        if ($mismatched > 0) {
            $this->warn("Stale hashes: {$mismatched}");
        }

        return $mismatched;
    }

    /** An active level must hold exactly total_quiz_days x daily_question_count questions. */
    private function checkLevelReadiness(?string $levelId): int
    {
        $problems = 0;

        Level::query()
            ->when($levelId !== null, fn ($q) => $q->whereKey($levelId))
            ->active()
            ->each(function (Level $level) use (&$problems): void {
                $actual = $level->questions()->active()->count();
                $required = $level->requiredQuestionCount();

                if ($actual !== $required) {
                    $problems++;
                    $this->warn("Level {$level->id} ({$level->title}): {$actual} active questions, needs {$required}");
                }
            });

        return $problems;
    }
}
