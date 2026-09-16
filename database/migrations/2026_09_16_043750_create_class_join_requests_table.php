<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_join_requests', function (Blueprint $table) {
            $table->id();

            // Who asked to join. Kept as a plain foreign id (not a
            // relation to a specific student row) because at request time
            // we only have the name the parent typed — the same pattern
            // ParentController::enroll() already used to look students up.
            $table->foreignId('parent_id')->constrained('users')->cascadeOnDelete();
            $table->string('student_name');

            // NOTE: adjust 'school_classes' below if your SchoolClass
            // model uses a different table name (check for a protected
            // $table property on the model).
            $table->foreignId('school_class_id')
                ->constrained('school_classes')
                ->cascadeOnDelete();

            $table->enum('status', ['pending', 'approved', 'declined'])
                ->default('pending');

            // Null = parent hasn't seen the approve/decline notification
            // yet. Same pattern as student_requests.notified_at.
            $table->timestamp('notified_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_join_requests');
    }
};