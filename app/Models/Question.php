<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_id',
        'question_text',
        'options',        // Mga pagpipilian (e.g., ["A. Dog", "B. Cat"])
        'correct_answer', // Ang tamang sagot
    ];

    // I-cast ang options bilang array para auto-convert mula sa JSON sa database
    protected $casts = [
        'options' => 'array',
    ];

    // Relasyon pabalik sa Quiz
    public function quiz()
    {
        return $this->belongsTo(Quiz::class);
    }
}