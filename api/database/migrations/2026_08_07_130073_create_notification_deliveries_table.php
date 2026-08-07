<?php

declare(strict_types=1);

use App\Enums\NotificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** One inbox notification fans out to N devices with independent outcomes. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('notification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('push_subscription_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->string('channel', 16)->default('webpush');
            $table->string('status', 16)->default(NotificationStatus::Queued->value);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error_code', 48)->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();

            $table->index('notification_id');
            $table->index(['status', 'created_at']);
            $table->index('push_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
