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
    return $this->belongsToMany(Story::class, 'student_progress')
                ->wherePivot('is_completed', true);
}



}