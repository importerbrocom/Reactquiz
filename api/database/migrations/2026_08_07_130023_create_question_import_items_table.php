<?php

declare(strict_types=1);

use App\Enums\ImportItemStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging rows for a spreadsheet import. Deliberately flat (option_a..option_d)
 * rather than normalised: written by bulk insert, read only by the review UI,
 * discarded after approval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_import_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');         // spreadsheet row, for the error report
            $table->string('external_ref', 32)->nullable(); // the sheet's own question_no

            $table->text('question_text')->nullable();
            $table->string('option_a', 500)->nullable();
            $table->string('option_b', 500)->nullable();
            $table->string('option_c', 500)->nullable();
            $table->string('option_d', 500)->nullable();
            $table->char('correct_option', 1)->nullable();
            $table->text('explanation')->nullable();
            $table->string('topic', 120)->nullable();
            $table->string('tags', 500)->nullable();
            $table->string('source', 160)->nullable();
            $table->string('image_filename')->nullable();
            $table->string('row_status', 16)->nullable();   // the sheet's status column

            $table->string('status', 16)->default(ImportItemStatus::Valid->value);
            $table->json('errors')->nullable();             // ["missing_option_c", ...]
            $table->json('warnings')->nullable();
            $table->string('question_hash', 64)->nullable();
            $table->foreignId('duplicate_of_question_id')->nullable()
                ->constrained('questions')->nullOnDelete();
            $table->foreignId('created_question_id')->nullable()
                ->constrained('questions')->nullOnDelete();

            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->unique(['question_import_id', 'row_number']);
            $table->index(['question_import_id', 'status']);
            $table->index('question_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_items');
    }
};
