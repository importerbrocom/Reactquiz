<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The day container inside a level. Question *membership* is per student
 * (enrollment_day_questions) — this table only defines that Day N exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('title', 160)->nullable();
            $table->unsignedSmallInteger('question_count')->default(10);
            $table->string('status', 16)->default(ContentStatus::Active->value);
            $table->date('available_from')->nullable();   // unlock_mode = scheduled only
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['level_id', 'day_number']);   // one quiz per level per day
            $table->index(['level_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_quizzes');
    }
};
