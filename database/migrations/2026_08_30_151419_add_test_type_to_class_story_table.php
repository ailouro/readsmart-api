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
        Schema::table('class_story', function (Blueprint $table) {
            $table->string('test_type')->default('post_test');
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('class_story', function (Blueprint $table) {
            $table->dropColumn('test_type');
            $table->string('test_type')->default('post_test');
            //
        });
    }
};
