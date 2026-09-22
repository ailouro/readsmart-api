<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentProgress;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Mispronunciation;

class ClassEnrollmentController extends Controller
{
    // ➕ 1. TEACHER: CREATE NEW CLASS WITH CODE
    public function createClass(Request $request)
    {
        $request->validate([
            'teacher_id'  => 'required|exists:users,id',
            'name'        => 'required|string|max:255',
            'grade_level' => 'required|in:Grade 5,Grade 6',
        ]);

        // Generate unique code (e.g. G5-READ-9102)
        $prefix = $request->grade_level === 'Grade 5' ? 'G5' : 'G6';
        do {
            $classCode = $prefix . '-READ-' . rand(1000, 9999);
        } while (SchoolClass::where('class_code', $classCode)->exists());

        $schoolClass = SchoolClass::create([
            'teacher_id'  => $request->teacher_id,
            'name'        => $request->name,
            'grade_level' => $request->grade_level,
            'class_code'  => $classCode,
        ]);

        return response()->json([
            'message'    => 'Class created successfully!',
            'class_data' => $schoolClass,
        ], 201);
    }

    // 🔗 2. PARENT: ENROLL CHILD VIA CLASS CODE
    public function enrollParent(Request $request)
    {
        $request->validate([
            'parent_id'    => 'required|exists:users,id',
            'class_code'   => 'required|string',
            'student_name' => 'required|string|max:255',
        ]);

        // Hanapin ang Class batay sa Class Code
        $schoolClass = SchoolClass::where('class_code', $request->class_code)->first();

        if (!$schoolClass) {
            return response()->json([
                'message' => 'Invalid Class Code. Please check with the teacher.',
            ], 404);
        }

        // Lumikha o i-update ang Student Record
        $student = Student::firstOrCreate(
            [
                'parent_id'   => $request->parent_id,
                'name'        => $request->student_name,
            ],
            [
                'grade_level' => $schoolClass->grade_level,
            ]
        );

        // I-attach ang student sa class sa pivot table
        $schoolClass->students()->syncWithoutDetaching([$student->id]);

        return response()->json([
            'message'      => 'Student successfully enrolled in class!',
            'student_name' => $student->name,
            'class_name'   => $schoolClass->name,
            'grade_level'  => $schoolClass->grade_level,
            'class_code'   => $schoolClass->class_code,
        ], 200);
    }

    public function logMispronunciation(Request $request)
{
    // 🛠️ FIX: The Flutter app (story_view_screen.dart) sends word data as
    // a JSON-encoded string field called 'words_json' (via a
    // MultipartRequest), NOT as a real 'words' array field — 'words[]' is
    // actually sent as file parts, which $request->validate() cannot see.
    // Validating 'words' as required|array always failed silently (422),
    // so no Mispronunciation rows were ever created. Read 'words_json'
    // instead, matching what the app actually transmits.
    $validated = $request->validate([
        'student_id' => 'required|integer',
        'story_id' => 'required|exists:stories,id',
        'words_json' => 'required|string',
    ]);

    $words = json_decode($validated['words_json'], true);

    if (!is_array($words) || empty($words)) {
        return response()->json([
            'message' => 'words_json must be a non-empty JSON array of {word, total_attempts} objects.',
        ], 422);
    }

    // 🎤 FIX: dating dumarating ang 'audio_files[]' pero hindi kailanman
    // binabasa ng controller — natatanggap lang, tapos itinatapon. Ngayon
    // ginagamit na, at ini-upload sa Cloudinary kaparehong pattern ng TTS.
    //
    // Hindi natin puwedeng ipares ang audio_files[] sa words_json base sa
    // array index — sa Flutter side, isang audio file lang isinasama
    // PER WORD NA MAY AUDIO ("if (failedWords[i].audioPath != null)"), kaya
    // maiiba ang bilang nila kung may failed word na walang na-record na
    // audio. Sa kabutihang-palad, naka-encode ang mismong salita sa
    // filename ("struggle_<word>.wav"), kaya ipares natin dun.
    $audioByWord = [];
    foreach ($request->file('audio_files', []) as $file) {
        if (!$file) {
            continue;
        }
        $original = $file->getClientOriginalName(); // e.g. struggle_frog.wav
        if (preg_match('/^struggle_(.+)\.wav$/i', $original, $m)) {
            $audioByWord[strtolower($m[1])] = $file;
        }
    }

    foreach ($words as $wordData) {
        $word = is_array($wordData) ? ($wordData['word'] ?? null) : $wordData;
        if (!$word) {
            continue;
        }
        $cleanWord = strtolower($word);

        $audioUrl = null;
        if (isset($audioByWord[$cleanWord])) {
            try {
                $upload = cloudinary()->upload($audioByWord[$cleanWord]->getRealPath(), [
                    'folder' => 'mispronunciations',
                    'resource_type' => 'video', // Cloudinary treats audio as 'video' resource type
                ]);
                $audioUrl = $upload->getSecurePath();
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Mispronunciation audio upload failed: ' . $e->getMessage());
            }
            // Alisin agad para hindi na maulit kung magkataong paulit-ulit
            // ang salita sa listahan.
            unset($audioByWord[$cleanWord]);
        }

        Mispronunciation::create([
            'student_id' => $validated['student_id'],
            'story_id' => $validated['story_id'],
            'word' => $cleanWord,
            'total_attempts' => is_array($wordData) ? ($wordData['total_attempts'] ?? 1) : 1,
            'audio_url' => $audioUrl,
        ]);
    }

    return response()->json([
        'message' => 'Mispronunciations recorded successfully'
    ], 201);
}

    public function getStudentMispronunciations($student_id)
{
    $logs = Mispronunciation::where('student_id', $student_id)
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json([
        'status' => 'success',
        'data'   => $logs
    ], 200);
}

// 📊 GET ALL MISPRONUNCIATION LOGS FOR TEACHER DASHBOARD
    public function getTeacherStudentLogs($teacher_id)
    {
        // audio_url is pulled via a correlated subquery (not a plain
        // selected column) because this query GROUPs BY word to collapse
        // multiple attempts into one row — a plain column pick would be
        // ambiguous/DB-dependent under GROUP BY. This grabs the most
        // recent recording for that (student, story, word) combo instead.
        $logs = Mispronunciation::whereHas('student.classes', function ($query) use ($teacher_id) {
            $query->where('teacher_id', $teacher_id);
        })
        ->selectRaw('student_id, story_id, word, MAX(created_at) as created_at, COUNT(*) as total_attempts,
            (SELECT m2.audio_url FROM mispronunciations m2
             WHERE m2.student_id = mispronunciations.student_id
               AND m2.story_id = mispronunciations.story_id
               AND m2.word = mispronunciations.word
               AND m2.audio_url IS NOT NULL
             ORDER BY m2.created_at DESC LIMIT 1) as audio_url')
        ->groupBy('student_id', 'story_id', 'word')
        ->with(['student', 'story'])
        ->orderByRaw('MAX(created_at) DESC')
        ->get();
        
        return response()->json([
            'data' => $logs
        ], 200);
    }

public function getTeacherDashboardSummary($teacher_id)
{
    // 1. Fetch classes using the PROVEN Eloquent relationship
    // and eager-load the students' progress and associated stories.
    $classes = \App\Models\SchoolClass::where('teacher_id', $teacher_id)
        ->with(['students' => function($query) {
            $query->with(['progress' => function($q) {
                $q->orderBy('created_at', 'desc');
            }, 'progress.story']);
        }])
        ->get();

    // 2. Extract unique students (prevents duplicates if a student is in multiple classes)
    $students = collect();
    foreach ($classes as $class) {
        foreach ($class->students as $student) {
            if (!$students->contains('id', $student->id)) {
                // Tag the class this student was found under so the
                // Flutter dashboard can group learners by their actual
                // class instead of guessing from grade_level alone.
                $student->setAttribute('class_id', $class->id);
                $student->setAttribute('class_name', $class->name);
                $students->push($student);
            }
        }
    }
    
    // 3. Pluck the correct User IDs for the stats query
    $studentIds = $students->pluck('id')->toArray();

    // 4. Calculate Phil-IRI stats from the verified list, split by
    // test_type (pre_test vs post_test) so the dashboard can show a
    // before/after comparison instead of one merged count.
    $rawStats = \App\Models\StudentProgress::whereIn('user_id', $studentIds)
        ->selectRaw('LOWER(reading_level) as level, LOWER(test_type) as test_type, count(*) as count')
        ->whereNotNull('reading_level')
        ->groupBy('level', 'test_type')
        ->get();

    $preTestStats = ['frustration' => 0, 'instructional' => 0, 'independent' => 0];
    $postTestStats = ['frustration' => 0, 'instructional' => 0, 'independent' => 0];

    foreach ($rawStats as $row) {
        if ($row->test_type === 'pre_test' && isset($preTestStats[$row->level])) {
            $preTestStats[$row->level] = (int) $row->count;
        } elseif ($row->test_type === 'post_test' && isset($postTestStats[$row->level])) {
            $postTestStats[$row->level] = (int) $row->count;
        }
    }

    // 5. Return the JSON structure the Flutter app expects.
    // frustration_count / instructional_count / independent_count are kept
    // (now as pre+post combined) so older app builds don't break; pre_test
    // and post_test are the new breakdown used by the updated dashboard.
    return response()->json([
        'frustration_count' => $preTestStats['frustration'] + $postTestStats['frustration'],
        'instructional_count' => $preTestStats['instructional'] + $postTestStats['instructional'],
        'independent_count' => $preTestStats['independent'] + $postTestStats['independent'],
        'pre_test' => $preTestStats,
        'post_test' => $postTestStats,
        'students' => $students->values() // ->values() resets the array keys for Flutter ListView
    ], 200);
}
    // 📊 3. TEACHER: FETCH ALL CLASSES WITH ENROLLED STUDENTS
    public function getTeacherClasses($teacher_id)
    {
        $classes = SchoolClass::with('students')
            ->where('teacher_id', $teacher_id)
            ->get();

        return response()->json($classes, 200);
    }

    // Get all stories assigned to a specific class
    public function getClassStories(Request $request, $classId)
    {
        $class = SchoolClass::with(['stories.quiz', 'stories.pages'])->findOrFail($classId);

        $stories = $class->stories->map(function ($story) use ($request) {
            $studentId = $request->query('student_id');
            if ($studentId) {
                // A story can be assigned to the SAME class as both a
                // pre_test and a post_test (in two separate rows of the
                // class_story pivot). Without filtering by test_type here,
                // ->orderBy('id','desc')->first() would just grab whichever
                // attempt happened most recently — usually the post_test —
                // and show that score on both tiles. Match it to the
                // test_type this particular story tile is assigned as.
                $assignedTestType = $story->pivot->test_type ?? 'post_test';

                $progress = \App\Models\StudentProgress::where('user_id', $studentId)
                    ->where('story_id', $story->id)
                    ->where('test_type', $assignedTestType)
                    ->orderBy('id', 'desc')
                    ->first();
                $story->setAttribute('student_progress', $progress);
            }
            return $story;
        });

        return response()->json([
            'success' => true,
            'stories' => $stories
        ], 200);
    }

// Assign an existing library story to a class
public function assignStoryToClass(Request $request, $classId)
{
    return response()->json([
        'status' => 'error',
        'message' => 'Assigning stories directly to classes is deprecated. Please assign reading tests via the Students tab.'
    ], 410);
}

// Unassign a story from a class
public function unassignStoryFromClass(Request $request, $classId)
{
    $request->validate([
        'story_id' => 'required|exists:stories,id',
        'test_type' => 'nullable|in:pre_test,post_test',
    ]);

    $class = SchoolClass::findOrFail($classId);

    if ($request->filled('test_type')) {
        // Detach only the specific pre_test/post_test assignment — a
        // story assigned as both should keep the other one intact.
        $class->stories()
            ->wherePivot('test_type', $request->test_type)
            ->detach($request->story_id);
    } else {
        // No test_type given (older app builds) — fall back to the old
        // behavior of removing every assignment of this story.
        $class->stories()->detach($request->story_id);
    }

    return response()->json([
        'success' => true,
        'message' => 'Story unassigned from class successfully!'
    ], 200);
}

public function saveProgress(Request $request)
{
    $validated = $request->validate([
        'user_id' => 'required|exists:users,id',
        'story_id' => 'required|exists:stories,id',
        'quiz_score' => 'required|integer',
        'total_questions' => 'required|integer',
        'oral_fluency_accuracy' => 'required|numeric',
        'time_on_task' => 'required|integer',
        'wpm' => 'nullable|numeric|min:0',
        'test_type' => 'required|string',
    ]);

    $quizPercent = $validated['total_questions'] > 0 
        ? ($validated['quiz_score'] / $validated['total_questions']) * 100 
        : 0;
    $oralPercent = $validated['oral_fluency_accuracy'];

    // Auto-classify Phil-IRI Reading Level
    if ($oralPercent >= 97 && $quizPercent >= 80) {
        $level = 'independent';
    } elseif ($oralPercent >= 90 && $quizPercent >= 59) {
        $level = 'instructional';
    } else {
        $level = 'frustration';
    }

    $progress = StudentProgress::create([
        'user_id' => $validated['user_id'],
        'story_id' => $validated['story_id'],
        'quiz_score' => $validated['quiz_score'],
        'total_questions' => $validated['total_questions'],
        'oral_fluency_accuracy' => $validated['oral_fluency_accuracy'],
        'reading_level' => $level,
        'time_on_task' => $validated['time_on_task'],
        'wpm' => $validated['wpm'] ?? null,
        'test_type' => $validated['test_type'],
    ]);

    return response()->json([
        'status' => 'success',
        'data' => $progress,
    ], 201);
}

    // 🗑️ TEACHER: DELETE A CLASS
    // Didn't exist before — the app was calling DELETE /api/classes/{id}
    // with no matching route/method, hence the 405.
    public function destroy($id)
    {
        $schoolClass = SchoolClass::find($id);

        if (!$schoolClass) {
            return response()->json(['message' => 'Class not found'], 404);
        }

        try {
            // Detach pivot rows first (class_student, class_story) so no
            // orphaned links remain pointing at a class that no longer exists.
            $schoolClass->students()->detach();
            $schoolClass->stories()->detach();

            $schoolClass->delete();

            return response()->json([
                'success' => true,
                'message' => 'Class deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete class: ' . $e->getMessage(),
            ], 500);
        }
    }

    // 👥 TEACHER: LIST STUDENTS NOT YET IN THIS CLASS
    // Powers the "Add to Class" sheet, which was calling
    // GET /api/classes/{id}/available-students with nothing to answer it —
    // that's why the sheet spun forever instead of erroring or listing
    // anyone. Matches on grade_level (same grade as the class) and
    // excludes students already attached via the class_student pivot.
    public function availableStudents($classId)
    {
        $class = SchoolClass::findOrFail($classId);

        $alreadyEnrolledIds = $class->students()->pluck('users.id');

        $available = User::where('role', 'student')
            ->where('grade_level', $class->grade_level)
            ->whereNotIn('id', $alreadyEnrolledIds)
            ->select('id', 'name', 'lrn', 'grade_level', 'section')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $available,
        ], 200);
    }

    // ➕ TEACHER: ADD MULTIPLE EXISTING STUDENTS TO A CLASS AT ONCE
    // The other half of the "Add to Class" sheet — it POSTs a
    // {"student_ids": [...]} body to /api/classes/{id}/bulk-add-students
    // after the admin/teacher checks off students from availableStudents().
    public function bulkAddStudents(Request $request, $classId)
    {
        $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'integer|exists:users,id',
        ]);

        $class = SchoolClass::findOrFail($classId);

        // syncWithoutDetaching so re-adding an already-enrolled student
        // (e.g. a stale checkbox state) doesn't error or duplicate rows.
        $class->students()->syncWithoutDetaching($request->student_ids);

        return response()->json([
            'success' => true,
            'message' => count($request->student_ids) . ' student(s) added to class.',
        ], 200);
    }
}