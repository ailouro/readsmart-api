<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Story;
use App\Models\StoryPage;
use App\Models\Quiz;
use App\Models\StudentProgress;
use App\Models\SelfCorrection;

class StoryController extends Controller
{
    /**
     * 🟢 Kunin lahat ng kwento kasama ang pages/slides
     */
    public function index()
    {
        $stories = Story::with(['pages', 'quiz'])->get(); 
        return response()->json($stories);
    }

    /**
     * 🟢 Kunin ang isang tiyak na kwento gamit ang ID
     */
    public function show($id)
    {
        $story = Story::with(['pages', 'quiz'])->find($id);

        if (!$story) {
            return response()->json(['message' => 'Story not found'], 404);
        }

        return response()->json($story);
    }
    
    /**
     * 🟢 Pag-upload ng Story at Images (Teacher / Admin Dashboard)
     *
     * NOTE: The Flutter app uploads images to Cloudinary itself and sends
     * back plain secure_url strings for cover_image / pages — it does NOT
     * send raw files anymore. This method must use those URLs directly,
     * not re-upload via cloudinary()->upload() (that only works on actual
     * uploaded files, which will never be present here).
     */
        public function store(Request $request)
{
    $request->validate([
        'title'         => 'required|string',
        'cover_image'   => 'required|url',
        'pages'         => 'required',
        'audio_scripts' => 'nullable',
    ]);

    try {
        $coverPath = $request->input('cover_image');

        $story = Story::create([
            'title'       => $request->title,
            'description' => $request->description ?? 'Walang description',
            'cover_image' => $coverPath,
            'level'       => $request->level ?? 'frustration',
        ]);

        $audioScripts = $request->input('audio_scripts', []);
        if (is_string($audioScripts)) {
            $decodedAudio = json_decode($audioScripts, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $audioScripts = $decodedAudio;
            }
        }

        $pageUrlsRaw = $request->input('pages', []);
        if (is_string($pageUrlsRaw)) {
            $decodedPages = json_decode($pageUrlsRaw, true);
            $pageUrls = (json_last_error() === JSON_ERROR_NONE && is_array($decodedPages))
                ? $decodedPages
                : [];
        } else {
            $pageUrls = is_array($pageUrlsRaw) ? $pageUrlsRaw : [];
        }

        foreach ($pageUrls as $index => $pageUrl) {
            $pageScripts = null;
            if (isset($audioScripts[$index])) {
                $scriptValue = $audioScripts[$index];

                if (is_string($scriptValue)) {
                    $decoded = json_decode($scriptValue, true);
                    $pageScripts = (json_last_error() === JSON_ERROR_NONE) ? $decoded : $scriptValue;
                } else {
                    $pageScripts = $scriptValue;
                }
            }

            StoryPage::create([
                'story_id'      => $story->id,
                'image_path'    => $pageUrl,
                'page_number'   => $index + 1,
                'audio_scripts' => $pageScripts,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Story uploaded successfully with audio scripts',
            'story'   => $story->load('pages') 
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage(),
            'line'    => $e->getLine(),
        ], 500);
    }
}

    /**
     * 🛠️ INAYOS: I-update ang Audio Script ng isang tiyak na Slide Page
     */
    public function updatePageAudio(Request $request, $id)
    {
        $page = StoryPage::find($id);

        if (!$page) {
            return response()->json(['message' => 'Page not found'], 404);
        }

        $request->validate([
            'audio_scripts' => 'required',
        ]);

        $input = $request->input('audio_scripts');

        // Kung string ang input, linisin at i-format nang maayos
        if (is_string($input)) {
            $lines = explode("\n", $input);
            $cleanLines = array_values(array_filter(array_map('trim', $lines)));

            // Kung isang linya lang, i-save as string; kung marami, i-array
            $finalScript = (count($cleanLines) === 1) ? $cleanLines[0] : $cleanLines;
        } else {
            $finalScript = $input;
        }

        $page->audio_scripts = $finalScript;
        $page->save();

        return response()->json([
            'success'         => true,
            'message'         => 'Audio scripts updated successfully!',
            'updated_scripts' => $finalScript,
            'page'            => $page
        ], 200);
    }

    /**
     * 🔵 Kunin ang kwento base sa Phil-IRI Reading Level
     */
    public function getStoriesByLevel($level)
    {
        $stories = Story::with('pages')->where('level', strtolower($level))->get();

        return response()->json([
            'success' => true,
            'stories' => $stories
        ], 200);
    }

    /**
     * 🔵 Kunin ang Quiz sa dulo ng kwento
     */
    public function getQuizByStory($storyId)
    {
        $quiz = Quiz::where('story_id', $storyId)->with('questions')->first();

        if (!$quiz) {
            return response()->json([
                'success' => false,
                'message' => 'No quiz found for this story.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'quiz'    => $quiz
        ], 200);
    }

    /**
     * Magdagdag ng Quiz at mga Tanong (Questions) sa isang Kwento
     */
    public function addQuizToStory(Request $request, $storyId)
    {
        $story = Story::find($storyId);
        if (!$story) {
            return response()->json(['success' => false, 'message' => 'Story not found'], 404);
        }

        $validated = $request->validate([
            'title' => 'nullable|string',
            'questions' => 'required|array',
            'questions.*.question_text' => 'required|string',
            'questions.*.options' => 'required|array',
            'questions.*.correct_answer' => 'required|string',
        ]);

        try {
            // Check kung may quiz na, kung mayroon update, kung wala create
            $quiz = Quiz::updateOrCreate(
                ['story_id' => $storyId],
                ['title' => $validated['title'] ?? 'Story Quiz']
            );

            // Burahin ang lumang questions bago ilagay ang bago
            $quiz->questions()->delete();

            foreach ($validated['questions'] as $q) {
                $quiz->questions()->create([
                    'question_text' => $q['question_text'],
                    'options' => $q['options'], // automatically casted to array in model
                    'correct_answer' => $q['correct_answer'],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Quiz added successfully!',
                'quiz' => $quiz->load('questions'),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add quiz: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 🔵 I-save ang Score at Progress
     */
    public function saveProgress(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'story_id' => 'required|exists:stories,id',
            'quiz_score' => 'required|numeric',
            'total_questions' => 'required|numeric',
            'oral_fluency_accuracy' => 'required|numeric', // e.g. 85.5 (%)
            'time_on_task' => 'required|integer',
            'total_words' => 'nullable|integer',
            'test_type' => 'nullable|in:pre_test,post_test',
            // 🛠️ FIX: story_view_screen.dart has been sending this array all
            // along (see the /api/student/progress call), but it was never
            // in this validate() list, so it was silently dropped on every
            // request and never reached the SelfCorrection table — the
            // dedicated /api/student-self-corrections endpoint isn't called
            // by the app anymore. Same flow, just actually wired up now.
            'self_corrected_words' => 'nullable|array',
            'self_corrected_words.*.word' => 'required_with:self_corrected_words|string',
            'self_corrected_words.*.total_attempts' => 'nullable|integer',
        ]);

        // Calculate Quiz Score Percentage (Comprehension)
        $comprehensionPct = ($validated['total_questions'] > 0)
            ? ($validated['quiz_score'] / $validated['total_questions']) * 100
            : 0;

        $oralAccuracy = $validated['oral_fluency_accuracy'];

        // Calculate Reading Speed (WPM)
        $timeInSeconds = $validated['time_on_task'] > 0 ? $validated['time_on_task'] : 1;
        $totalWords = $validated['total_words'] ?? 0;
        $wpm = ($totalWords / $timeInSeconds) * 60;

        // --- Phil-IRI Classification Standard ---
        
        // 1. Determine Word Recognition (WR) Level
        $wrLevel = 'Frustration';
        if ($oralAccuracy >= 97) {
            $wrLevel = 'Independent';
        } elseif ($oralAccuracy >= 90) {
            $wrLevel = 'Instructional';
        }

        // 2. Determine Comprehension (C) Level
        $cLevel = 'Frustration';
        if ($comprehensionPct >= 80) {
            $cLevel = 'Independent';
        } elseif ($comprehensionPct >= 59) {
            $cLevel = 'Instructional';
        }

        // 3. Determine Overall Reading Level
        $readingLevel = 'Frustration';
        
        if ($wrLevel === 'Independent') {
            if ($cLevel === 'Independent') $readingLevel = 'Independent';
            elseif ($cLevel === 'Instructional') $readingLevel = 'Instructional';
            else $readingLevel = 'Frustration';
        } elseif ($wrLevel === 'Instructional') {
            if ($cLevel === 'Independent') $readingLevel = 'Independent';
            elseif ($cLevel === 'Instructional') $readingLevel = 'Instructional';
            else $readingLevel = 'Frustration';
        } elseif ($wrLevel === 'Frustration') {
            $readingLevel = 'Frustration'; // Frustration WR always results in Frustration
        }
        $progress = \App\Models\StudentProgress::where('user_id', $validated['user_id'])
            ->where('story_id', $validated['story_id'])
            ->orderBy('id', 'desc')
            ->first();

        if ($progress) {
            $progress->update([
                'test_type' => $validated['test_type'] ?? 'post_test',
                'quiz_score' => $validated['quiz_score'],
                'total_questions' => $validated['total_questions'],
                'oral_fluency_accuracy' => $oralAccuracy,
                'time_on_task' => $validated['time_on_task'],
                'reading_level' => $readingLevel,
                'is_reading_completed' => true,
            ]);
        } else {
            $progress = \App\Models\StudentProgress::create([
                'user_id' => $validated['user_id'],
                'story_id' => $validated['story_id'],
                'test_type' => $validated['test_type'] ?? 'post_test',
                'quiz_score' => $validated['quiz_score'],
                'total_questions' => $validated['total_questions'],
                'oral_fluency_accuracy' => $oralAccuracy,
                'time_on_task' => $validated['time_on_task'],
                'reading_level' => $readingLevel,
                'is_reading_completed' => true,
            ]);
        }

        // Same fix as above, applied on both branches: persist any
        // self-corrected words that came with this progress submission.
        foreach ($validated['self_corrected_words'] ?? [] as $w) {
            SelfCorrection::create([
                'student_id' => $validated['user_id'],
                'story_id' => $validated['story_id'],
                'word' => strtolower($w['word']),
                'total_attempts' => $w['total_attempts'] ?? 2,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Progress saved successfully',
            'progress' => $progress
        ], 201);
    }

    public function generateMultiScriptAudio(Request $request, $pageId)
{
    $request->validate([
        'scripts' => 'required|array|min:1',
        'scripts.*' => 'required|string',
    ]);

    $page = StoryPage::findOrFail($pageId);
    $generatedUrls = [];

    foreach ($request->input('scripts') as $index => $scriptText) {
        // Step A: Convert text string to TTS audio binary stream
        $audioBinary = $this->googleService->generateAudio($scriptText);

        // Step B: Upload stream directly to Cloudinary folder
        $fileName = "story_{$page->story_id}_p{$page->id}_script_{$index}";
        $uploadResult = Cloudinary::uploadApi()->upload(
            "data:audio/mp3;base64," . base64_encode($audioBinary),
            [
                'folder' => 'story_audio',
                'public_id' => $fileName,
                'resource_type' => 'video',
            ]
        );

        // Step C: Collect secure public URL
        $generatedUrls[] = $uploadResult['secure_url'];
    }



    $page->scripts = $request->input('scripts'); 
    $page->audio_urls = $generatedUrls;         
    $page->save();

    return response()->json([
        'status' => 'success',
        'audio_urls' => $page->audio_urls,
    ], 200);
}
    
 
    /**
     * 🛠️ BAGO: I-update ang Story Type (pre_test/post_test) at/o Grade Level
     * ng isang kwento. Dating tinatawag ng Flutter app ang isang PUT route
     * na hindi naka-register (PUT /stories/{id}), kaya nag-405. Ito ang
     * tamang endpoint, kasunod ng convention ng update-script route.
     */
    public function updateMeta(Request $request, $id)
    {
        $story = Story::find($id);

        if (!$story) {
            return response()->json(['message' => 'Story not found'], 404);
        }

        $validated = $request->validate([
            'story_type'  => 'nullable|string|in:pre_test,post_test',
            'grade_level' => 'nullable|string|in:Grade 2,Grade 3,Grade 4,Grade 5,Grade 6,Grade 7',
            'set_letter'  => 'nullable|string|in:Set A,Set B,Set C,Set D',
        ]);

        if (array_key_exists('story_type', $validated) && $validated['story_type'] !== null) {
            $story->story_type = $validated['story_type'];
        }
        if (array_key_exists('grade_level', $validated) && $validated['grade_level'] !== null) {
            $story->grade_level = $validated['grade_level'];
        }
        if (array_key_exists('set_letter', $validated) && $validated['set_letter'] !== null) {
            $story->set_letter = $validated['set_letter'];
        }

        $story->save();

        return response()->json([
            'success' => true,
            'message' => 'Story info updated successfully!',
            'story'   => $story,
        ], 200);
    }

    public function destroy($id)
    {
        $story = Story::with('pages')->find($id);

        if (!$story) {
            return response()->json(['message' => 'Story not found'], 404);
        }

        try {   
            // Tanggalin ang cover image sa storage
            if ($story->cover_image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($story->cover_image);
            }

            // Tanggalin ang mga page images sa storage
            foreach ($story->pages()->get() as $page) {
    $page->delete();
}

            // Tanggalin ang mismong record sa database
            $story->delete();

            return response()->json([
                'success' => true,
                'message' => 'Story and associated files deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Delete Story Error: ' . $e->getMessage() . ' Trace: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Failed to delete story: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getProgress($studentId, $storyId)
    {
        $progress = \App\Models\StudentProgress::where('user_id', $studentId)
            ->where('story_id', $storyId)
            ->orderBy('id', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'progress' => $progress
        ]);
    }

    public function getAllProgress($studentId)
    {
        $records = \App\Models\StudentProgress::where('user_id', $studentId)
            ->where('is_reading_completed', true)
            ->with('story:id,title')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($p) {
                // A completed progress row whose story_id doesn't match any
                // row in `stories` (story deleted/re-uploaded, or a wrong id
                // was saved) used to show up as a nameless "Unknown" entry.
                // Log it so the bad story_id is easy to find, and fall back to
                // a readable label instead of "Unknown".
                if (!$p->story) {
                    \Illuminate\Support\Facades\Log::warning(
                        "student_progress #{$p->id} (user {$p->user_id}) points to missing story_id {$p->story_id}"
                    );
                }

                return [
                    'story_id'              => $p->story_id,
                    'story_title'           => $p->story->title ?? $this->fallbackStoryTitle($p),
                    'test_type'             => $p->test_type,
                    'reading_level'         => $p->reading_level ?? 'N/A',
                    'oral_fluency_accuracy' => $p->oral_fluency_accuracy ?? 0,
                    'quiz_score'            => $p->quiz_score ?? 0,
                    'total_questions'       => $p->total_questions ?? 0,
                    'time_on_task'          => $p->time_on_task ?? 0,
                    'completed_at'          => $p->updated_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data'    => $records,
        ]);
    }

    private function fallbackStoryTitle($progress): string
    {
        $labels = [
            'pre_test'  => 'Pre-Test Reading',
            'post_test' => 'Post-Test Reading',
        ];

        return $labels[$progress->test_type] ?? ('Story #' . $progress->story_id);
    }

    public function saveReadingProgress(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'story_id' => 'required|exists:stories,id',
            'current_slide' => 'required|integer',
        ]);

        $progress = \App\Models\StudentProgress::where('user_id', $validated['user_id'])
            ->where('story_id', $validated['story_id'])
            ->where('is_reading_completed', false)
            ->orderBy('id', 'desc')
            ->first();

        if ($progress) {
            $progress->update([
                'current_slide' => $validated['current_slide']
            ]);
        } else {
            $progress = \App\Models\StudentProgress::create([
                'user_id' => $validated['user_id'],
                'story_id' => $validated['story_id'],
                'current_slide' => $validated['current_slide'],
                'is_reading_completed' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'progress' => $progress
        ]);
    }

    /**
     * 📚 "Aking Silid-Aklatan" (My Library) -- every story this student has
     * finished at least once, with full story data (pages + quiz included) so
     * the app can hand it straight to StoryViewerScreen for a re-read. A story
     * finished more than once is returned once, using its latest attempt.
     *
     * The old version used a raw join + select('stories.*'), which never
     * loaded `pages`, so re-reading opened an empty story.
     */
    public function getCompletedStories($studentId)
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Completed stories retrieved successfully.',
                'data'    => StudentProgress::completedStoriesFor($studentId),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch completed stories.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}