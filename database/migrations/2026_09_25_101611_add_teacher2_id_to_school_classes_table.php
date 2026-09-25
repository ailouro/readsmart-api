<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an optional second (co-)teacher to a class.
     *
     * NOTE: check your actual classes table name first — this project has
     * used both `class_code` and `code` for the class code column, so
     * confirm the table is really `school_classes` (run
     * `php artisan tinker` -> `(new App\Models\SchoolClass)->getTable()`)
     * before running this migration, and edit the name below if it differs.
     */
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->foreignId('teacher2_id')->nullable()->after('teacher_id')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table) {
            $table->dropForeign(['teacher2_id']);
            $table->dropColumn('teacher2_id');
        });
    }
};