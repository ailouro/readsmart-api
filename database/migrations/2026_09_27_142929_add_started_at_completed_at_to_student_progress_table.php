<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('student_progress', function (Blueprint $table) {
        $table->timestamp('started_at')->nullable()->after('status');
        $table->timestamp('completed_at')->nullable()->after('started_at');
    });
}

public function down()
{
    Schema::table('student_progress', function (Blueprint $table) {
        $table->dropColumn(['started_at', 'completed_at']);
    });
}
};
