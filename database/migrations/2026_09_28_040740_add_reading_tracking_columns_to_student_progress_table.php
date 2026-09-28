<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_progress', function (Blueprint $t) {
            if (!Schema::hasColumn('student_progress', 'current_slide')) {
                $t->unsignedInteger('current_slide')->nullable();
            }
            if (!Schema::hasColumn('student_progress', 'total_slides')) {
                $t->unsignedInteger('total_slides')->nullable();
            }
            if (!Schema::hasColumn('student_progress', 'status')) {
                $t->string('status')->nullable();
            }
            if (!Schema::hasColumn('student_progress', 'started_at')) {
                $t->timestamp('started_at')->nullable();
            }
            if (!Schema::hasColumn('student_progress', 'completed_at')) {
                $t->timestamp('completed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Sinasadya kong iwan itong walang laman para hindi mabura ang data
        // ng mga columns kapag nag-rollback, dahil baka may existing na ang ilan.
    }
};