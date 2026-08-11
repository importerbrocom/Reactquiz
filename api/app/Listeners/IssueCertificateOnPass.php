<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LevelTestGraded;
use App\Jobs\RenderCertificatePdfJob;
use App\Models\Certificate;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Listens to LevelTestGraded and issues a certificate if:
 * 1. The student passed
 * 2. The level has issue_certificate = true
 * 3. No certificate already exists for this attempt (idempotent)
 *
 * Creates a pending Certificate record, then dispatches the PDF render job.
 */
class IssueCertificateOnPass implements ShouldQueue
{
    public function handle(LevelTestGraded $event): void
    {
        $attempt = $event->attempt;

        // Only issue on pass
        if (! $attempt->passed) {
            return;
        }

        // Check if the level allows certificates
        $level = $attempt->levelEnrollment->level;
        if (! ($level->issue_certificate ?? false)) {
            return;
        }

        // Idempotent: don't issue twice for the same attempt
        if (Certificate::where('level_test_attempt_id', $attempt->id)->exists()) {
            return;
        }

        // Generate unique serial number: QP-YYYY-NNNNNNN
        $serial = 'QP-'.now()->year.'-'.str_pad(
            (string) (Certificate::count() + 1),
            7,
            '0',
            STR_PAD_LEFT,
        );

        // Create pending certificate
        $certificate = Certificate::create([
            'user_id' => $attempt->user_id,
            'level_id' => $level->id,
            'programme_id' => $attempt->levelEnrollment->programmeEnrollment?->programme_id ?? null,
            'level_test_attempt_id' => $attempt->id,
            'serial' => $serial,
            'score' => $attempt->score,
            'percentage' => $attempt->percentage,
            'status' => 'pending',
            'issued_at' => null,
        ]);

        // Dispatch PDF rendering job
        RenderCertificatePdfJob::dispatch($certificate);
    }
}
