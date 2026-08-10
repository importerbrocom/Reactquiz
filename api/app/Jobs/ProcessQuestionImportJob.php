<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ImportItemStatus;
use App\Enums\ImportStatus;
use App\Models\QuestionImport;
use App\Models\QuestionImportItem;
use App\Services\Import\RowValidator;
use App\Services\Import\SpreadsheetReader;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Parses and validates an uploaded XLSX/CSV file.
 *
 * Flow:
 * 1. Read the file row by row using SpreadsheetReader
 * 2. Validate each row with RowValidator (per docs/import-format.md)
 * 3. Store each row as a QuestionImportItem with status/errors/warnings
 * 4. Update the QuestionImport counters and set status to NeedsReview
 *
 * Does NOT insert questions — that happens in ApproveQuestionImportAction.
 */
class ProcessQuestionImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300; // 5 minutes max

    public function __construct(
        private readonly QuestionImport $import,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $import = $this->import;

        // Guard: only process uploads that haven't started yet
        if (! in_array($import->status, [ImportStatus::Uploaded, ImportStatus::Failed], true)) {
            return;
        }

        $import->update([
            'status' => ImportStatus::Validating,
            'started_at' => now(),
        ]);

        try {
            $filePath = Storage::disk($import->storage_disk)->path($import->storage_path);
            $levelId = $import->level_id;

            $validator = new RowValidator($levelId);
            $chunkSize = (int) config('quiz.import.chunk_size', 500);
            $maxRows = (int) config('quiz.import.max_rows', 5000);

            $rowsTotal = 0;
            $rowsValid = 0;
            $rowsWarning = 0;
            $rowsRejected = 0;
            $rowsDuplicate = 0;
            $itemBuffer = [];

            foreach (SpreadsheetReader::read($filePath, $import->mime_type) as $rowNumber => $row) {
                $rowsTotal++;

                if ($rowsTotal > $maxRows) {
                    $import->update([
                        'status' => ImportStatus::Failed,
                        'failure_reason' => 'too_many_rows',
                        'failure_detail' => "File exceeds maximum of {$maxRows} rows.",
                    ]);

                    return;
                }

                // Validate the row
                $result = $validator->validate($row);
                $hasErrors = ! empty($result['errors']);
                $hasWarnings = ! empty($result['warnings']);
                $isDuplicate = in_array('duplicate_in_file', $result['errors'], true)
                    || in_array('duplicate_in_level', $result['errors'], true);

                // Determine status
                if ($isDuplicate) {
                    $status = ImportItemStatus::Duplicate;
                    $rowsDuplicate++;
                } elseif ($hasErrors) {
                    $status = ImportItemStatus::Rejected;
                    $rowsRejected++;
                } elseif ($hasWarnings) {
                    $status = ImportItemStatus::Warning;
                    $rowsWarning++;
                    $rowsValid++;
                } else {
                    $status = ImportItemStatus::Valid;
                    $rowsValid++;
                }

                $itemBuffer[] = [
                    'question_import_id' => $import->id,
                    'row_number' => $rowNumber,
                    'external_ref' => $row['questionno'] ?? null,
                    'question_text' => $row['question'] ?? null,
                    'option_a' => $row['optiona'] ?? null,
                    'option_b' => $row['optionb'] ?? null,
                    'option_c' => $row['optionc'] ?? null,
                    'option_d' => $row['optiond'] ?? null,
                    'correct_option' => $result['correct_option'],
                    'explanation' => $row['explanation'] ?? null,
                    'topic' => $row['topic'] ?? null,
                    'tags' => $row['tags'] ?? null,
                    'source' => $row['source'] ?? null,
                    'image_filename' => $row['imagefilename'] ?? null,
                    'row_status' => $row['status'] ?? 'active',
                    'status' => $status->value,
                    'errors' => $hasErrors ? json_encode($result['errors']) : null,
                    'warnings' => $hasWarnings ? json_encode($result['warnings']) : null,
                    'question_hash' => $result['hash'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Flush buffer in chunks
                if (count($itemBuffer) >= $chunkSize) {
                    QuestionImportItem::insert($itemBuffer);
                    $itemBuffer = [];

                    // Update progress
                    $import->update([
                        'progress_percent' => min(99, (int) (($rowsTotal / max($rowsTotal, 100)) * 100)),
                        'rows_total' => $rowsTotal,
                    ]);
                }
            }

            // Flush remaining
            if (! empty($itemBuffer)) {
                QuestionImportItem::insert($itemBuffer);
            }

            // Final update
            $import->update([
                'status' => ImportStatus::NeedsReview,
                'progress_percent' => 100,
                'rows_total' => $rowsTotal,
                'rows_valid' => $rowsValid,
                'rows_warning' => $rowsWarning,
                'rows_rejected' => $rowsRejected,
                'rows_duplicate' => $rowsDuplicate,
                'completed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            Log::error('Question import failed', [
                'import_id' => $import->id,
                'error' => $e->getMessage(),
            ]);

            $import->update([
                'status' => ImportStatus::Failed,
                'failure_reason' => 'processing_error',
                'failure_detail' => $e->getMessage(),
                'completed_at' => now(),
            ]);

            throw $e;
        }
    }
}
