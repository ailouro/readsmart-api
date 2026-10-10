<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $add = [
            'word_reading_score_pct'  => fn (Blueprint $t) => $t->decimal('word_reading_score_pct', 5, 1)->nullable(),
            'comprehension_score_pct' => fn (Blueprint $t) => $t->decimal('comprehension_score_pct', 5, 1)->nullable(),
            'counts_estimated'        => fn (Blueprint $t) => $t->boolean('counts_estimated')->default(false),
            'reading_profile'         => fn (Blueprint $t) => $t->text('reading_profile')->nullable(),
            'struggled_words'         => fn (Blueprint $t) => $t->text('struggled_words')->nullable(),
        ];

        foreach ($add as $name => $make) {
            if (!Schema::hasColumn('student_progress', $name)) {
                Schema::table('student_progress', fn (Blueprint $t) => $make($t));
            }
        }

        // Para tanggapin ang 'not_started' / 'incomplete' at ang decimal/null
        Schema::table('student_progress', function (Blueprint $t) {
            $t->string('reading_level', 20)->nullable()->change();
            $t->decimal('oral_fluency_accuracy', 5, 1)->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        // sinadyang walang rollback
    }
};