<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rotating refresh tokens with family-based theft detection.
 *
 * Presenting a token that has already been rotated means it leaked, so the whole
 * family_id is revoked. See docs/phase-1/08-security-architecture.md section 3.2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refresh_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('family_id');
            $table->string('token_hash', 64)->unique();      // sha256, never the raw token
            $table->unsignedBigInteger('access_token_id')->nullable(); // paired Sanctum PAT
            $table->string('device_name', 120)->nullable();
            $table->string('device_hash', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_reason', 48)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at', 'expires_at']);
            $table->index('family_id');
            $table->index('access_token_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refresh_tokens');
    }
};
