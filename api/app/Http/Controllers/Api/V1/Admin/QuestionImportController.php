<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Import\ApproveQuestionImportAction;
use App\Enums\ImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadQuestionImportRequest;
use App\Jobs\ProcessQuestionImportJob;
use App\Models\QuestionImport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class QuestionImportController extends Controller
{
    /**
     * POST /admin/question-imports — Upload a file and queue processing.
     */
    public function store(UploadQuestionImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $levelId = (int) $request->input('level_id');

        // Store the file privately
        $path = $file->store('question-imports', 'local');
        $checksum = hash_file('sha256', $file->getRealPath());

        // Dedup by checksum — if an identical file was already uploaded for this level, reject
        $existing = QuestionImport::where('file_checksum', $checksum)
            ->where('level_id', $levelId)
            ->whereNotIn('status', [ImportStatus::Failed->value])
            ->first();

        if ($existing) {
            Storage::disk('local')->delete($path);

            return ApiResponse::error(
                'This file has already been uploaded for this level.',
                Response::HTTP_CONFLICT,
            );
        }

        // Create the import record
        $import = QuestionImport::create([
            'level_id' => $levelId,
            'uploaded_by' => $request->user()->id,
            'original_filename' => $file->getClientOriginalName(),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size_bytes' => $file->getSize(),
            'file_checksum' => $checksum,
            'status' => ImportStatus::Uploaded,
        ]);

        // Queue processing
        ProcessQuestionImportJob::dispatch($import);

        return ApiResponse::success(
            $import->only(['uuid', 'original_filename', 'status', 'created_at']),
            'File uploaded and queued for processing.',
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * GET /admin/question-imports — List all imports.
     */
    public function index(Request $request): JsonResponse
    {
        $imports = QuestionImport::query()
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('level_id'), fn ($q, $id) => $q->where('level_id', $id))
            ->orderByDesc('created_at')
            ->cursorPaginate(20);

        return ApiResponse::success($imports);
    }

    /**
     * GET /admin/question-imports/{uuid} — Single import with counters.
     */
    public function show(string $uuid): JsonResponse
    {
        $import = QuestionImport::where('uuid', $uuid)->firstOrFail();

        return ApiResponse::success($import);
    }

    /**
     * GET /admin/question-imports/{uuid}/progress — Polling endpoint.
     */
    public function progress(string $uuid): JsonResponse
    {
        $import = QuestionImport::where('uuid', $uuid)
            ->firstOrFail(['uuid', 'status', 'progress_percent', 'rows_total', 'rows_valid', 'rows_warning', 'rows_rejected', 'rows_duplicate']);

        return ApiResponse::success($import);
    }

    /**
     * GET /admin/question-imports/{uuid}/items — Preview parsed items.
     */
    public function items(string $uuid, Request $request): JsonResponse
    {
        $import = QuestionImport::where('uuid', $uuid)->firstOrFail();

        $items = $import->items()
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('row_number')
            ->cursorPaginate(50);

        return ApiResponse::success($items);
    }

    /**
     * POST /admin/question-imports/{uuid}/approve — Bulk insert valid items.
     */
    public function approve(string $uuid, ApproveQuestionImportAction $action): JsonResponse
    {
        $import = QuestionImport::where('uuid', $uuid)->firstOrFail();

        if ($import->status !== ImportStatus::NeedsReview) {
            return ApiResponse::error(
                'Import is not in a reviewable state.',
                Response::HTTP_CONFLICT,
            );
        }

        $result = $action($import);

        return ApiResponse::success($result, 'Import approved and questions created.', Response::HTTP_ACCEPTED);
    }

    /**
     * POST /admin/question-imports/{uuid}/retry — Re-queue a failed import.
     */
    public function retry(string $uuid): JsonResponse
    {
        $import = QuestionImport::where('uuid', $uuid)->firstOrFail();

        if ($import->status !== ImportStatus::Failed) {
            return ApiResponse::error('Only failed imports can be retried.', Response::HTTP_CONFLICT);
        }

        $import->update(['status' => ImportStatus::Uploaded, 'failure_reason' => null, 'failure_detail' => null]);
        ProcessQuestionImportJob::dispatch($import);

        return ApiResponse::success(null, 'Import re-queued for processing.', Response::HTTP_ACCEPTED);
    }

    /**
     * DELETE /admin/question-imports/{uuid} — Soft delete + file cleanup.
     */
    public function destroy(string $uuid): JsonResponse
    {
        $import = QuestionImport::where('uuid', $uuid)->firstOrFail();
        $import->delete();

        // Queue file cleanup (don't block the response)
        Storage::disk($import->storage_disk)->delete($import->storage_path);

        return ApiResponse::success(null, 'Import deleted.');
    }

    /**
     * GET /admin/question-imports/template — Download the import template.
     */
    public function template(): Response
    {
        $path = base_path('../docs/templates/questions-import-template.xlsx');

        if (! file_exists($path)) {
            return ApiResponse::error('Template not found.', Response::HTTP_NOT_FOUND);
        }

        return response()->download($path, 'questions-import-template.xlsx');
    }
}
