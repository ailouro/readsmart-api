<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'school_classes';

    protected $fillable = [
        'name',
        'section',
        'subject',
        'room',
        'grade_level',
        'class_code',
        'teacher_id',
    ];

    public function stories()
{
    return $this->belongsToMany(Story::class, 'class_story')
                ->withPivot('test_type')   // <-- ito yung kulang, malamang
                ->withTimestamps();        // (kung meron kang timestamps sa pivot)
}

public function students()
{
    return $this->belongsToMany(User::class, 'class_student', 'class_id', 'student_id')->withTimestamps();
}
}