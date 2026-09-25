<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

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
        'teacher2_id', // 🛠️ FIX: co-teacher column existed in the DB but was
                       // missing here, so create()/update() mass assignment
                       // silently dropped it — the co-teacher select in the
                       // admin blade never actually persisted.
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

 // Siguraduhing naka-import ito sa taas kung hindi pa

public function teacher()
{
    return $this->belongsTo(User::class, 'teacher_id');
}

// 🆕 Co-teacher relationship, mirrors teacher() but for teacher2_id.
public function teacher2()
{
    return $this->belongsTo(User::class, 'teacher2_id');
}
}