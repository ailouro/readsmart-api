<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Story; // Naka-import na para malinis tingnan

class StoryAudioController extends Controller
{
    /**
     * Hakbang A: Kukunin ang text, gagawing MP3 gamit ang Google, at ise-save sa server storage.
     */
    public function generateTts(Request $request, $storyId, $slideIndex)
    {
        // 1. I-validate ang mga datos na galing sa Flutter
        $request->validate([
            'text' => 'required|string',
            'script_index' => 'required|integer',
            'lang' => 'nullable|string'
        ]);

        $text = $request->input('text');
        $scriptIndex = $request->input('script_index');
        $lang = $request->input('lang', 'en'); // Default ay English ('en'), pwede ring 'tl' para sa Tagalog
        
        $googleService = new GoogleService();

        $translated = $googleService->translate($storyId, $slideIndex, $scriptIndex, $text, $lang);

        // try {
        //     // 2. Libreng Google TTS URL (Walang API key na kailangan)
        //     $ttsUrl = "https://translate.google.com/translate_tts?ie=UTF-8&tl=" . $lang . "&client=tw-ob&q=" . urlencode($text);

        //     // 3. Tawagin ang Google gamit ang Laravel Http Client para makuha ang audio bytes
        //     $response = Http::withHeaders([
        //         'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)'
        //     ])->get($ttsUrl);

        //     if ($response->failed()) {
        //         return response()->json(['error' => 'Failed to fetch audio from voice engine'], 500);
        //     }

        //     // 4. Gumawa ng kakaibang filename para sa slide part na ito
        //     $filename = "story_{$storyId}_slide_{$slideIndex}_script_{$scriptIndex}.mp3";

        //     // 5. I-save ang MP3 file sa 'storage/app/public/audio' folder ng Laravel
        //     Storage::disk('public')->put("audio/" . $filename, $response->body());

            return response()->json([
                'message' => 'AI Voice generated and saved successfully!',
                'filename' => $googleService['filename'],
            ], 200);

        // } catch (\Exception $e) {
        //     return response()->json(['error' => 'Server Error: ' . $e->getMessage()], 500);
        // }
    }

    /**
     * Hakbang B: Babasahin ang na-save na MP3 at ibabato pabalik sa AudioPlayer ng Flutter.
     */
    public function getAudio(Request $request)
    {
        $storyId = $request->query('story_id');
        $pageIndex = $request->query('page_index');
        $scriptIndex = $request->query('script_index');

        // Hanapin ang file base sa pangalan na ginawa natin sa taas
        $filename = "story_{$storyId}_slide_{$pageIndex}_script_{$scriptIndex}.mp3";
        $path = "audio/" . $filename;

        // Suriin kung umiiral nga ba ang file sa storage
        if (!Storage::disk('public')->exists($path)) {
            return response()->json(['error' => 'Audio file not found. Please generate it first.'], 404);
        }

        // Kunin ang file galing storage
        $file = Storage::disk('public')->get($path);
        
        // I-return ang file bilang isang Audio Stream na kayang basahin ng Flutter Audio Player agad
        return response($file, 200)
            ->header('Content-Type', 'audio/mpeg')
            ->header('Content-Length', strlen($file))
            ->header('Accept-Ranges', 'bytes');
    }

    /**
     * Hakbang C: I-save o i-update ang text/script ng isang partikular na page/slide sa DB.
     */
    public function updateSlideScript(Request $request, $storyId, $slideIndex)
    {
        // 1. I-validate ang text na galing sa Flutter Editor
        $request->validate([
            'audio_script' => 'required|string'
        ]);

        $newScript = $request->input('audio_script');

        // 2. Hanapin ang kuwento (Story) gamit ang ID nito
        $story = Story::find($storyId);
        if (!$story) {
            return response()->json(['error' => 'Story not found'], 404);
        }

        // 3. Gamit ang Relationship, hanapin ang tamang page base sa pagkakasunod-sunod ($slideIndex)
        // Ang skip(0) ay kukuha ng 1st page, skip(1) ay 2nd page, atbp.
        $page = $story->pages()->orderBy('id', 'asc')->skip($slideIndex)->first();

        if (!$page) {
            return response()->json(['error' => 'Slide page not found at index ' . $slideIndex], 404);
        }

        // 4. I-update ang 'audio_scripts' column sa `story_pages` table
        // Naka-array ito ([ $newScript ]) para magtugma sa inaasahang List/Array format ng Flutter mo
        $page->audio_scripts = [$newScript];
        $page->save();

        return response()->json([
            'message' => 'Slide text updated successfully in DB!',
            'page_id' => $page->id
        ], 200);
    }
}