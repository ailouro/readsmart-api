use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('mispronunciations', function (Blueprint $table) {
                $table->dropForeign(['student_id']);
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('mispronunciations', function (Blueprint $table) {
                $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }
};