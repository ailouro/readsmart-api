<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StudentRequest;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\StudentAccountApproved;

class AdminWebController extends Controller
{
    // Ipakita ang Dashboard kasama ang mga pending requests
    public function index()
    {
        $pendingRequests = StudentRequest::where('status', 'pending')->get();
        return view('admin.dashboard', compact('pendingRequests'));
    }

    // Kapag pinindot ng admin ang "Approve"
    public function approveRequest($id)
    {
        $req = StudentRequest::findOrFail($id);

        // 1. Gumawa ng user account para sa student
        $defaultPassword = 'readsmart123'; // Default password para sa lahat ng bagong students
        
        $student = User::create([
            'name' => $req->student_name,
            'lrn' => $req->lrn,
            'grade_level' => $req->grade_level,
            'section' => $req->section,
            'email' => $req->lrn . '@student.readsmart', // Dummy email fallback
            'password' => Hash::make($defaultPassword),
            'role' => 'student'
        ]);

        // 2. I-update ang status ng request
        $req->update(['status' => 'approved']);

        // 3. I-send ang email sa parent!
        Mail::to($req->parent_email)->send(new StudentAccountApproved($student, $defaultPassword));

        return back()->with('success', 'Student account approved! An email has been sent to the parent.');
    }

    // Kapag pinindot ng admin ang "Reject"
    public function rejectRequest($id)
    {
        $req = StudentRequest::findOrFail($id);
        $req->update(['status' => 'rejected']);

        return back()->with('error', 'Student request rejected.');
    }
}