<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentProgress extends Model
{
    use HasFactory;

    protected $table = 'student_progress';

    protected $fillable = [
    'user_id',
    'story_id',
    'test_type',
    'quiz_score',
    'total_questions',
    'oral_fluency_accuracy',
    'reading_level',
    'time_on_task',
    'wpm',
    'status',
    'current_slide',
    'is_reading_completed',
    'comprehension_score_pct',
    'word_reading_score_pct',
    'reading_profile',
    'struggled_words', // <- Add this
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function story()
    {
        return $this->belongsTo(Story::class);
    }
}