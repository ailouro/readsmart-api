<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function createStudent(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'lrn' => 'required|unique:users',
            'grade_level' => 'required',
            'section' => 'required'
        ]);

        $student = User::create([
            'name' => $request->name,
            'lrn' => $request->lrn,
            'grade_level' => $request->grade_level,
            'section' => $request->section,
            'role' => 'student',
            'password' => Hash::make('readsmart123')
        ]);

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student
        ]);
    }
}