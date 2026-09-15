<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoogleService
{
    public function translate($storyId, $slideIndex, $scriptIndex, $text, $lang = 'en')
    {
        try {
            // 1. Libreng Google TTS URL (Walang API key na kailangan)
            $ttsUrl = "https://translate.google.com/translate_tts?ie=UTF-8&tl=" . $lang . "&client=tw-ob&q=" . urlencode($text);

            // 2. Tawagin ang Google gamit ang Laravel Http Client para makuha ang audio bytes
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
            ])->get($ttsUrl);

            if ($response->failed()) {
                return ['error' => 'Failed to fetch audio from voice engine'];
            }

            // 3. Gumawa ng kakaibang filename para sa slide part na ito
            $filename = "story_{$storyId}_slide_{$slideIndex}_script_{$scriptIndex}";

            // 4. I-save muna nang temporary sa local tmp (kailangan ito ng Cloudinary SDK
            //    bilang input path), tapos i-upload sa Cloudinary, tapos burahin agad
            //    ang local temp file. Wala nang natitirang audio sa Railway disk.
            $tmpPath = tempnam(sys_get_temp_dir(), 'tts_') . '.mp3';
            file_put_contents($tmpPath, $response->body());

            $upload = cloudinary()->upload($tmpPath, [
                'folder' => 'story_audio',
                'public_id' => $filename,
                'resource_type' => 'video', // Cloudinary treats audio files as 'video' resource type
            ]);

            @unlink($tmpPath); // linisin ang temp file

            return [
                'filename' => $filename . '.mp3',
                'path' => $upload->getSecurePath(),
            ];

        } catch (\Exception $e) {
            return ['error' => 'Server Error: ' . $e->getMessage()];
        }
    }
}