<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('phone', 20)->nullable();
            $table->string('avatar_path')->nullable();

            // Fast-path copy of the primary spatie role. Read by EnsureRole on every
            // request; spatie's pivot remains authoritative for permissions.
            $table->string('role', 16)->default(UserRole::Student->value);
            $table->string('status', 24)->default(UserStatus::PendingVerification->value);

            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('locale', 10)->default('en');

            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_active_at')->nullable();

            // Account lockout (see LoginThrottleService)
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();

            // Bumped on "logout all devices" / password reset: any token issued
            // before this moment is rejected without needing to enumerate tokens.
            $table->timestamp('sessions_valid_after')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['role', 'status']);
            $table->index('last_active_at');
            $table->index('deleted_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email', 190)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
