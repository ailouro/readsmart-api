<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mispronunciation;
use App\Models\SelfCorrection;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class MiscueController extends Controller
{
    // 1. Save Mispronunciations (Kasama ang audio clip kung mayroon)
    public function storeMispronunciation(Request $request)
    {
        $request->validate([
            'student_id' => 'required',
            'story_id' => 'required',
            'words_json' => 'required',
        ]);

        $words = json_decode($request->words_json, true);
        $audioFiles = $request->file('audio_files') ?? [];

        foreach ($words as $index => $item) {
            $audioUrl = null;

            // Upload audio sa Cloudinary kung pumasok ang .wav file mula sa Deepgram
            if (isset($audioFiles[$index])) {
                $uploadedFile = Cloudinary::upload($audioFiles[$index]->getRealPath(), [
                    'folder' => 'readsmart/struggle_words',
                    'resource_type' => 'video' // .wav files use video resource type in Cloudinary
                ]);
                $audioUrl = $uploadedFile->getSecurePath();
            }

            Mispronunciation::create([
                'student_id'     => $request->student_id,
                'story_id'       => $request->story_id,
                'slide_index'    => $item['slide_index'] ?? 0,
                'word'           => $item['word'],
                'total_attempts' => $item['total_attempts'] ?? 3,
                'audio_url'      => $audioUrl,
            ]);
        }

        return response()->json(['message' => 'Mispronunciations saved successfully'], 201);
    }

    // 2. Save Self-Corrections (Naisawasto ng bata — HINDI bawas sa score)
    public function storeSelfCorrection(Request $request)
    {
        $request->validate([
            'student_id'     => 'required',
            'story_id'       => 'required',
            'word'           => 'required',
            'total_attempts' => 'required',
        ]);

        $selfCorrection = SelfCorrection::create([
            'student_id'     => $request->student_id,
            'story_id'       => $request->story_id,
            'slide_index'    => $request->slide_index ?? 0,
            'word'           => $request->word,
            'total_attempts' => $request->total_attempts,
        ]);

        return response()->json([
            'message' => 'Self-correction logged successfully',
            'data'    => $selfCorrection
        ], 201);
    }
}
