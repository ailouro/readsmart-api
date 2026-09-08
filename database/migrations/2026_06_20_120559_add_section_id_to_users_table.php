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
    Schema::table('users', function (Blueprint $table) {
        // Idadagdag natin ang section_id (nullable muna kasi pwedeng wala pa silang section pagka-sign up)
        $table->unsignedBigInteger('section_id')->nullable()->after('role');
        $table->foreign('section_id')->references('id')->on('sections')->onDelete('set null');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
