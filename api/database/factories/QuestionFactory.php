<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\OptionKey;
use App\Models\Level;
use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Option texts are derived deterministically from the question text, so the four
 * option rows created in afterCreating() are guaranteed to be the same four texts
 * that went into question_hash. No state smuggling between the two callbacks.
 *
 * @extends Factory<Question>
 */
final class QuestionFactory extends Factory
{
    private const TOPICS = [
        'Anatomy', 'Physiology', 'Biochemistry', 'Pathology', 'Pharmacology',
        'Microbiology', 'Forensic Medicine', 'Community Medicine', 'General Medicine',
        'General Surgery', 'Obstetrics & Gynaecology', 'Paediatrics',
    ];

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Sequence-tagged so 300+ questions per level are guaranteed distinct
        // (and therefore hash distinctly) without faker's unique() pool.
        self::$sequence++;
        $stem = ucfirst(fake()->sentence(8));
        $stem = rtrim($stem, '.').' [#'.self::$sequence.']?';

        $options = self::optionsFor($stem);
        $correct = self::correctKeyFor($stem);

        return [
            'level_id' => Level::factory(),
            'question_text' => $stem,
            'correct_option' => OptionKey::from($correct),
            'correct_answer_text' => $options[$correct],
            'explanation' => 'This is correct because '.fake()->sentence(12),
            'topic' => fake()->randomElement(self::TOPICS),
            'status' => ContentStatus::Active,
            'question_hash' => Question::makeHash($stem, array_values($options)),
            'shuffle_options' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Question $question): void {
            $options = self::optionsFor($question->question_text);
            $correct = $question->correct_option->value;

            $order = 0;
            foreach ($options as $key => $text) {
                QuestionOption::query()->create([
                    'question_id' => $question->getKey(),
                    'option_key' => $key,
                    'option_text' => $text,
                    'is_correct' => $key === $correct,
                    'display_order' => $order++,
                ]);
            }
        });
    }

    public function forLevel(Level $level): static
    {
        return $this->state(fn (): array => ['level_id' => $level->getKey()]);
    }

    public function withTopic(string $topic): static
    {
        return $this->state(fn (): array => ['topic' => $topic]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => ContentStatus::Inactive]);
    }

    /** Options whose text references other options cannot be shuffled. */
    public function unshufflable(): static
    {
        return $this->state(fn (): array => ['shuffle_options' => false]);
    }

    /**
     * Deterministic pseudo-random option texts for a given stem.
     *
     * @return array<string, string>
     */
    private static function optionsFor(string $stem): array
    {
        $seed = crc32($stem);
        $words = ['Alpha', 'Beta', 'Gamma', 'Delta', 'Sigma', 'Omega', 'Theta', 'Lambda'];

        $out = [];
        foreach (['a', 'b', 'c', 'd'] as $i => $key) {
            $out[$key] = $words[($seed + $i * 3) % count($words)].' '.(($seed + $i) % 97 + 1);
        }

        return $out;
    }

    private static function correctKeyFor(string $stem): string
    {
        return ['a', 'b', 'c', 'd'][crc32($stem) % 4];
    }
}
