<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 160);
            $table->string('body', 500);
            $table->string('action_url')->nullable();
            $table->string('audience', 24)->default('all');  // all|programme|level|inactive|custom
            $table->json('audience_filter')->nullable();
            $table->string('status', 16)->default('draft');   // draft|scheduled|sending|sent|cancelled
            $table->timestamp('scheduled_for')->nullable();
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->uuid('batch_id')->nullable();             // Laravel job batch, for progress
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
