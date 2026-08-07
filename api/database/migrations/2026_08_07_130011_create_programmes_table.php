<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\CycleReshuffleScope;
use App\Enums\UnlockMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A curriculum under an exam type: "FMGE Complete — 6 Months", 6 levels x 300 = 1,800 questions.
 * See docs/adr/002-programme-levels-and-cycles.md
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_category_id')->constrained()->restrictOnDelete();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('bank_label', 60)->nullable(); // "2026 Bank" — admin facing

            $table->unsignedSmallInteger('total_levels')->default(6);
            $table->unsignedSmallInteger('total_cycles')->default(2);  // 0 = repeat indefinitely
            $table->string('cycle_reshuffle_scope', 24)->default(CycleReshuffleScope::WithinLevel->value);
            $table->foreignId('next_programme_id')->nullable()->constrained('programmes')->nullOnDelete();

            $table->boolean('require_test_pass_to_advance')->default(false);
            $table->string('level_unlock_mode', 24)->default(UnlockMode::Immediate->value);

            $table->string('status', 16)->default(ContentStatus::Draft->value);
            $table->unsignedInteger('questions_count')->default(0);   // target 1,800
            $table->unsignedInteger('enrollments_count')->default(0);
            $table->unsignedSmallInteger('display_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['exam_category_id', 'status', 'display_order']);
            $table->index('status');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programmes');
    }
};
