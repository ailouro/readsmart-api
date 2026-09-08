<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Str; // Importante para sa random code generator

class SectionController extends Controller
{
    // 1. KUMUHA NG MGA SECTIONS NG ISANG TEACHER
    public function index(Request $request)
    {
        $teacherId = $request->query('teacher_id');
        
        // Kukunin nito lahat ng section na ginawa ng teacher, pati na ang bawat estudyante na naka-join dito
        $sections = Section::query()->where('teacher_id', $teacherId)->get();
        
        return response()->json($sections);
    }

    // 2. GUMAWA NG BAGONG SECTION (May kasamang auto-generate Class Code)
    public function store(Request $request)
    {
        $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'section_name' => 'required|string|max:255',
        ]);

        // Gagawa ng random 6-character Code (Halimbawa: AB39XQ)
        $classCode = strtoupper(Str::random(6));

        // Siguraduhing walang kapareho ang code (Unique Checker)
        while(Section::query()->where('class_code', $classCode)->exists()) {
            $classCode = strtoupper(Str::random(6));
        }

        // I-save ang bagong section sa database
        $section = Section::create([
            'teacher_id' => $request->teacher_id,
            'section_name' => $request->section_name,
            'class_code' => $classCode,
        ]);

        return response()->json([
            'message' => 'Section successfully created!', 
            'section' => $section
        ], 201);
    }
}