<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function createStudent(Request $request)
    {
        $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'lrn' => 'required|unique:users',
            'grade_level' => 'required',
            'section' => 'required'
        ]);

        $result = DB::transaction(function () use ($request) {
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'lrn' => $request->lrn,
                'grade_level' => $request->grade_level,
                'section' => $request->section,
                'role' => 'student',
                'password' => Hash::make('readsmart123')
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'name' => trim("{$request->first_name} {$request->last_name}"),
                'grade_level' => $request->grade_level,
                'section' => $request->section,
            ]);

            return [$user, $student];
        });

        [$user, $student] = $result;

        return response()->json([
            'message' => 'Student created successfully',
            'student' => $student,
            'user' => $user
        ]);
    }
}