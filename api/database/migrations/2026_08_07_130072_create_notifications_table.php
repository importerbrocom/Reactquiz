<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's DatabaseNotification schema plus the indexed columns this app needs.
 *
 * Keeping the framework shape means $user->notifications, unreadNotifications and
 * markAsRead() all work for free; the extra columns give us a queryable inbox
 * (status, scheduling) without stuffing everything into the data JSON.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();

            // --- application extensions ---------------------------------------
            $table->foreignId('campaign_id')->nullable()
                ->constrained('notification_campaigns')->nullOnDelete();
            $table->string('category', 40)->nullable();       // App\Enums\NotificationType
            $table->string('status', 16)->default(NotificationStatus::Queued->value);
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->index(['notifiable_type', 'notifiable_id', 'status', 'created_at'], 'ix_notif_owner_status');
            $table->index(['notifiable_type', 'notifiable_id', 'read_at'], 'ix_notif_owner_read');
            $table->index(['category', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
