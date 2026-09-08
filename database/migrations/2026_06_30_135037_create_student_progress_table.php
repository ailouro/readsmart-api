<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema; // <-- Ito ang kailangan para mawala ang dilaw kay Schema

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_progress', function (Blueprint $table) {
            $table->id();
            
            // Mga Foreign Keys (Ikakabit sa users at stories table)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            
            // Mga columns para sa scores at data ng bata
            $table->integer('quiz_score');
            $table->integer('total_questions');
            $table->integer('oral_fluency_accuracy')->default(0);
            $table->integer('time_on_task')->default(0); // sa segundo o minuto
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_progress');
    }
};