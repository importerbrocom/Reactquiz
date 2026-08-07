<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Month-end test definition (day 31 of a level). Its question set is derived per
 * student from enrollment_day_questions, so there is no snapshot table here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedSmallInteger('day_number')->default(31);
            $table->string('title', 160)->nullable();
            $table->unsignedSmallInteger('question_count')->default(300);
            $table->decimal('pass_percentage', 5, 2)->default(50.00);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->unsignedSmallInteger('attempt_limit')->default(0);   // 0 = unlimited
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('allow_pause')->default(false);
            $table->string('status', 16)->default(ContentStatus::Active->value);
            $table->timestamps();

            $table->unique(['level_id', 'version']);
            $table->index(['level_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('level_tests');
    }
};
