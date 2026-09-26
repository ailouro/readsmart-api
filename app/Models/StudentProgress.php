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

    /**
     * Alias of user(). AnalyticsController queries this relation as
     * `student.classes` (e.g. whereHas('student.classes', ...)) to find the
     * classes/sections a learner belongs to, but that relation name didn't
     * exist on this model, so those queries were failing. Fixed by adding
     * `student` as an alias for the same user_id foreign key.
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function story()
    {
        return $this->belongsTo(Story::class, 'story_id');
    }

    /**
     * Stories a student has finished, ready for "Aking Silid-Aklatan".
     *
     * Goes through the story() relation instead of a raw join so that:
     *  - the full story comes back WITH its pages + quiz (the app hands this
     *    straight to StoryViewerScreen for re-reading, which needs the pages),
     *  - a story finished more than once is listed once (latest attempt wins),
     *  - progress rows pointing at a story that no longer exists are skipped
     *    instead of producing blank/"Unknown" entries.
     *
     * The story's own `id` is preserved; the progress fields are merged on top.
     */
    public static function completedStoriesFor($userId)
    {
        return static::query()
            ->where('user_id', $userId)
            ->where('is_reading_completed', true)
            ->whereHas('story')
            ->with(['story.pages', 'story.quiz'])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->unique('story_id')
            ->map(function ($p) {
                return array_merge($p->story->toArray(), [
                    'quiz_score'      => $p->quiz_score,
                    'total_questions' => $p->total_questions,
                    'reading_level'   => $p->reading_level,
                    'test_type'       => $p->test_type,
                    'date_completed'  => $p->updated_at,
                ]);
            })
            ->values();
    }
}