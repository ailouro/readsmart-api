<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Mispronunciation;
use App\Models\SelfCorrection;
use Illuminate\Support\Facades\Storage;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;

class MiscueController extends Controller
{
    /**
     * Store student mispronunciations / omissions sent from Flutter.
     */
    public function storeMispronunciation(Request $request)
    {
        $request->validate([
            'student_id' => 'required',
            'story_id'   => 'required',
            'words_json' => 'required',
        ]);

        $studentId = $request->input('student_id');
        $storyId   = $request->input('story_id');
        $wordsData = json_decode($request->input('words_json'), true) ?? [];
        $audioFiles = $request->file('audio_files') ?? [];

        $audioFileIndex = 0;

        foreach ($wordsData as $index => $item) {
            $word        = $item['word'] ?? '';
            $attempts    = $item['total_attempts'] ?? 3;
            $miscueType  = $item['miscue_type'] ?? 'mispronunciation';
            $slideIndex  = $item['slide_index'] ?? 0;
            $audioUrl    = null;

            // Kung may pumasok na WAV audio recording para sa partikular na struggling word
            if ($miscueType === 'mispronunciation' && isset($audioFiles[$audioFileIndex])) {
                $file = $audioFiles[$audioFileIndex];
                
                try {
                    // Option A: Cloudinary Upload (kung gumagamit ng Cloudinary package)
                    if (class_exists(Cloudinary::class) && config('cloudinary.cloud_url')) {
                        $uploaded = Cloudinary::upload($file->getRealPath(), [
                            'folder'        => 'readsmart/struggle_words',
                            'resource_type' => 'video', // WAV audio uses 'video' resource type in Cloudinary
                        ]);
                        $audioUrl = $uploaded->getSecurePath();
                    } else {
                        // Option B: Local Laravel Storage Fallback
                        $path = $file->store("public/struggle_words/{$studentId}");
                        $audioUrl = asset(Storage::url($path));
                    }
                } catch (\Exception $e) {
                    \Log::error("Audio upload failed for word '{$word}': " . $e->getMessage());
                }

                $audioFileIndex++;
            }

            Mispronunciation::create([
                'student_id'     => $studentId,
                'story_id'       => $storyId,
                'slide_index'    => $slideIndex,
                'word'           => $word,
                'miscue_type'    => $miscueType,
                'total_attempts' => $attempts,
                'audio_url'      => $audioUrl,
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Mispronunciations saved successfully.',
        ], 201);
    }

    /**
     * Store student self-corrections (Phil-IRI Rule: Does NOT penalize WR score).
     */
    public function storeSelfCorrection(Request $request)
    {
        $request->validate([
            'student_id'     => 'required',
            'story_id'       => 'required',
            'word'           => 'required',
            'total_attempts' => 'required',
        ]);

        $record = SelfCorrection::create([
            'student_id'     => $request->input('student_id'),
            'story_id'       => $request->input('story_id'),
            'slide_index'    => $request->input('slide_index', 0),
            'word'           => strtolower(trim($request->input('word'))),
            'total_attempts' => $request->input('total_attempts'),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Self-correction logged successfully.',
            'data'    => $record,
        ], 201);
    }

    /**
     * Get mispronunciations log for a teacher's students.
     */
    public function getTeacherMispronunciations($teacherId)
    {
        $logs = Mispronunciation::with(['story:id,title', 'student:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $logs,
        ], 200);
    }

    /**
     * Get self-corrections log for a teacher's students.
     */
    public function getTeacherSelfCorrections($teacherId)
    {
        $logs = SelfCorrection::with(['story:id,title', 'student:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $logs,
        ], 200);
    }
}