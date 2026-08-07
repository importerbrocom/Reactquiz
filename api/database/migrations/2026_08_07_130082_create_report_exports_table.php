<?php

declare(strict_types=1);

use App\Enums\ReportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type', 48);
            $table->string('format', 8);
            $table->json('filters')->nullable();
            $table->string('status', 16)->default(ReportStatus::Queued->value);
            $table->unsignedInteger('row_count')->nullable();
            $table->string('storage_disk', 32)->nullable();
            $table->string('storage_path', 512)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();   // signed URL + file both expire
            $table->timestamp('downloaded_at')->nullable();
            $table->unsignedSmallInteger('download_count')->default(0);
            $table->timestamps();

            $table->index(['requested_by', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
