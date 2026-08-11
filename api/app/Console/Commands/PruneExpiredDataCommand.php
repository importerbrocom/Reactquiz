<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\LoginAttempt;
use App\Models\NotificationDelivery;
use App\Models\ReportExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Scheduled data retention enforcement.
 *
 * Per docs/phase-1/08-security-architecture.md §9:
 * - activity_logs: 12 months (security severity 24 months)
 * - notifications: 90 days
 * - notification_deliveries: 90 days
 * - login_attempts: 90 days
 * - report_exports files: 7 days
 *
 * Run daily via scheduler: $schedule->command('app:prune-expired-data')->daily();
 */
class PruneExpiredDataCommand extends Command
{
    protected $signature = 'app:prune-expired-data';

    protected $description = 'Prune expired data per retention policy';

    public function handle(): int
    {
        $this->info('Pruning expired data...');

        // Activity logs: 12 months (security: 24 months)
        $regularLogs = ActivityLog::where('severity', '!=', 'security')
            ->where('created_at', '<', now()->subMonths(12))
            ->delete();
        $securityLogs = ActivityLog::where('severity', 'security')
            ->where('created_at', '<', now()->subMonths(24))
            ->delete();
        $this->line("  Activity logs: {$regularLogs} regular + {$securityLogs} security deleted");

        // Login attempts: 90 days
        $loginAttempts = LoginAttempt::where('created_at', '<', now()->subDays(90))->delete();
        $this->line("  Login attempts: {$loginAttempts} deleted");

        // Notification deliveries: 90 days
        $deliveries = NotificationDelivery::where('created_at', '<', now()->subDays(90))->delete();
        $this->line("  Notification deliveries: {$deliveries} deleted");

        // Report exports: delete files after 7 days, records after 30 days
        $expiredReports = ReportExport::where('expires_at', '<', now())
            ->whereNotNull('storage_path')
            ->get();

        foreach ($expiredReports as $report) {
            if ($report->storage_disk && $report->storage_path) {
                Storage::disk($report->storage_disk)->delete($report->storage_path);
            }
            $report->update(['storage_path' => null, 'status' => 'expired']);
        }
        $this->line("  Report files: {$expiredReports->count()} cleaned up");

        $this->info('Done.');

        return self::SUCCESS;
    }
}
