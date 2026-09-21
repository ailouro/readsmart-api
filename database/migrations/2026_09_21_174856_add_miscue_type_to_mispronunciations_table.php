<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            if (!Schema::hasColumn('mispronunciations', 'miscue_type')) {
                $table->string('miscue_type')->default('mispronunciation')->after('word');
            }
            if (!Schema::hasColumn('mispronunciations', 'slide_index')) {
                $table->integer('slide_index')->default(0)->after('story_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            $table->dropColumn(['miscue_type', 'slide_index']);
        });
    }
};