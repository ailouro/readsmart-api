<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request; // 👈 Idagdag ito sa taas ng file!

class Mispronunciation extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'story_id', // story_id instead of story_title
        'story_title', // keep it just in case
        'slide_index',
        'word',
        'total_attempts',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id'); // User::class since they use users table usually
    }

    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id');
    }
}