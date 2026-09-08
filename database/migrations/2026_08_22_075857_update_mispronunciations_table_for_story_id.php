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
        Schema::table('mispronunciations', function (Blueprint $table) {
            $table->foreignId('story_id')->nullable()->constrained('stories')->onDelete('cascade');
            $table->dropColumn(['story_title', 'slide_index']);
        });
    }

    public function down(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            $table->dropForeign(['story_id']);
            $table->dropColumn('story_id');
            $table->string('story_title')->nullable();
            $table->integer('slide_index')->nullable();
        });
    }
};
