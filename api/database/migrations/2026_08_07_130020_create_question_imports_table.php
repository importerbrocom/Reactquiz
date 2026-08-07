<?php

declare(strict_types=1);

use App\Enums\ImportStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** XLSX/CSV bulk import batches. See docs/import-format.md */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('level_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();

            $table->string('original_filename');
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path', 512);
            $table->string('mime_type', 96);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('file_checksum', 64);          // sha256 → duplicate upload detection

            $table->string('status', 24)->default(ImportStatus::Uploaded->value);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_valid')->default(0);
            $table->unsignedInteger('rows_warning')->default(0);
            $table->unsignedInteger('rows_rejected')->default(0);
            $table->unsignedInteger('rows_duplicate')->default(0);
            $table->unsignedInteger('rows_imported')->default(0);

            $table->string('failure_reason', 64)->nullable();
            $table->text('failure_detail')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index('level_id');
            $table->index('file_checksum');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_imports');
    }
};
