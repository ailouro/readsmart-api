<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class GoogleService

{
    public function translate($storyId, $slideIndex, $scriptIndex, $text, $lang = 'en')
    {
        try {
            // 2. Libreng Google TTS URL (Walang API key na kailangan)
            $ttsUrl = "https://translate.google.com/translate_tts?ie=UTF-8&tl=" . $lang . "&client=tw-ob&q=" . urlencode($text);

            // 3. Tawagin ang Google gamit ang Laravel Http Client para makuha ang audio bytes
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
            ])->get($ttsUrl);

            if ($response->failed()) {
                return response()->json(['error' => 'Failed to fetch audio from voice engine'], 500);
            }

            // 4. Gumawa ng kakaibang filename para sa slide part na ito
            $filename = "story_{$storyId}_slide_{$slideIndex}_script_{$scriptIndex}.mp3";

            // 5. I-save ang MP3 file sa 'storage/app/public/audio' folder ng Laravel
            Storage::disk('public')->put("audio/" . $filename, $response->body());

            // return response()->json([
            //     'message' => 'AI Voice generated and saved successfully!',
            //     'filename' => $filename
            // ], 200);

            return [
                'filename' => $filename,
                'path' => Storage::disk('public')->url("audio/" . $filename)    
            ];

        } catch (\Exception $e) {
            return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        }
        // Implement translation logic here
    }
}