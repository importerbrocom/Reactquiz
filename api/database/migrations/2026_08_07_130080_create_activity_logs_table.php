<?php

declare(strict_types=1);

use App\Enums\ActivitySeverity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Business audit trail, queryable by admins. Distinct from application logs,
 * which are for engineers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 64);          // auth.login_failed, quiz.completed, admin.level.published
            $table->nullableMorphs('subject');
            $table->json('properties')->nullable();
            $table->string('reason')->nullable(); // mandatory for support overrides
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('severity', 16)->default(ActivitySeverity::Info->value);
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'created_at']);
            $table->index(['severity', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
