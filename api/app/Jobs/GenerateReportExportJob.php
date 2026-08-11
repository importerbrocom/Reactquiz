<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QueueName;
use App\Enums\ReportStatus;
use App\Models\ReportExport;
use App\Services\Reports\ReportGeneratorFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates a report export file (CSV/XLSX/PDF) from database queries.
 *
 * Flow: queued → processing → completed/failed
 * Output stored to disk, path written to the ReportExport record.
 */
class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 600; // 10 minutes max

    public function __construct(
        private readonly ReportExport $report,
    ) {
        $this->onQueue(QueueName::Reports->value);
    }

    public function handle(): void
    {
        $report = $this->report;

        if ($report->status !== ReportStatus::Queued) {
            return;
        }

        $report->update([
            'status' => ReportStatus::Processing,
            'started_at' => now(),
        ]);

        try {
            $generator = ReportGeneratorFactory::make($report->type);
            $result = $generator->generate($report);

            $disk = 'local';
            $path = "reports/{$report->uuid}.{$report->format->value}";

            Storage::disk($disk)->put($path, $result['content']);

            $report->update([
                'status' => ReportStatus::Completed,
                'storage_disk' => $disk,
                'storage_path' => $path,
                'file_size_bytes' => strlen($result['content']),
                'row_count' => $result['row_count'] ?? null,
                'completed_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Report generation failed', [
                'report_id' => $report->id,
                'type' => $report->type->value,
                'error' => $e->getMessage(),
            ]);

            $report->update([
                'status' => ReportStatus::Failed,
                'error_message' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
