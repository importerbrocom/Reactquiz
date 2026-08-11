<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\QueueName;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Renders a certificate PDF and stores it.
 *
 * A real implementation would use a PDF library (DOMPDF, TCPDF, or Browsershot).
 * This creates a minimal placeholder that proves the pipeline works end-to-end.
 * Replace the PDF generation with a proper template when ready.
 */
class RenderCertificatePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        private readonly Certificate $certificate,
    ) {
        $this->onQueue(QueueName::Default->value);
    }

    public function handle(): void
    {
        $cert = $this->certificate;
        $cert->load(['user', 'level']);

        try {
            // Generate PDF content (placeholder — replace with DOMPDF/Browsershot)
            $html = $this->buildHtml($cert);
            $pdfContent = $html; // In production, render $html to PDF

            // Store the file
            $path = "certificates/{$cert->uuid}.pdf";
            Storage::disk('local')->put($path, $pdfContent);

            // Update certificate record
            $cert->update([
                'storage_path' => $path,
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Certificate render failed', [
                'certificate_id' => $cert->id,
                'error' => $e->getMessage(),
            ]);

            $cert->update(['status' => 'failed']);
        }
    }

    private function buildHtml(Certificate $cert): string
    {
        $name = $cert->user?->name ?? 'Student';
        $level = $cert->level?->name ?? 'Level';
        $score = $cert->percentage.'%';
        $serial = $cert->serial;
        $date = $cert->created_at?->format('d F Y') ?? now()->format('d F Y');

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head><title>Certificate - {$serial}</title></head>
        <body style="font-family: serif; text-align: center; padding: 60px;">
            <h1 style="color: #4F46E5;">Certificate of Completion</h1>
            <p style="font-size: 14px; color: #666;">This certifies that</p>
            <h2 style="font-size: 28px; margin: 20px 0;">{$name}</h2>
            <p>has successfully completed</p>
            <h3 style="color: #333;">{$level}</h3>
            <p>with a score of <strong>{$score}</strong></p>
            <hr style="margin: 40px auto; width: 60%; border: 1px solid #ddd;">
            <p style="font-size: 12px; color: #999;">
                Serial: {$serial} | Issued: {$date}<br>
                QuizPath — Daily Exam Practice
            </p>
        </body>
        </html>
        HTML;
    }
}
