<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('programme_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('level_test_attempt_id')->nullable()
                ->constrained()->cascadeOnDelete();
            $table->string('serial', 32)->unique();     // QP-2026-0000123
            $table->decimal('score', 7, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('status', 16)->default('pending'); // pending|issued|revoked
            $table->string('storage_path', 512)->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            // Re-running issuance is idempotent.
            $table->unique('level_test_attempt_id', 'uq_cert_attempt');
            $table->index(['user_id', 'programme_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
