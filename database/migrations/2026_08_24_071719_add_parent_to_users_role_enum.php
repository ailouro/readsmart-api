<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // We use DB::statement because altering ENUMs using standard Blueprint methods can be tricky
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('student', 'teacher', 'parent', 'admin') NOT NULL DEFAULT 'student'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('student', 'teacher', 'admin') NOT NULL DEFAULT 'student'");
    }
};