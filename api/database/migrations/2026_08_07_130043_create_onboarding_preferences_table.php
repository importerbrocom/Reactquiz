<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('step', 24)->default('welcome');      // resume pointer
            $table->json('completed_steps')->nullable();
            $table->foreignId('selected_exam_category_id')->nullable()
                ->constrained('exam_categories')->nullOnDelete();
            $table->foreignId('selected_programme_id')->nullable()
                ->constrained('programmes')->nullOnDelete();
            $table->time('reminder_time')->nullable();
            $table->boolean('notifications_opt_in')->default(false);
            $table->string('language', 10)->default('en');
            $table->string('timezone', 64)->nullable();
            $table->string('study_goal', 32)->nullable();
            $table->unsignedSmallInteger('daily_goal_minutes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_preferences');
    }
};
