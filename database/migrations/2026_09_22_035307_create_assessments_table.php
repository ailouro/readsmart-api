<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();

            $table->enum('test_type', ['pre_test', 'post_test']);
            $table->string('set_letter'); // stored as 'A'-'D' (letter only)

            // Pre-test inputs
            $table->unsignedTinyInteger('gst_raw')->nullable();
            $table->unsignedTinyInteger('student_grade');

            // Where the branching search should begin
            $table->unsignedTinyInteger('start_grade')->nullable();

            $table->enum('status', ['assigned', 'in_progress', 'completed'])
                ->default('assigned');

            // Outcome, filled in once PhilIriSession finishes
            $table->unsignedTinyInteger('independent_grade')->nullable();
            $table->unsignedTinyInteger('instructional_grade')->nullable();
            $table->unsignedTinyInteger('frustration_grade')->nullable();
            $table->boolean('below_range')->default(false);
            $table->boolean('above_range')->default(false);

            // Full PhilIriSession.toJson() as a backup, in case any of the
            // individual columns above failed to save for some reason.
            $table->json('session_data')->nullable();

            $table->timestamps();

            // One assessment per student/class/test_type/set at a time.
            $table->unique(
                ['class_id', 'student_id', 'test_type', 'set_letter'],
                'assessments_unique_attempt'
            );
        });
    }

    public function down()
    {
        Schema::dropIfExists('assessments');
    }
};