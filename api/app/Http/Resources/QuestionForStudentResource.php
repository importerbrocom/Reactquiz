<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * THE answer-free question payload. The most security-sensitive class in the app.
 *
 * This is a positive whitelist: adding a column to `questions` can never leak it.
 * `correct_option`, `correct_answer_text` and `explanation` are absent by
 * construction, and Question::$hidden blocks them from any accidental
 * ->toArray()/json() path as well.
 *
 * Option order is supplied by the caller (per-exposure shuffle, docs/adr/003) and
 * every option carries its CANONICAL key, so the client submits a key rather than
 * a position and server-side grading needs no translation step.
 *
 * @mixin Question
 */
final class QuestionForStudentResource extends JsonResource
{
    /** @param  array<int, string>|null  $optionOrder canonical keys in display order */
    public function __construct($resource, private readonly ?array $optionOrder = null)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'question_text' => $this->question_text,
            'image' => $this->question_image_path === null ? null : [
                'url' => asset('storage/'.$this->question_image_path),
                'width' => $this->question_image_width,
                'height' => $this->question_image_height,
                'alt' => $this->question_image_alt,
            ],
            'options' => $this->orderedOptions()
                ->map(self::presentOption(...))
                ->all(),
        ];
    }

    /**
     * One option as the student sees it: the canonical key travels with the text,
     * so the client submits a key and never a position.
     *
     * @return array{key: string, text: string, image_url: string|null}
     */
    private static function presentOption(QuestionOption $option): array
    {
        return [
            'key' => $option->option_key->value,
            'text' => $option->option_text,
            'image_url' => $option->option_image_path !== null
                ? asset('storage/'.$option->option_image_path)
                : null,
        ];
    }

    /**
     * @return Collection<int, QuestionOption>
     */
    private function orderedOptions(): Collection
    {
        /** @var Collection<int, QuestionOption> $options */
        $options = Collection::make($this->question()->options->all());

        if ($this->optionOrder === null) {
            return $options
                ->sortBy(static fn (QuestionOption $o): int => $o->display_order)
                ->values();
        }

        // Per-exposure order from the caller (docs/adr/003). An unknown key sorts
        // last rather than throwing, so a stale order can never hide an option.
        $rank = array_flip($this->optionOrder);

        return $options
            ->sortBy(static fn (QuestionOption $o): int => $rank[$o->option_key->value] ?? 99)
            ->values();
    }

    /**
     * JsonResource types $resource as mixed, so narrow it once here rather than
     * annotating at every use.
     */
    private function question(): Question
    {
        /** @var Question $question */
        $question = $this->resource;

        return $question;
    }
}
