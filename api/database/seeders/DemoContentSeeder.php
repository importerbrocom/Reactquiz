<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ContentStatus;
use App\Enums\CycleReshuffleScope;
use App\Enums\UnlockMode;
use App\Models\DailyQuiz;
use App\Models\ExamCategory;
use App\Models\Level;
use App\Models\LevelTest;
use App\Models\Programme;
use App\Models\Question;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A demonstrable FMGE programme: 6 levels, with Level 1 fully populated so the app
 * is runnable end to end immediately after `migrate:fresh --seed`.
 *
 * Uses bulk inserts (one statement per chunk) rather than 1,500 individual model
 * saves — the same pattern the spreadsheet importer will use in Phase 7.
 */
final class DemoContentSeeder extends Seeder
{
    /** Canonical subject list from docs/import-format.md section 10. */
    private const TOPICS = [
        'Anatomy', 'Physiology', 'Biochemistry', 'Pathology', 'Pharmacology',
        'Microbiology', 'Forensic Medicine', 'Community Medicine', 'General Medicine',
        'General Surgery', 'Obstetrics & Gynaecology', 'Paediatrics', 'Orthopaedics',
        'ENT', 'Ophthalmology', 'Psychiatry', 'Dermatology', 'Anaesthesia', 'Radiology',
    ];

    public function run(): void
    {
        $category = ExamCategory::query()->updateOrCreate(
            ['slug' => 'fmge'],
            [
                'title' => 'FMGE',
                'description' => 'Foreign Medical Graduate Examination screening test preparation.',
                'display_order' => 1,
                'status' => ContentStatus::Active,
            ],
        );

        ExamCategory::query()->updateOrCreate(
            ['slug' => 'amc'],
            [
                'title' => 'AMC',
                'description' => 'Australian Medical Council Part 1 examination preparation.',
                'display_order' => 2,
                'status' => ContentStatus::Active,
            ],
        );

        $programme = Programme::query()->updateOrCreate(
            ['slug' => 'fmge-complete-6-months'],
            [
                'exam_category_id' => $category->getKey(),
                'title' => 'FMGE Complete — 6 Months',
                'description' => '1,800 questions across six monthly levels, repeated for a second cycle.',
                'bank_label' => '2026 Bank',
                'total_levels' => 6,
                'total_cycles' => 2,
                'cycle_reshuffle_scope' => CycleReshuffleScope::WithinLevel,
                'require_test_pass_to_advance' => false,
                'level_unlock_mode' => UnlockMode::Immediate,
                'status' => ContentStatus::Active,
            ],
        );

        $totalQuestions = 0;

        for ($number = 1; $number <= 6; $number++) {
            $level = Level::query()->updateOrCreate(
                ['programme_id' => $programme->getKey(), 'level_number' => $number],
                [
                    'title' => "Level {$number} — Month {$number}",
                    'slug' => "fmge-level-{$number}",
                    'description' => "Month {$number} of the FMGE programme.",
                    'total_quiz_days' => 30,
                    'daily_question_count' => 10,
                    'test_day' => 31,
                    'test_question_count' => 300,
                    'pass_percentage' => 50.00,
                    'test_time_limit_minutes' => 300,
                    'test_attempt_limit' => 0,          // unlimited
                    'allow_test_pause' => false,
                    // Only Level 1 ships with content in the demo seed; the rest are
                    // drafts, exactly as they would be while content is being written.
                    'status' => $number === 1 ? ContentStatus::Active : ContentStatus::Draft,
                ],
            );

            $this->seedDays($level);

            LevelTest::query()->updateOrCreate(
                ['level_id' => $level->getKey(), 'version' => 1],
                [
                    'day_number' => $level->test_day,
                    'title' => "Level {$number} month-end test",
                    'question_count' => $level->requiredQuestionCount(),
                    'pass_percentage' => $level->pass_percentage,
                    'time_limit_minutes' => $level->test_time_limit_minutes,
                    'attempt_limit' => 0,
                    'shuffle_questions' => true,
                    'allow_pause' => false,
                    'status' => ContentStatus::Active,
                ],
            );

            if ($number === 1) {
                $totalQuestions += $this->seedQuestions($level);
            }
        }

        $programme->forceFill(['questions_count' => $totalQuestions])->save();

        $this->command?->info("  FMGE programme seeded: 6 levels, {$totalQuestions} questions in Level 1");
    }

    private function seedDays(Level $level): void
    {
        $rows = [];

        for ($day = 1; $day <= $level->total_quiz_days; $day++) {
            $rows[] = [
                'level_id' => $level->getKey(),
                'day_number' => $day,
                'title' => "Day {$day}",
                'question_count' => $level->daily_question_count,
                'status' => ContentStatus::Active->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DailyQuiz::query()->upsert($rows, ['level_id', 'day_number'], ['question_count', 'status']);
    }

    /** @return int number of questions now active on the level */
    private function seedQuestions(Level $level): int
    {
        $required = $level->requiredQuestionCount();   // 300
        $existing = Question::query()->where('level_id', $level->getKey())->count();

        if ($existing >= $required) {
            return $existing;
        }

        $questionRows = [];
        $now = now();

        for ($i = $existing + 1; $i <= $required; $i++) {
            $topic = self::TOPICS[$i % count(self::TOPICS)];
            $stem = "[{$topic}] Demo question {$i}: which option is the correct answer?";
            $options = $this->optionsFor($i);
            $correctKey = ['a', 'b', 'c', 'd'][$i % 4];

            $questionRows[] = [
                'level_id' => $level->getKey(),
                'question_text' => $stem,
                'correct_option' => $correctKey,
                'correct_answer_text' => $options[$correctKey],
                'explanation' => "Demo explanation for question {$i}. "
                    .'The correct choice is named here rather than referred to by letter, '
                    .'because answer positions are reshuffled on every exposure.',
                'topic' => $topic,
                'tags' => json_encode(['demo', Str::slug($topic)]),
                'source' => 'Seeded demo content',
                'status' => ContentStatus::Active->value,
                'question_hash' => Question::makeHash($stem, array_values($options)),
                'shuffle_options' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // One INSERT per chunk, not one per question.
        foreach (array_chunk($questionRows, 100) as $chunk) {
            DB::table('questions')->insert($chunk);
        }

        $this->seedOptions($level);

        $count = Question::query()->where('level_id', $level->getKey())->count();
        $level->forceFill(['questions_count' => $count])->save();

        return $count;
    }

    private function seedOptions(Level $level): void
    {
        $optionRows = [];
        $now = now();

        Question::query()
            ->where('level_id', $level->getKey())
            ->whereDoesntHave('options')
            ->select(['id', 'question_text', 'correct_option'])
            ->chunkById(200, function ($questions) use (&$optionRows, $now): void {
                foreach ($questions as $question) {
                    preg_match('/question (\d+):/', $question->question_text, $m);
                    $options = $this->optionsFor((int) ($m[1] ?? 1));
                    $order = 0;

                    foreach ($options as $key => $text) {
                        $optionRows[] = [
                            'question_id' => $question->id,
                            'option_key' => $key,
                            'option_text' => $text,
                            'is_correct' => $question->getRawOriginal('correct_option') === $key,
                            'display_order' => $order++,
                            'pin_last' => false,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            });

        foreach (array_chunk($optionRows, 400) as $chunk) {
            DB::table('question_options')->insert($chunk);
        }
    }

    /** @return array<string, string> */
    private function optionsFor(int $index): array
    {
        $pool = ['Left ventricle', 'Right atrium', 'Loop of Henle', 'Vitamin C',
            'Sodium influx', 'Chest X-ray', 'Nothing by mouth', 'Twelve pairs'];

        return [
            'a' => $pool[($index + 0) % count($pool)]." (variant {$index}a)",
            'b' => $pool[($index + 1) % count($pool)]." (variant {$index}b)",
            'c' => $pool[($index + 2) % count($pool)]." (variant {$index}c)",
            'd' => $pool[($index + 3) % count($pool)]." (variant {$index}d)",
        ];
    }
}
