<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestContentSeeder extends Seeder
{
    public function run()
    {
        // 1. Linisin muna ang mga tables para siguradong simula sa empty
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('student_progress')->truncate();
        DB::table('questions')->truncate();
        DB::table('quizzes')->truncate();
        DB::table('stories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // 2. Mag-inject ng isang Sample Story para sa Testing ng Flutter
        $storyId = DB::table('stories')->insertGetId([
            'title' => 'Ang Paglalakbay ni ReadSmart (Test Comic)',
            'description' => 'Isang sample comic profile para ma-test kung gumagana ang layout sa Flutter.',
            'level' => 'frustration', // Level entry sample
            'cover_image' => 'https://placeholder.com/cover.png',
            'pages' => json_encode([
                ['panel_image' => 'https://placeholder.com/page1.png', 'text' => 'Unang pahina ng kwento...'],
                ['panel_image' => 'https://placeholder.com/page2.png', 'text' => 'Ikalawang pahina ng kwento...']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Mag-inject ng Sample Quiz para sa Story na ito (Objective 2 Simulation)
        $quizId = DB::table('quizzes')->insertGetId([
            'story_id' => $storyId,
            'title' => 'Comprehension Check (Test Quiz)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Question 1: Multiple Choice
        DB::table('questions')->insert([
            'quiz_id' => $quizId,
            'question_text' => 'Sino ang pangunahing tauhan sa subok na kwento?',
            'type' => 'multiple_choice',
            'options' => json_encode(['ReadSmart', 'Juan', 'Pedro', 'Maria']),
            'correct_answer' => 'ReadSmart',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Question 2: Custom/True or False para sa testing niyo
        DB::table('questions')->insert([
            'quiz_id' => $quizId,
            'question_text' => 'Gumagana ba nang maayos ang integration ng system niyo?',
            'type' => 'multiple_choice',
            'options' => json_encode(['Oo', 'Hindi', 'Ewan', 'Medyo']),
            'correct_answer' => 'Oo',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}