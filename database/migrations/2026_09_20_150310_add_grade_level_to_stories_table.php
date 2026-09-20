<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            if (!Schema::hasColumn('stories', 'grade_level')) {
                // Intentionally no ->default(...) here: existing stories
                // should stay unlabeled (null) until a teacher manually
                // picks Grade 5 or Grade 6 for them in the editor, rather
                // than every pre-existing story silently becoming "Grade 5".
                $table->string('grade_level')->nullable();
            }

            // Defensive: your app already reads/writes story_type (used to
            // split the Pre Test / Post Test library sections), so this
            // column most likely already exists. This only adds it if it
            // is somehow missing, so the migration is safe to run either way.
            if (!Schema::hasColumn('stories', 'story_type')) {
                $table->string('story_type')->nullable()->default('pre_test');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            if (Schema::hasColumn('stories', 'grade_level')) {
                $table->dropColumn('grade_level');
            }
        });
    }
};
