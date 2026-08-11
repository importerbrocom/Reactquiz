<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ReportFormat;
use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExportJob;
use App\Models\ReportExport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /**
     * POST /admin/reports — Queue an export.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', new Enum(ReportType::class)],
            'format' => ['required', new Enum(ReportFormat::class)],
            'filters' => ['nullable', 'array'],
        ]);

        $report = ReportExport::create([
            'requested_by' => $request->user()->id,
            'type' => $validated['type'],
            'format' => $validated['format'],
            'filters' => $validated['filters'] ?? null,
            'status' => ReportStatus::Queued,
            'expires_at' => now()->addHours(48),
        ]);

        GenerateReportExportJob::dispatch($report);

        return ApiResponse::success(
            $report->only(['uuid', 'type', 'format', 'status', 'expires_at', 'created_at']),
            'Report queued for generation.',
            Response::HTTP_ACCEPTED,
        );
    }

    /**
     * GET /admin/reports — List exports for the current admin.
     */
    public function index(Request $request): JsonResponse
    {
        $reports = ReportExport::where('requested_by', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return ApiResponse::success($reports);
    }

    /**
     * GET /admin/reports/{uuid} — Single report status.
     */
    public function show(string $uuid): JsonResponse
    {
        $report = ReportExport::where('uuid', $uuid)->firstOrFail();

        return ApiResponse::success($report);
    }

    /**
     * GET /admin/reports/{uuid}/download — 302 to signed URL.
     */
    public function download(string $uuid): JsonResponse|Response
    {
        $report = ReportExport::where('uuid', $uuid)->firstOrFail();

        if ($report->status !== ReportStatus::Completed) {
            return ApiResponse::error('Report is not ready for download.', Response::HTTP_CONFLICT);
        }

        if ($report->hasExpired()) {
            return ApiResponse::error('Report has expired.', Response::HTTP_GONE);
        }

        if (! $report->storage_path) {
            return ApiResponse::error('Report file not found.', Response::HTTP_NOT_FOUND);
        }

        // Increment download count
        $report->increment('download_count');
        $report->update(['downloaded_at' => now()]);

        // Generate a temporary signed URL (or serve directly for local disk)
        $disk = $report->storage_disk ?? 'local';
        $path = $report->storage_path;

        if (method_exists(Storage::disk($disk), 'temporaryUrl')) {
            $url = Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15));

            return response()->json(['url' => $url]);
        }

        // Fallback: serve the file directly
        $fullPath = Storage::disk($disk)->path($path);

        return response()->download($fullPath);
    }
}
