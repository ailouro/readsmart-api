@'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ok | no_audio | no_sound | no_transcript | unstable
        if (!Schema::hasColumn('student_progress', 'mic_status')) {
            Schema::table('student_progress', function (Blueprint $table) {
                $table->string('mic_status', 20)->nullable();
            });
        }
        if (!Schema::hasColumn('student_progress', 'mic_peak_level')) {
            Schema::table('student_progress', function (Blueprint $table) {
                $table->decimal('mic_peak_level', 5, 3)->nullable();
            });
        }
        if (!Schema::hasColumn('student_progress', 'asr_drops')) {
            Schema::table('student_progress', function (Blueprint $table) {
                $table->unsignedSmallInteger('asr_drops')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['mic_status', 'mic_peak_level', 'asr_drops'] as $col) {
            if (Schema::hasColumn('student_progress', $col)) {
                Schema::table('student_progress', function (Blueprint $table) use ($col) {
                    $table->dropColumn($col);
                });
            }
        }
    }
};
