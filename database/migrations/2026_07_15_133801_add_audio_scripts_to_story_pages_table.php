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
    Schema::table('story_pages', function (Blueprint $table) {
        // Idagdag ang json column para sa listahan ng mga babasahing text
        $table->json('audio_scripts')->nullable()->after('image_path');
    });
}

public function down(): void
{
    Schema::table('story_pages', function (Blueprint $table) {
        $table->dropColumn('audio_scripts');
    });
}
};
