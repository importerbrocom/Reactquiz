<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Canonical answer choices. The `option_key` is stable and is what the client
 * submits; display order is permuted per exposure and never stored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->char('option_key', 1);                 // a | b | c | d (| e)
            $table->string('option_text', 500);
            $table->string('option_image_path')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('display_order')->default(0);
            // "All of the above" must stay last even when the others shuffle.
            $table->boolean('pin_last')->default(false);
            $table->timestamps();

            $table->unique(['question_id', 'option_key']);
            $table->index(['question_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_options');
    }
};
