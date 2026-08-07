<?php

declare(strict_types=1);

use App\Enums\PushStatus;
use App\Enums\PushTransport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-transport by design: Web Push today, Expo/APNs when the iOS app ships.
 * endpoint_hash is indexed rather than endpoint because a TEXT column cannot be
 * usefully unique-indexed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('transport', 16)->default(PushTransport::WebPush->value);

            // Web Push
            $table->text('endpoint')->nullable();
            $table->string('endpoint_hash', 64)->nullable();
            $table->string('public_key')->nullable();       // p256dh
            $table->string('auth_token')->nullable();
            $table->string('content_encoding', 24)->default('aes128gcm');

            // Expo / APNs / FCM
            $table->string('device_token')->nullable();

            $table->string('device_label', 120)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('browser', 48)->nullable();
            $table->string('platform', 48)->nullable();
            $table->string('timezone', 64)->nullable();

            $table->string('status', 16)->default(PushStatus::Active->value);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique('endpoint_hash');
            $table->unique(['transport', 'device_token'], 'uq_push_transport_token');
            $table->index(['user_id', 'status']);
            $table->index(['status', 'failure_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
