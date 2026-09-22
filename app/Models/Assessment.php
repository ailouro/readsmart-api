<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assessment extends Model
{
    protected $fillable = [
        'class_id',
        'student_id',
        'test_type',
        'set_letter',
        'gst_raw',
        'student_grade',
        'start_grade',
        'status',
        'independent_grade',
        'instructional_grade',
        'frustration_grade',
        'below_range',
        'above_range',
        'session_data',
    ];

    protected $casts = [
        'below_range' => 'boolean',
        'above_range' => 'boolean',
        'session_data' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }
}