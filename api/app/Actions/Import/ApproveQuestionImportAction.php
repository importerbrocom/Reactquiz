<?php

declare(strict_types=1);

namespace App\Actions\Import;

use App\Enums\ContentStatus;
use App\Enums\ImportItemStatus;
use App\Enums\ImportStatus;
use App\Enums\OptionKey;
use App\Models\Question;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Models\QuestionOption;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-insert approved import items into the questions table.
 *
 * - Only processes items with status Valid or Warning
 * - Inserts in chunks (per config/quiz.php chunk_size)
 * - One transaction per chunk
 * - Updates item status to Imported with created_question_id
 * - Updates the import counters and status
 */
final readonly class ApproveQuestionImportAction
{
    /**
     * @return array{imported: int, skipped: int}
     */
    public function __invoke(QuestionImport $import): array
    {
        $chunkSize = (int) config('quiz.import.chunk_size', 500);
        $imported = 0;
        $skipped = 0;
        $adminId = auth()->id();

        $import->update([
            'status' => ImportStatus::Importing,
            'approved_at' => now(),
            'approved_by' => $adminId,
        ]);

        $items = $import->items()
            ->importable()
            ->orderBy('row_number')
            ->get();

        foreach ($items->chunk($chunkSize) as $chunk) {
            DB::transaction(function () use ($chunk, $import, $adminId, &$imported, &$skipped) {
                foreach ($chunk as $item) {
                    /** @var QuestionImportItem $item */
                    $optionTexts = $item->optionTexts();

                    if (count($optionTexts) < 4) {
                        $item->update(['status' => ImportItemStatus::Skipped]);
                        $skipped++;
                        continue;
                    }

                    $hash = Question::makeHash($item->question_text, $optionTexts);

                    // Final dedup check inside transaction
                    $exists = Question::where('level_id', $import->level_id)
                        ->where('question_hash', $hash)
                        ->exists();

                    if ($exists) {
                        $item->update([
                            'status' => ImportItemStatus::Duplicate,
                            'question_hash' => $hash,
                        ]);
                        $skipped++;
                        continue;
                    }

                    $correctOption = $item->correct_option;
                    $status = ($item->row_status === 'draft')
                        ? ContentStatus::Draft
                        : ContentStatus::Active;

                    // Create the question
                    $question = Question::create([
                        'level_id' => $import->level_id,
                        'question_text' => $item->question_text,
                        'correct_option' => OptionKey::from($correctOption),
                        'correct_answer_text' => $this->getCorrectText($item, $correctOption),
                        'explanation' => $item->explanation,
                        'topic' => $item->topic,
                        'tags' => $item->tags ? explode(';', $item->tags) : null,
                        'source' => $item->source,
                        'status' => $status,
                        'question_hash' => $hash,
                        'shuffle_options' => true,
                        'question_import_id' => $import->id,
                        'created_by' => $adminId,
                    ]);

                    // Create the 4 options
                    $optionKeys = [OptionKey::A, OptionKey::B, OptionKey::C, OptionKey::D];
                    $optionValues = [$item->option_a, $item->option_b, $item->option_c, $item->option_d];

                    foreach ($optionKeys as $i => $key) {
                        $text = $optionValues[$i] ?? '';
                        if ($text === '') continue;

                        QuestionOption::create([
                            'question_id' => $question->id,
                            'option_key' => $key,
                            'option_text' => $text,
                            'is_correct' => $key->value === $correctOption,
                            'display_order' => $i + 1,
                            'pin_last' => QuestionOption::isAllOrNoneOfTheAbove($text),
                        ]);
                    }

                    // Mark item as imported
                    $item->update([
                        'status' => ImportItemStatus::Imported,
                        'created_question_id' => $question->id,
                        'question_hash' => $hash,
                    ]);

                    $imported++;
                }
            });
        }

        // Final import status
        $finalStatus = $skipped > 0 && $imported > 0
            ? ImportStatus::PartiallyCompleted
            : ($imported > 0 ? ImportStatus::Completed : ImportStatus::Failed);

        $import->update([
            'status' => $finalStatus,
            'rows_imported' => $imported,
            'completed_at' => now(),
        ]);

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function getCorrectText(QuestionImportItem $item, string $correctOption): string
    {
        return match ($correctOption) {
            'a' => $item->option_a ?? '',
            'b' => $item->option_b ?? '',
            'c' => $item->option_c ?? '',
            'd' => $item->option_d ?? '',
            default => '',
        };
    }
}
