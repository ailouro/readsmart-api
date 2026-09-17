<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_pages', function (Blueprint $table) {

            $table->id();

            $table->foreignId('story_id')
                ->constrained()
                ->onDelete('cascade');

            $table->string('image_path');

            $table->integer('page_number');

            $table->timestamps();

            $table->json('audio_urls')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_pages');
    }
};