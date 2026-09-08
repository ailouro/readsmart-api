<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model
{
    use HasFactory;

    protected $fillable = [
        'story_id',
        'title',
    ];

    // Relasyon sa Story
    public function story()
    {
        return $this->belongsTo(Story::class);
    }

    // Relasyon sa mga Tanong (Questions) - Ito ang hinahanap ng ->with('questions') mo!
    public function questions()
    {
        return $this->hasMany(Question::class);
    }
}