<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema; // <-- Siguradong imported para hindi mag-fail ang Schema

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            
            // Explicitly nating sasabihin na ikakabit ito sa 'quizzes' table para walang saloobang error ang MySQL
            $table->foreignId('quiz_id')->constrained('quizzes')->onDelete('cascade');
            
            $table->text('question_text');   // Ang tanong
            $table->json('options');         // Ang mga choices (A, B, C, D)
            $table->string('correct_answer');// Ang tamang sagot
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};