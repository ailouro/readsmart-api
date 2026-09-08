<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Words the student got wrong on the first try but corrected themselves on
// a retry. These NEVER count against oral_fluency_accuracy — that's still
// computed purely from Mispronunciation/failed words. This table exists
// purely so teachers can see "needed prompting on: cat, house" without it
// looking like it hurt the score, per Phil-IRI's self-correction rule
// (Table 5: "Don't count self-correction as an error").
class SelfCorrection extends Model
{
    use HasFactory;

    protected $table = 'self_corrections';

    protected $fillable = [
        'student_id',
        'story_id',
        'slide_index',
        'word',
        'total_attempts',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id');
    }
}