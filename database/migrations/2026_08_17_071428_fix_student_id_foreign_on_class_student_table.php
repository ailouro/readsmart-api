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
        // Drop foreign key pointing to 'students'
        $table->dropForeign(['student_id']);
        
        // Re-add foreign key pointing to 'users'
        $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
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
