import re
content = open('app/Http/Controllers/Api/ClassController.php', encoding='utf-8').read()

pattern = re.compile(r'public function assignStory.*?\}', re.DOTALL)
match = pattern.search(content)
if match:
    old_method = match.group(0)
    new_method = '''public function assignStory(Request $request, $id)
    {
        $validated = $request->validate([
            'story_id' => 'required|array',
            'story_id.*' => 'exists:stories,id',
            'test_type' => 'nullable|string|in:pre_test,post_test'
        ]);

        $class = SchoolClass::findOrFail($id);

        // Attach each story with its test_type
        $testType = $validated['test_type'] ?? 'post_test';
        $syncData = [];
        foreach ($validated['story_id'] as $storyId) {
            $syncData[$storyId] = ['test_type' => $testType];
        }

        $class->stories()->syncWithoutDetaching($syncData);

        return response()->json([
            'status' => 'success',
            'message' => 'Stories assigned successfully.',
        ], 200);
    }'''
    content = content.replace(old_method, new_method)

    old_get = "            $query->with(['stories' => function ($q) {\n                $q->select('stories.id', 'title', 'cover_image', 'level', 'description');\n            }]);"
    new_get = "            $query->with(['stories' => function ($q) {\n                $q->select('stories.id', 'title', 'cover_image', 'level', 'description')->withPivot('test_type');\n            }]);"
    content = content.replace(old_get, new_get)

    open('app/Http/Controllers/Api/ClassController.php', 'w', encoding='utf-8').write(content)
    print('Updated ClassController')
else:
    print('assignStory not found')
