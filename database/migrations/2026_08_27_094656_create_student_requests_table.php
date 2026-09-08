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
        Schema::create('student_requests', function (Blueprint $table) {
            $table->id();
            $table->string('student_name');
            $table->string('lrn')->unique();
            $table->string('grade_level');
            $table->string('section');
            $table->string('parent_email');
            $table->string('status')->default('pending'); // Pwedeng 'pending', 'approved', o 'rejected'
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_requests');
    }
};
