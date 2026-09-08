<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoryPage extends Model
{
    use HasFactory;

    // 1. Siguraduhing kasama ang 'audio_scripts' sa fillable array
    protected $fillable = [
        'story_id',
        'image_path',
        'page_number',
        'audio_scripts', 
    ];

    // 2. I-cast ang audio_scripts bilang array para hindi ito mag-error kapag ini-save sa database
    protected $casts = [
        'audio_scripts' => 'array',
    ];

    // Relasyon sa Story (existing code mo)
    public function story()
    {
        return $this->belongsTo(Story::class);
    }
}