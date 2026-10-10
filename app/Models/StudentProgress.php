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
    'total_slides',
    'total_words',         // IDINAGDAG
    'miscues_count',
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
    'struggled_words',
    'mic_status',
    'mic_peak_level',
    'asr_drops',
    'started_at',    
    'counts_estimated'=> 'boolean',
    'completed_at'
    
];

    protected static function boot()
{
    parent::boot();

    // Auto-stamp start time on first creation of a progress row
    static::creating(function ($progress) {
        if (!$progress->started_at) {
            $progress->started_at = now();
        }
    });

    // Auto-stamp completion time the moment is_reading_completed flips to true
    static::saving(function ($progress) {
        if ($progress->isDirty('is_reading_completed')
            && $progress->is_reading_completed
            && !$progress->completed_at) {
            $progress->completed_at = now();
        }
    });
}

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
        ->get()
        ->unique('story_id')
        ->map(function ($p) {
            $totalWords = $p->total_words ?? 0;
            $miscues = $p->miscues_count ?? 0;
            $correctWords = max(0, $totalWords - $miscues);

            // Comprehension %: kung walang laman pero may quiz, kuwentahin
            $compPct = $p->comprehension_score_pct;
            if (($compPct === null || (float) $compPct == 0)
                && (int) $p->total_questions > 0
                && (int) $p->quiz_score > 0) {
                $compPct = round($p->quiz_score / $p->total_questions * 100, 1);
            }

            return array_merge($p->story->toArray(), [
                'progress_id'             => $p->id,
                'test_type'               => $p->test_type,
                // Computation Breakdown Details para sa App UI
                'total_words'             => $totalWords,
                'miscues_count'           => $miscues,
                'correct_words_count'     => $correctWords,
                'word_reading_score_pct'  => $p->word_reading_score_pct,
                'quiz_score'              => $p->quiz_score,
                'total_questions'         => $p->total_questions,
                'comprehension_score_pct' => $compPct,
                'counts_estimated'        => (bool) $p->counts_estimated,
                'time_on_task_seconds'    => $p->time_on_task,
                'wpm'                     => $p->wpm,
                'reading_level'           => $p->reading_level,
                'date_completed'          => $p->completed_at ?? $p->updated_at,
            ]);
        })
        ->values();
}
}