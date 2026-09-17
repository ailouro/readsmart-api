<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idinadagdag ang audio_url para maimbak ang Cloudinary link ng
     * struggle-word recording ng bata (dati ay natatanggap ng server
     * pero itinatapon lang — walang column kung saan ito isasave).
     */
    public function up(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            $table->string('audio_url')->nullable()->after('total_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            $table->dropColumn('audio_url');
        });
    }
};