<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

if (!Schema::hasColumn('users', 'parent_id')) {
    Schema::table('users', function(Blueprint $table) {
        $table->foreignId('parent_id')->nullable()->constrained('users')->onDelete('cascade');
    });
    echo "Added parent_id column\n";
} else {
    echo "parent_id column already exists\n";
}
