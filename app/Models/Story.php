<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Story extends Model
{
    protected $fillable = [

        'title',

        'description',

        'difficulty_level',

        'cover_image',

        'audio_path',

        'story_type',

        'grade_level',
    ];
    protected $casts = [
    'audio_scripts' => 'array'
];

    public function pages()
    {
        return $this->hasMany(StoryPage::class);
    }
    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }
    public function schoolClasses()
{
    return $this->belongsToMany(SchoolClass::class, 'class_story', 'story_id', 'school_class_id');
}
}
