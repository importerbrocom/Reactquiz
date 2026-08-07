<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin view: the ONLY resource that exposes answers, and it is only reachable
 * from routes behind role:admin.
 *
 * makeVisible() is required because Question::$hidden deliberately conceals these
 * fields everywhere else.
 *
 * @mixin Question
 */
final class QuestionAdminResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $question = $this->resource->makeVisible([
            'correct_option', 'correct_answer_text', 'explanation',
        ]);

        return [
            'id' => $question->id,
            'level_id' => $question->level_id,
            'question_text' => $question->question_text,
            'image' => $question->question_image_path === null ? null : [
                'url' => asset('storage/'.$question->question_image_path),
                'width' => $question->question_image_width,
                'height' => $question->question_image_height,
                'alt' => $question->question_image_alt,
            ],
            'options' => $question->options->map(fn (QuestionOption $o): array => [
                'key' => $o->option_key->value,
                'text' => $o->option_text,
                'is_correct' => $o->is_correct,
                'pin_last' => $o->pin_last,
                'display_order' => $o->display_order,
            ])->values()->all(),
            'correct_option' => $question->correct_option->value,
            'correct_answer_text' => $question->correct_answer_text,
            'explanation' => $question->explanation,
            'topic' => $question->topic,
            'tags' => $question->tags,
            'source' => $question->source,
            'status' => $question->status->value,
            'shuffle_options' => $question->shuffle_options,
            'question_hash' => $question->question_hash,
            'stats' => [
                'times_served' => $question->times_served,
                'times_correct' => $question->times_correct,
                'times_incorrect' => $question->times_incorrect,
                'observed_difficulty' => $question->observed_difficulty,
            ],
            'created_at' => $question->created_at?->toIso8601String(),
            'updated_at' => $question->updated_at?->toIso8601String(),
        ];
    }
}
