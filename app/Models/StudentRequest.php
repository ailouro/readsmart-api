<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_name', 
        'lrn', 
        'grade_level', 
        'section', 
        'parent_email', 
        'status'
    ];
}