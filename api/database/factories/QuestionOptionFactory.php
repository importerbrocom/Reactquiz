<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuestionOption> */
final class QuestionOptionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'option_key' => 'a',
            'option_text' => fake()->words(3, true),
            'option_image_path' => null,
            'is_correct' => false,
            'display_order' => 0,
            'pin_last' => false,
        ];
    }
}
