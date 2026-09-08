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
    Schema::table('class_student', function (Blueprint $table) {
        // 1. Drop foreign key constraint first
        $table->dropForeign(['school_class_id']);
        
        // 2. Drop the column
        $table->dropColumn('school_class_id');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_student', function (Blueprint $table) {
            //
        });
    }
};
