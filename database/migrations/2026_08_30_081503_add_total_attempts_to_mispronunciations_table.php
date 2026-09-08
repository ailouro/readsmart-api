<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            if (!Schema::hasColumn('mispronunciations', 'total_attempts')) {
                $table->integer('total_attempts')->default(1)->after('word');
            }

            // slide_index is also in the model's $fillable but the app never
            // sends it — make sure it won't block inserts either.
            if (!Schema::hasColumn('mispronunciations', 'slide_index')) {
                $table->integer('slide_index')->nullable()->after('word');
            }
        });
    }

    public function down(): void
    {
        Schema::table('mispronunciations', function (Blueprint $table) {
            $table->dropColumn(['total_attempts', 'slide_index']);
        });
    }
};