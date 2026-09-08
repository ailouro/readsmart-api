<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_id',
        'name',
        'grade_level',
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

}