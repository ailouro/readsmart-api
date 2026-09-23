<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
    'user_id',
    'parent_id',
    'name',
    'first_name',
    'last_name',
    'grade_level',
    'section',
];

    public function classes()
    {
        return $this->belongsToMany(SchoolClass::class, 'class_student');
    }
    public function mispronunciations()
{
    return $this->hasMany(Mispronunciation::class);
}

public function schoolClasses()
{
    return $this->belongsToMany(SchoolClass::class, 'class_student');
}
public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}
// Sa Student.php model
public function completedStories() {
    // student_progress is keyed by the USER id (user_id), not students.id,
    // so tell Eloquent which pivot column and which parent column to use.
    // (Default was student_progress.student_id, which doesn't exist.)
    return $this->belongsToMany(
            Story::class,
            'student_progress',
            'user_id',   // pivot column pointing at this student
            'story_id',  // pivot column pointing at the story
            'user_id',   // students.user_id  <->  student_progress.user_id
            'id'
        )
        ->wherePivot('is_reading_completed', true);
}



}