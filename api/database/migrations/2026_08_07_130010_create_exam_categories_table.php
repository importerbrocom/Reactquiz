<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Exam type: FMGE, AMC, NEET-PG … admin-created, nothing hard-coded. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_categories', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('image_path')->nullable();
            $table->string('color_token', 24)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('status', 16)->default(ContentStatus::Active->value);
            $table->unsignedInteger('programmes_count')->default(0); // counter cache
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'display_order']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_categories');
    }
};
