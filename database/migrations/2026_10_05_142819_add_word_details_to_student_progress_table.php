<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
   public function up(): void
{
    Schema::table('student_progress', function (Blueprint $table) {
        $table->integer('total_words')->nullable()->after('story_id');
        $table->integer('miscues_count')->default(0)->after('total_words');
    });
}

public function down(): void
{
    Schema::table('student_progress', function (Blueprint $table) {
        $table->dropColumn(['total_words', 'miscues_count']);
    });
}
};
