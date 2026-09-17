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
        'audio_url', // legacy/single-script column — kept for backward compat
        'audio_urls', // ⬅️ NEW: one generated audio URL per script_index, e.g. {"0": "https://...", "1": "https://..."}
    ];

    // 2. I-cast ang audio_scripts/audio_urls bilang array para hindi mag-error kapag ini-save
    protected $casts = [
        'audio_scripts' => 'array',
        'audio_urls' => 'array',
    ];

    /**
     * Get the generated audio URL for a specific script index, falling back
     * to the legacy single audio_url column for index 0 (old pages generated
     * before audio_urls existed only ever had one script anyway).
     */
    public function audioUrlFor(int $scriptIndex): ?string
    {
        $urls = $this->audio_urls ?? [];
        if (isset($urls[$scriptIndex])) {
            return $urls[$scriptIndex];
        }
        if ($scriptIndex === 0 && $this->audio_url) {
            return $this->audio_url;
        }
        return null;
    }

    // Relasyon sa Story (existing code mo)
    public function story()
    {
        return $this->belongsTo(Story::class);
    }
}