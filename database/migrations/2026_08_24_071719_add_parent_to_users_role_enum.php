<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
{
    if (\DB::getDriverName() !== 'sqlite') {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('student', 'teacher', 'parent', 'admin') NOT NULL DEFAULT 'student'");
    }
}

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student'");
        }
    }
};