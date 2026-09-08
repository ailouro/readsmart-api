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
    Schema::create('quizzes', function (Blueprint $table) {
        $table->id();
        // I-kakabit natin ang quiz sa partikular na kwento
        $table->foreignId('story_id')->constrained()->onDelete('cascade');
        $table->string('title')->nullable(); // Pwede mong lagyan ng pangalan ang quiz (e.g., "Quiz 1")
        $table->timestamps();
    });
}
};
