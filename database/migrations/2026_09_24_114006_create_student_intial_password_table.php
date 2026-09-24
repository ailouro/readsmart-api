<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Encrypted copy of a student's ORIGINAL password, kept only so the
        // admin can reprint the login slip. It lives in its own table (not a
        // users column) so it can never leak through User JSON responses.
        Schema::create('student_initial_passwords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->text('password'); // Crypt::encryptString(), decrypted only when printing
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_initial_passwords');
    }
};