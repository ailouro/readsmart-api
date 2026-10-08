<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            // ok | no_audio | no_sound | no_transcript | unstable
            $table->string('mic_status', 20)->nullable()->after('struggled_words');
            $table->decimal('mic_peak_level', 5, 3)->nullable()->after('mic_status');
            $table->unsignedSmallInteger('asr_drops')->nullable()->after('mic_peak_level');
        });
    }

    public function down(): void
    {
        Schema::table('student_progress', function (Blueprint $table) {
            $table->dropColumn(['mic_status', 'mic_peak_level', 'asr_drops']);
        });
    }
};