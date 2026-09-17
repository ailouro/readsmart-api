<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idinadagdag ang audio_urls (JSON) column sa story_pages para maimbak
     * ang generated TTS URL ng bawat audio_script nang hiwa-hiwalay (keyed by
     * script_index), sa halip na iisang audio_url column lang na nagkakaroon
     * ng overwrite kapag maraming scripts ang isang page.
     */
    public function up(): void
    {
        Schema::table('story_pages', function (Blueprint $table) {
            $table->json('audio_urls')->nullable()->after('audio_url');
        });
    }

    public function down(): void
    {
        Schema::table('story_pages', function (Blueprint $table) {
            $table->dropColumn('audio_urls');
        });
    }
};