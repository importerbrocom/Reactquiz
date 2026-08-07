<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Streaks are per programme, so a student in two programmes keeps them separate. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_streaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('programme_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('current_streak')->default(0);
            $table->unsignedSmallInteger('longest_streak')->default(0);
            $table->date('last_activity_date')->nullable();   // in the student's timezone
            $table->unsignedSmallInteger('freeze_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'programme_id']);
            $table->index('last_activity_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_streaks');
    }
};
