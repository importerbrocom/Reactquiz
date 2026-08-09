<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alt text for image answer options.
 *
 * Question stems already carry `question_image_alt`; options were missed. Several FMGE
 * items are pure-image choices (ECG traces, radiographs), and without alt text a
 * screen-reader user is offered four unlabelled buttons.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_options', function (Blueprint $table) {
            $table->string('option_image_alt')->nullable()->after('option_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('question_options', function (Blueprint $table) {
            $table->dropColumn('option_image_alt');
        });
    }
};
