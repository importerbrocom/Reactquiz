<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A question block: stem + options + which option is correct + explanation.
 *
 * correct_option / correct_answer_text are maintained denormalisations of
 * question_options so that grading is a single primary-key read with no join.
 * There is NO authored difficulty column — difficulty is observed (see
 * observed_difficulty, computed nightly from real wrong-answer rates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('level_id')->constrained()->cascadeOnDelete();
            $table->text('question_text');
            $table->string('question_image_path')->nullable();
            $table->unsignedSmallInteger('question_image_width')->nullable();  // reserve layout space
            $table->unsignedSmallInteger('question_image_height')->nullable(); // → protects CLS budget
            $table->string('question_image_alt')->nullable();

            $table->char('correct_option', 1);
            $table->string('correct_answer_text', 500)->nullable();
            $table->text('explanation');

            $table->string('topic', 120)->nullable();
            $table->json('tags')->nullable();
            $table->string('source', 160)->nullable();

            $table->string('status', 16)->default(ContentStatus::Active->value);
            $table->string('question_hash', 64);     // sha256(normalised stem + sorted options)

            // 0 when an option's text references other option letters ("Both A and B"),
            // in which case per-exposure shuffling must be disabled. See docs/adr/003.
            $table->boolean('shuffle_options')->default(true);

            // Observed analytics, refreshed by a queued nightly job — never per request.
            $table->unsignedInteger('times_served')->default(0);
            $table->unsignedInteger('times_correct')->default(0);
            $table->unsignedInteger('times_incorrect')->default(0);
            $table->decimal('observed_difficulty', 5, 2)->nullable(); // % answered incorrectly

            $table->foreignId('question_import_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['level_id', 'question_hash']);   // deduplication guarantee
            $table->index(['level_id', 'status']);
            $table->index(['level_id', 'observed_difficulty']);
            $table->index('topic');
            $table->index('question_hash');
            $table->index('deleted_at');
        });

        if (DB::getDriverName() === 'mysql') {
            // Admin question search. MySQL only; SQLite test runs fall back to LIKE.
            DB::statement('ALTER TABLE questions ADD FULLTEXT ft_questions_text (question_text)');
            // The one invariant worth enforcing in the database itself.
            DB::statement("ALTER TABLE questions ADD CONSTRAINT chk_questions_correct_option
                           CHECK (correct_option IN ('a','b','c','d','e'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
